<?php

namespace Tests\Feature\V2;

use App\Models\V2\ExamAttempt;
use App\Models\V2\NotesPage;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Learning Hub Notes - page lifecycle (autosave + optimistic concurrency),
 * version snapshots/restore, search, ownership, and the import bridge
 * (asset/mistake/widget_state) with its visibility + release gating.
 * Same minimal-graph approach as LearningHubMistakeBankTest (sqlite :memory:).
 */
class LearningHubNotesTest extends TestCase
{
    use DatabaseMigrations;

    private MistakeBankService $svc;
    private int $schoolId;
    private int $subjectId;
    private int $topicId;
    private int $studentId;
    private int $qNum = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        $this->svc = app(MistakeBankService::class);
        $this->schoolId  = DB::table('v2_schools')->insertGetId(['name' => 'Sch', 'contact_email' => 's@s.edu', 'created_at' => now(), 'updated_at' => now()]);
        $this->subjectId = DB::table('v2_subjects')->insertGetId(['name' => 'Physics', 'created_at' => now(), 'updated_at' => now()]);
        $this->topicId   = DB::table('v2_topics')->insertGetId(['subject_id' => $this->subjectId, 'external_id' => '1', 'title' => 'Kinematics', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Stu', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    /* ============================ Helpers ============================== */

    private function student(): Student
    {
        return Student::withoutGlobalScopes()->findOrFail($this->studentId);
    }

    private function actingAsStudent(): static
    {
        return $this->actingAs($this->student(), 'v2_student');
    }

    private function makeQuestion(string $correct = 'A'): int
    {
        return DB::table('v2_questions')->insertGetId([
            'paper_id' => 1, 'subject_id' => $this->subjectId, 'topic_id' => $this->topicId,
            'question_number' => ++$this->qNum, 'correct_answer' => $correct,
            'difficulty' => 'medium', 'year' => 2020, 'source_paper' => '9702_x_qp_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeExam(bool $released = true): int
    {
        return DB::table('v2_exams')->insertGetId([
            'school_id' => $this->schoolId, 'class_id' => 1, 'subject_id' => $this->subjectId, 'created_by' => 1,
            'title' => 'Test', 'question_count' => 1, 'status' => 'released', 'published_at' => now(),
            'results_released_at' => $released ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function attempt(int $examId, int $questionId, string $selected, string $correct): ExamAttempt
    {
        $attemptId = DB::table('v2_exam_attempts')->insertGetId([
            'exam_id' => $examId, 'student_id' => $this->studentId, 'school_id' => $this->schoolId,
            'status' => 'submitted', 'total_questions' => 1, 'score' => $selected === $correct ? 1 : 0,
            'started_at' => now(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_exam_questions')->insert([
            'exam_id' => $examId, 'question_id' => $questionId, 'sort_order' => 1, 'marks' => 1,
            'is_voided' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_exam_answers')->insert([
            'attempt_id' => $attemptId, 'question_id' => $questionId, 'selected_option' => $selected,
            'correct_option' => $correct, 'is_correct' => $selected === $correct,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ExamAttempt::withoutGlobalScopes()->findOrFail($attemptId);
    }

    /** A released (or not) mistake for a fresh question; returns [mistake, questionId]. */
    private function makeMistake(bool $released = true): array
    {
        $q = $this->makeQuestion('A');
        $this->svc->syncFromAttempt($this->attempt($this->makeExam($released), $q, 'B', 'A'));

        return [StudentMistake::withoutGlobalScopes()->where('question_id', $q)->firstOrFail(), $q];
    }

    /** Create a page through the real endpoint (also provisions the default notebook/section). */
    private function makePage(): NotesPage
    {
        $this->actingAsStudent()->postJson(route('v2.student.notes.pages.store'))->assertOk();

        return NotesPage::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function importUrl(NotesPage $page): string
    {
        return route('v2.student.notes.pages.import', $page);
    }

    private function paragraph(string $text): array
    {
        return ['id' => 'p1', 'type' => 'paragraph', 'props' => [],
            'content' => [['type' => 'text', 'text' => $text, 'styles' => (object) []]], 'children' => []];
    }

    /* ======================= Lifecycle + autosave ====================== */

    public function test_create_provisions_defaults_and_autosave_bumps_version(): void
    {
        $page = $this->makePage();

        $this->assertDatabaseHas('v2_notes_notebooks', ['student_id' => $this->studentId, 'title' => 'My Notes']);
        $this->assertSame(1, $page->content_version);

        $doc = [$this->paragraph('Ohm’s law: V = IR'),
            ['id' => 'f1', 'type' => 'flashcard', 'props' => ['front' => 'State Ohm’s law', 'back' => 'V = IR'], 'content' => [], 'children' => []]];

        $this->actingAsStudent()
            ->putJson(route('v2.student.notes.pages.update', $page), [
                'title' => 'Electricity', 'document' => $doc, 'content_version' => 1,
            ])
            ->assertOk()->assertJsonPath('contentVersion', 2);

        $page->refresh();
        $this->assertSame('Electricity', $page->title);
        $this->assertStringContainsString('Ohm’s law: V = IR', $page->plain_text);
        $this->assertStringContainsString('State Ohm’s law', $page->plain_text); // card props indexed
        $this->assertContains('flashcard', $page->block_types_json);
    }

    public function test_stale_autosave_gets_409_with_server_copy_never_clobbers(): void
    {
        $page = $this->makePage();
        $url = route('v2.student.notes.pages.update', $page);

        $this->actingAsStudent()->putJson($url, [
            'document' => [$this->paragraph('newer content')], 'content_version' => 1,
        ])->assertOk();

        $this->actingAsStudent()->putJson($url, [
            'document' => [$this->paragraph('stale tab content')], 'content_version' => 1,
        ])->assertStatus(409)->assertJsonPath('contentVersion', 2);

        $this->assertStringContainsString('newer content', $page->refresh()->plain_text);
    }

    public function test_versions_snapshot_and_restore_round_trip(): void
    {
        $page = $this->makePage();
        $url = route('v2.student.notes.pages.update', $page);

        $this->actingAsStudent()->putJson($url, ['title' => 'First', 'document' => [$this->paragraph('v1')], 'content_version' => 1])->assertOk();
        $this->travel(3)->minutes(); // past the snapshot throttle
        $this->actingAsStudent()->putJson($url, ['title' => 'Second', 'document' => [$this->paragraph('v2')], 'content_version' => 2])->assertOk();

        $versions = $this->actingAsStudent()->getJson(route('v2.student.notes.pages.versions', $page))
            ->assertOk()->json('versions');
        $this->assertNotEmpty($versions);

        $restored = $this->actingAsStudent()->postJson($versions[0]['restoreUrl'])->assertOk()->json();
        $this->assertSame('First', $restored['title']);
        $this->assertStringContainsString('v1', $page->refresh()->plain_text);
    }

    public function test_students_cannot_touch_another_students_page(): void
    {
        $page = $this->makePage();

        $otherId = DB::table('v2_students')->insertGetId([
            'school_id' => $this->schoolId, 'name' => 'Other', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = Student::withoutGlobalScopes()->findOrFail($otherId);

        $this->actingAs($other, 'v2_student')
            ->putJson(route('v2.student.notes.pages.update', $page), ['document' => [], 'content_version' => 1])
            ->assertForbidden();
        $this->actingAs($other, 'v2_student')
            ->postJson($this->importUrl($page), ['source' => 'mistake', 'mistake_id' => 'x'])
            ->assertForbidden();
    }

    /* =========================== Import bridge ========================= */

    public function test_import_is_gated_on_release_and_asset_visibility(): void
    {
        [$mistake, $q] = $this->makeMistake(released: false);
        $page = $this->makePage();
        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'worked_solution', 'content' => '# Steps', 'status' => 'approved']);

        $payload = ['source' => 'asset', 'asset_type' => 'worked_solution', 'mistake_id' => $mistake->getRouteKey()];

        // Unreleased exam -> the mistake (and its assets) are not importable yet.
        $this->actingAsStudent()->postJson($this->importUrl($page), $payload)->assertNotFound();

        DB::table('v2_exams')->where('id', $mistake->latest_exam_id)->update(['results_released_at' => now()]);
        $this->actingAsStudent()->postJson($this->importUrl($page), $payload)
            ->assertOk()->assertJsonPath('blocks.0.type', 'asset_snapshot');

        // Draft assets stay invisible even when released.
        QuestionLearningAsset::where('question_id', $q)->update(['status' => 'draft']);
        $this->actingAsStudent()->postJson($this->importUrl($page), $payload)->assertNotFound();
    }

    public function test_asset_types_map_to_their_blocks_and_page_inherits_curriculum(): void
    {
        [$mistake, $q] = $this->makeMistake();
        $page = $this->makePage();
        $this->assertNull($page->subject_id);

        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'flashcards', 'status' => 'approved',
            'payload_json' => [['front' => 'F1', 'back' => 'B1'], ['front' => 'F2', 'back' => 'B2']]]);
        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'mermaid', 'status' => 'approved',
            'content' => "flowchart TD\n A-->B", 'format' => 'mermaid']);
        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'interactive_widget', 'status' => 'approved',
            'payload_json' => ['widget' => 'calculator', 'config' => ['symbol' => 'F']]]);

        $import = fn (string $type) => $this->actingAsStudent()
            ->postJson($this->importUrl($page), ['source' => 'asset', 'asset_type' => $type, 'mistake_id' => $mistake->getRouteKey()])
            ->assertOk()->json('blocks');

        $cards = $import('flashcards');
        $this->assertCount(2, $cards);
        $this->assertSame(['flashcard', 'F1', 'B1'], [$cards[0]['type'], $cards[0]['props']['front'], $cards[0]['props']['back']]);

        $this->assertSame('mermaid', $import('mermaid')[0]['type']);

        $widget = $import('interactive_widget')[0];
        $this->assertSame('widget', $widget['type']);
        $this->assertSame('calculator', $widget['props']['widgetType']);
        $this->assertSame(['symbol' => 'F'], json_decode($widget['props']['config'], true));

        // Blocks landed in the document + page inherited the mistake's curriculum.
        $page->refresh();
        $this->assertContains('widget', $page->block_types_json);
        $this->assertSame($this->subjectId, $page->subject_id);
        $this->assertSame($this->topicId, $page->topic_id);
    }

    public function test_mistake_import_includes_the_question_diagram_as_a_reference(): void
    {
        [$mistake, $q] = $this->makeMistake();
        // A diagram image on the question (role=diagram, not an option crop).
        DB::table('v2_question_images')->insert([
            'question_id' => $q, 'image_path' => 'questions/5054/diagram-1.png', 'role' => 'diagram',
            'option_label' => null, 'sort_order' => 1, 'caption' => 'Plank on two supports',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $blocks = $this->actingAsStudent()
            ->postJson($this->importUrl($this->makePage()), ['source' => 'mistake', 'mistake_id' => $mistake->getRouteKey()])
            ->assertOk()->json('blocks');

        $figure = collect($blocks)->firstWhere('type', 'question_figure');
        $this->assertNotNull($figure, 'a question_figure reference block should be inserted');
        // A reference only — path + question, never a URL.
        $this->assertSame('questions/5054/diagram-1.png', $figure['props']['imagePath']);
        $this->assertSame($q, $figure['props']['questionId']);
        $this->assertArrayNotHasKey('url', $figure['props']);
    }

    public function test_worked_solution_import_carries_the_diagram_but_not_twice_in_a_section(): void
    {
        [$mistake, $q] = $this->makeMistake();
        DB::table('v2_question_images')->insert([
            'question_id' => $q, 'image_path' => 'questions/5054/diagram-1.png', 'role' => 'question_diagram',
            'option_label' => null, 'sort_order' => 1, 'caption' => '',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'worked_solution', 'content' => '# Steps', 'status' => 'approved']);
        $page = $this->makePage();
        $url = $this->importUrl($page);
        $mid = ['mistake_id' => $mistake->getRouteKey()];

        // Worked solution brings the diagram (prepended before the snapshot).
        $blocks = $this->actingAsStudent()->postJson($url, ['source' => 'asset', 'asset_type' => 'worked_solution'] + $mid)
            ->assertOk()->json('blocks');
        $this->assertSame(['question_figure', 'asset_snapshot'], array_column($blocks, 'type'));

        // Import the mistake into the same (single) section → its diagram is deduped away.
        $mistakeBlocks = $this->actingAsStudent()->postJson($url, ['source' => 'mistake'] + $mid)
            ->assertOk()->json('blocks');
        $this->assertContains('mistake', array_column($mistakeBlocks, 'type'));
        $this->assertNotContains('question_figure', array_column($mistakeBlocks, 'type'), 'diagram already in the section — not added again');

        // The whole note has exactly one copy of the diagram.
        $figures = array_filter($page->refresh()->document_json, fn ($b) => ($b['type'] ?? '') === 'question_figure');
        $this->assertCount(1, $figures);
    }

    public function test_same_diagram_allowed_once_per_page_break_section(): void
    {
        [$mistake, $q] = $this->makeMistake();
        DB::table('v2_question_images')->insert([
            'question_id' => $q, 'image_path' => 'questions/5054/diagram-1.png', 'role' => 'diagram',
            'option_label' => null, 'sort_order' => 1, 'caption' => '',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // A note with one diagram already in "page 1", then a page break.
        $page = $this->makePage();
        $this->actingAsStudent()->putJson(route('v2.student.notes.pages.update', $page), [
            'document' => [
                ['id' => 'fig0', 'type' => 'question_figure', 'props' => ['questionId' => $q, 'imagePath' => 'questions/5054/diagram-1.png', 'caption' => '', 'questionVersionId' => 0], 'content' => [], 'children' => []],
                ['id' => 'br1', 'type' => 'page_break', 'props' => ['label' => 'Page 2'], 'content' => [], 'children' => []],
            ],
            'content_version' => 1,
        ])->assertOk();

        // Import the mistake at the END (page 2) — different section, so the diagram IS added.
        $this->actingAsStudent()->postJson($this->importUrl($page), ['source' => 'mistake', 'mistake_id' => $mistake->getRouteKey()])
            ->assertOk();

        $figures = array_filter($page->refresh()->document_json, fn ($b) => ($b['type'] ?? '') === 'question_figure');
        $this->assertCount(2, $figures, 'one diagram per page-break section');
    }

    public function test_figure_urls_are_minted_only_while_the_mistake_is_released(): void
    {
        $service = app(\App\Services\V2\NotesDocumentService::class);
        [$mistake, $q] = $this->makeMistake(released: true);
        $doc = [[
            'id' => 'f1', 'type' => 'question_figure',
            'props' => ['questionId' => $q, 'imagePath' => 'questions/5054/diagram-1.png', 'caption' => '', 'questionVersionId' => 0],
            'content' => [], 'children' => [],
        ]];

        // Released → a fresh signed URL is minted for the path.
        $urls = $service->figureUrls($doc, $this->student());
        $this->assertArrayHasKey('questions/5054/diagram-1.png', $urls);
        $this->assertStringContainsString('v2/img', $urls['questions/5054/diagram-1.png']);
        $this->assertStringContainsString('s=', $urls['questions/5054/diagram-1.png']); // HMAC signature present

        // Un-release the exam → no URL (access re-check fails closed).
        DB::table('v2_exams')->where('id', $mistake->latest_exam_id)->update(['results_released_at' => null]);
        $this->assertSame([], $service->figureUrls($doc, $this->student()));
    }

    public function test_mistake_import_creates_live_reference_block_with_explanations(): void
    {
        [$mistake, $q] = $this->makeMistake();
        $page = $this->makePage();
        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'option_explanation', 'status' => 'approved',
            'payload_json' => ['options' => [
                ['label' => 'A', 'text' => 'Right', 'correct' => true],
                ['label' => 'B', 'text' => 'Wrong', 'why' => 'Sign error'],
            ]]]);

        $blocks = $this->actingAsStudent()
            ->postJson($this->importUrl($page), ['source' => 'mistake', 'mistake_id' => $mistake->getRouteKey()])
            ->assertOk()->json('blocks');

        $this->assertSame('mistake', $blocks[0]['type']);
        $this->assertSame('B', $blocks[0]['props']['selected']);
        $this->assertSame('A', $blocks[0]['props']['correct']);
        $this->assertSame($mistake->getRouteKey(), $blocks[0]['props']['mistakeId']);

        $callouts = array_values(array_filter($blocks, fn ($b) => $b['type'] === 'callout'));
        $this->assertCount(2, $callouts);
        $this->assertSame(['correct', 'wrong'], [$callouts[0]['props']['variant'], $callouts[1]['props']['variant']]);
    }

    public function test_widget_state_import_must_match_the_questions_own_widget(): void
    {
        [$mistake, $q] = $this->makeMistake();
        $page = $this->makePage();
        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'interactive_widget', 'status' => 'approved',
            'payload_json' => ['widget' => 'graph_explorer', 'config' => ['xMax' => 10]]]);

        $url = $this->importUrl($page);
        $base = ['source' => 'widget_state', 'mistake_id' => $mistake->getRouteKey()];

        // A widget type the question doesn't have -> rejected.
        $this->actingAsStudent()->postJson($url, $base + ['widget' => 'calculator', 'config' => []])->assertNotFound();

        // The question's own widget, with the student's adjusted state -> saved verbatim.
        $blocks = $this->actingAsStudent()
            ->postJson($url, $base + ['widget' => 'graph_explorer', 'config' => ['xMax' => 10, '_state' => ['x' => 4]]])
            ->assertOk()->json('blocks');
        $this->assertSame(['x' => 4], json_decode($blocks[0]['props']['config'], true)['_state']);
    }

    /* ==================== Subject-aware hierarchy ====================== */

    public function test_store_with_subject_provisions_notebook_and_topic_section(): void
    {
        // First create: notebook named after the subject + section after the topic.
        $res = $this->actingAsStudent()->postJson(route('v2.student.notes.pages.store'), [
            'subject_id' => $this->subjectId, 'topic_id' => $this->topicId, 'title' => 'Suvat',
        ])->assertOk();

        $this->assertDatabaseHas('v2_notes_notebooks', [
            'student_id' => $this->studentId, 'subject_id' => $this->subjectId, 'title' => 'Physics',
        ]);
        $this->assertDatabaseHas('v2_notes_sections', [
            'student_id' => $this->studentId, 'topic_id' => $this->topicId, 'title' => 'Kinematics',
        ]);

        // The note inherits the curriculum tags from its home.
        $page = NotesPage::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame($this->subjectId, $page->subject_id);
        $this->assertSame($this->topicId, $page->topic_id);

        // Second create in the same home: no duplicate notebook/section.
        $this->actingAsStudent()->postJson(route('v2.student.notes.pages.store'), [
            'subject_id' => $this->subjectId, 'topic_id' => $this->topicId,
        ])->assertOk();
        $this->assertSame(1, DB::table('v2_notes_notebooks')->where('subject_id', $this->subjectId)->count());
        $this->assertSame(1, DB::table('v2_notes_sections')->where('topic_id', $this->topicId)->count());
    }

    public function test_tree_provisions_notebooks_for_enrolled_subjects(): void
    {
        // Enroll the student in a Physics class, then open the tree.
        $classId = DB::table('v2_classes')->insertGetId([
            'school_id' => $this->schoolId, 'grade_id' => 1, 'subject_id' => $this->subjectId,
            'name' => 'O-A Physics', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_student_enrollments')->insert([
            'student_id' => $this->studentId, 'class_id' => $classId, 'school_id' => $this->schoolId,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $tree = $this->actingAsStudent()->getJson(route('v2.student.notes.tree'))->assertOk()->json('tree');

        $physics = collect($tree)->firstWhere('title', 'Physics');
        $this->assertNotNull($physics, 'enrolled subject should appear as a notebook');
        $this->assertSame($this->subjectId, $physics['subjectId']);
    }

    /* ================== Page breaks + positional import ================ */

    public function test_outline_splits_on_page_breaks_and_import_inserts_before_a_break(): void
    {
        [$mistake, $q] = $this->makeMistake();
        QuestionLearningAsset::create(['question_id' => $q, 'asset_type' => 'mermaid', 'status' => 'approved',
            'content' => "flowchart TD\n A-->B", 'format' => 'mermaid']);

        // A note with two book-style pages: [para, BREAK(Waves), para].
        $page = $this->makePage();
        $this->actingAsStudent()->putJson(route('v2.student.notes.pages.update', $page), [
            'document' => [
                $this->paragraph('page one content'),
                ['id' => 'br1', 'type' => 'page_break', 'props' => ['label' => 'Waves'], 'content' => [], 'children' => []],
                $this->paragraph('page two content'),
            ],
            'content_version' => 1,
        ])->assertOk();

        // Outline: two sections; the first inserts before br1, the last appends.
        $outline = $this->actingAsStudent()->getJson(route('v2.student.notes.pages.outline', $page))
            ->assertOk()->json();
        $this->assertSame(['Page 1', 'Page 2 — Waves'], array_column($outline['sections'], 'label'));
        $this->assertSame(['br1', null], array_column($outline['sections'], 'insertBeforeId'));

        // Import into "Page 1" -> the mermaid block lands BEFORE the break.
        $this->actingAsStudent()->postJson($this->importUrl($page), [
            'source' => 'asset', 'asset_type' => 'mermaid',
            'mistake_id' => $mistake->getRouteKey(), 'before_block_id' => 'br1',
        ])->assertOk();

        $types = array_column($page->refresh()->document_json, 'type');
        $this->assertSame(['paragraph', 'mermaid', 'page_break', 'paragraph'], $types);

        // Unknown target degrades to append, never an error.
        $this->actingAsStudent()->postJson($this->importUrl($page), [
            'source' => 'asset', 'asset_type' => 'mermaid',
            'mistake_id' => $mistake->getRouteKey(), 'before_block_id' => 'nope',
        ])->assertOk();
        $doc = $page->refresh()->document_json;
        $this->assertSame('mermaid', end($doc)['type']);
    }

    /* ============================== Uploads ============================ */

    public function test_note_image_upload_stores_privately_and_serves_owner_only(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        // A real 1x1 PNG (GD isn't installed on the test host, so no File::image()).
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $upload = $this->actingAsStudent()->post(route('v2.student.notes.uploads.store'), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('handwritten.png', $png),
        ], ['Accept' => 'application/json']);
        $upload->assertOk();
        $url = $upload->json('url');
        $this->assertStringContainsString('/notes/images/', $url);

        // Non-images are rejected.
        $this->actingAsStudent()->post(route('v2.student.notes.uploads.store'), [
            'file' => \Illuminate\Http\Testing\File::create('notes.pdf', 100),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        // The owner can fetch it; another student cannot.
        $this->actingAsStudent()->get($url)->assertOk();

        $otherId = DB::table('v2_students')->insertGetId([
            'school_id' => $this->schoolId, 'name' => 'Other', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(Student::withoutGlobalScopes()->findOrFail($otherId), 'v2_student')
            ->get($url)->assertForbidden();
    }

    /* ============================== Search ============================= */

    public function test_search_finds_pages_by_text_and_block_type(): void
    {
        $page = $this->makePage();
        $this->actingAsStudent()->putJson(route('v2.student.notes.pages.update', $page), [
            'title' => 'Waves',
            'document' => [
                $this->paragraph('Refraction bends light'),
                ['id' => 'f1', 'type' => 'flashcard', 'props' => ['front' => 'n = ?', 'back' => 'sin i / sin r'], 'content' => [], 'children' => []],
            ],
            'content_version' => 1,
        ])->assertOk();

        $byText = $this->actingAsStudent()->getJson(route('v2.student.notes.search', ['q' => 'refraction']))->assertOk()->json('results');
        $this->assertCount(1, $byText);
        $this->assertSame('Waves', $byText[0]['title']);

        $byType = $this->actingAsStudent()->getJson(route('v2.student.notes.search', ['block_type' => 'flashcard']))->assertOk()->json('results');
        $this->assertCount(1, $byType);

        $miss = $this->actingAsStudent()->getJson(route('v2.student.notes.search', ['block_type' => 'mistake']))->assertOk()->json('results');
        $this->assertCount(0, $miss);
    }
}
