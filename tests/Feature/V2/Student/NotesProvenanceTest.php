<?php

namespace Tests\Feature\V2\Student;

use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorMessage;
use App\Models\V2\ExamAttempt;
use App\Models\V2\NotesImport;
use App\Models\V2\NotesPage;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use App\Services\V2\NotesProvenanceService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Save-to-Notes provenance ledger: every import records an exact-source event
 * (repeats allowed, never deduped), the note mutation + ledger row are atomic,
 * and provenance is exact-source (never inferred from question_id) and
 * student-scoped.
 */
class NotesProvenanceTest extends TestCase
{
    use DatabaseMigrations;

    private MistakeBankService $svc;
    private int $schoolId;
    private int $subjectId;
    private int $topicId;
    private int $studentId;
    private int $otherStudentId;
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
        $this->otherStudentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Other', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function student(?int $id = null): Student
    {
        return Student::withoutGlobalScopes()->findOrFail($id ?? $this->studentId);
    }

    private function actingAsStudent(?int $id = null): static
    {
        return $this->actingAs($this->student($id), 'v2_student');
    }

    /** A released mistake with two approved assets (worked_solution + flashcards). */
    private function makeMistake(int $studentId): StudentMistake
    {
        $questionId = DB::table('v2_questions')->insertGetId([
            'paper_id' => 1, 'subject_id' => $this->subjectId, 'topic_id' => $this->topicId,
            'question_number' => ++$this->qNum, 'correct_answer' => 'A', 'question_text' => 'Q?',
            'difficulty' => 'medium', 'year' => 2020, 'source_paper' => '9702_x_qp_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_question_learning_assets')->insert([
            ['question_id' => $questionId, 'asset_type' => 'worked_solution', 'asset_key' => '', 'content' => 'WS body', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $questionId, 'asset_type' => 'flashcards', 'asset_key' => '', 'payload_json' => json_encode([['front' => 'F', 'back' => 'B']]), 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $examId = DB::table('v2_exams')->insertGetId([
            'school_id' => $this->schoolId, 'class_id' => 1, 'subject_id' => $this->subjectId, 'created_by' => 1,
            'title' => 'Test', 'question_count' => 1, 'status' => 'released', 'published_at' => now(),
            'results_released_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $attemptId = DB::table('v2_exam_attempts')->insertGetId([
            'exam_id' => $examId, 'student_id' => $studentId, 'school_id' => $this->schoolId,
            'status' => 'submitted', 'total_questions' => 1, 'score' => 0,
            'started_at' => now(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_exam_questions')->insert([
            'exam_id' => $examId, 'question_id' => $questionId, 'sort_order' => 1, 'marks' => 1,
            'is_voided' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_exam_answers')->insert([
            'attempt_id' => $attemptId, 'question_id' => $questionId, 'selected_option' => 'B',
            'correct_option' => 'A', 'is_correct' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->svc->syncFromAttempt(ExamAttempt::withoutGlobalScopes()->findOrFail($attemptId));

        return StudentMistake::withoutGlobalScopes()->where('student_id', $studentId)->where('question_id', $questionId)->firstOrFail();
    }

    private function makePage(?int $studentId = null): NotesPage
    {
        $this->actingAsStudent($studentId)->postJson(route('v2.student.notes.pages.store'))->assertOk();

        return NotesPage::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function importAsset(NotesPage $page, StudentMistake $mistake, string $assetType): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsStudent()->postJson(route('v2.student.notes.pages.import', $page), [
            'source' => 'asset', 'mistake_id' => $mistake->getRouteKey(), 'asset_type' => $assetType,
        ]);
    }

    private function assetId(StudentMistake $mistake, string $type): int
    {
        return (int) $this->svc->visibleAsset($mistake->question_id, $type)->id;
    }

    public function test_successful_import_writes_one_provenance_row(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $page = $this->makePage();

        $this->importAsset($page, $mistake, 'worked_solution')->assertOk();

        $this->assertSame(1, NotesImport::count());
        $row = NotesImport::firstOrFail();
        $this->assertSame('asset', $row->source);
        $this->assertSame($this->assetId($mistake, 'worked_solution'), $row->source_id);
        $this->assertSame($mistake->question_id, $row->question_id);
        $this->assertSame($page->id, $row->page_id);
    }

    public function test_repeated_save_of_same_source_is_allowed_and_records_each_event(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $page = $this->makePage();

        for ($i = 0; $i < 5; $i++) {
            $this->importAsset($page, $mistake, 'worked_solution')->assertOk();
        }

        // Five events (never deduped); provenance count = 5, one page.
        $this->assertSame(5, NotesImport::count());
        $prov = app(NotesProvenanceService::class)->forSource($this->studentId, 'asset', $this->assetId($mistake, 'worked_solution'));
        $this->assertSame(5, $prov['count']);
        $this->assertCount(1, $prov['pages']);
        $this->assertSame($page->getRouteKey(), $prov['pages'][0]['id']);
        $this->assertSame($page->title, $prov['pages'][0]['title']);
    }

    public function test_provenance_reports_all_pages_containing_the_source(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $pageA = $this->makePage();
        $pageB = $this->makePage();

        $this->importAsset($pageA, $mistake, 'worked_solution')->assertOk();
        $this->importAsset($pageB, $mistake, 'worked_solution')->assertOk();

        $prov = app(NotesProvenanceService::class)->forSource($this->studentId, 'asset', $this->assetId($mistake, 'worked_solution'));
        $this->assertSame(2, $prov['count']);
        $this->assertEqualsCanonicalizing(
            [$pageA->getRouteKey(), $pageB->getRouteKey()],
            collect($prov['pages'])->pluck('id')->all(),
        );
    }

    public function test_exact_source_isolation_across_asset_types_and_ai_answers(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $page = $this->makePage();

        // Save the worked solution only.
        $this->importAsset($page, $mistake, 'worked_solution')->assertOk();

        $prov = app(NotesProvenanceService::class);
        // The worked solution is saved...
        $this->assertSame(1, $prov->forSource($this->studentId, 'asset', $this->assetId($mistake, 'worked_solution'))['count']);
        // ...but its sibling flashcards asset from the SAME question is NOT.
        $this->assertSame(0, $prov->forSource($this->studentId, 'asset', $this->assetId($mistake, 'flashcards'))['count']);

        // And an AI answer on the same question is independent.
        $chat = AiTutorChat::create([
            'student_id' => $this->studentId, 'school_id' => $this->schoolId, 'source_type' => 'mistake',
            'question_id' => $mistake->question_id, 'student_mistake_id' => $mistake->id, 'subject_id' => $this->subjectId,
        ]);
        $answer = $chat->messages()->create(['role' => 'assistant', 'content' => 'hint']);
        $this->assertSame(0, $prov->forSource($this->studentId, 'ai_answer', $answer->id)['count']);
    }

    public function test_provenance_is_student_scoped(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $page = $this->makePage();
        $this->importAsset($page, $mistake, 'worked_solution')->assertOk();

        // Another student querying the same exact source sees nothing.
        $prov = app(NotesProvenanceService::class)->forSource($this->otherStudentId, 'asset', $this->assetId($mistake, 'worked_solution'));
        $this->assertSame(0, $prov['count']);
        $this->assertSame([], $prov['pages']);
    }

    public function test_ledger_failure_rolls_back_the_note_mutation(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $page = $this->makePage();
        $versionBefore = NotesPage::withoutGlobalScopes()->findOrFail($page->id)->content_version;

        // Force the ledger insert to throw AFTER apply() runs, inside the txn.
        NotesImport::saving(function () {
            throw new \RuntimeException('ledger down');
        });

        try {
            $this->importAsset($page, $mistake, 'worked_solution');
        } catch (\Throwable $e) {
            // expected
        } finally {
            NotesImport::flushEventListeners();
        }

        // The document mutation rolled back: no ledger row, and the note's
        // content_version is unchanged (no orphaned save).
        $this->assertSame(0, NotesImport::count());
        $after = NotesPage::withoutGlobalScopes()->findOrFail($page->id);
        $this->assertSame($versionBefore, $after->content_version);
    }
}
