<?php

namespace Tests\Feature\V2\Student;

use App\Models\V2\ExamAttempt;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Anti-scraping exposure guarantees for the Learning Hub surfaces:
 * the LIST is a teaser-only summary (never options/answers/solutions/images),
 * REVIEW/STUDIO are guarded per request, and the STUDIO payload carries only
 * the current question with the debug/JSON inspector off outside local.
 */
class LearningHubExposureTest extends TestCase
{
    use DatabaseMigrations;

    private const STEM = 'A uniform beam of length 2.0 m is pivoted at its centre and a load of 40 N hangs from one end while a spring balance supports the other end at an unknown angle to the horizontal, keeping the whole arrangement in equilibrium during the experiment described below.';
    private const OTHER_STEM = 'SECRET-OTHER-STUDENT-QUESTION about electrolysis of concentrated sodium chloride solution using inert electrodes.';

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

        $this->schoolId  = DB::table('v2_schools')->insertGetId(['name' => 'Sch', 'contact_email' => 's@s.edu', 'created_at' => now(), 'updated_at' => now()]);
        $this->subjectId = DB::table('v2_subjects')->insertGetId(['name' => 'Physics', 'code' => '5054', 'level' => 'O Level', 'created_at' => now(), 'updated_at' => now()]);
        $this->topicId   = DB::table('v2_topics')->insertGetId(['subject_id' => $this->subjectId, 'external_id' => '1', 'title' => 'Kinematics', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Stu Dent', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->otherStudentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Other Kid', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeMistake(int $studentId, string $stem, bool $released = true, string $correct = 'C'): StudentMistake
    {
        $examId = DB::table('v2_exams')->insertGetId([
            'school_id' => $this->schoolId, 'class_id' => 1, 'subject_id' => $this->subjectId, 'created_by' => 1,
            'title' => 'Test', 'question_count' => 1, 'status' => 'released', 'published_at' => now(),
            'results_released_at' => $released ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $questionId = DB::table('v2_questions')->insertGetId([
            'paper_id' => 1, 'subject_id' => $this->subjectId, 'topic_id' => $this->topicId,
            'question_number' => ++$this->qNum, 'correct_answer' => $correct, 'question_text' => $stem,
            'difficulty' => 'medium', 'year' => 2020, 'source_paper' => '5054_x_qp_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['A', 'OPTION-ALPHA distinctive text'], ['B', 'OPTION-BRAVO distinctive text'], ['C', 'OPTION-CHARLIE distinctive text'], ['D', 'OPTION-DELTA distinctive text']] as $i => [$label, $text]) {
            DB::table('v2_question_options')->insert([
                'question_id' => $questionId, 'label' => $label, 'text' => $text, 'sort_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('v2_question_images')->insert([
            'question_id' => $questionId, 'image_path' => 'v2/questions/secret-diagram-path.png',
            'role' => 'question_image_after_text', 'option_label' => null, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_question_learning_assets')->insert([
            ['question_id' => $questionId, 'asset_type' => 'worked_solution', 'asset_key' => '', 'content' => 'APPROVED-SOLUTION step-by-step working', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $questionId, 'asset_type' => 'revision_notes', 'asset_key' => '', 'content' => 'DRAFT-NOTES not yet reviewed', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()],
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
            'correct_option' => $correct, 'is_correct' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(MistakeBankService::class)->syncFromAttempt(ExamAttempt::withoutGlobalScopes()->findOrFail($attemptId));

        return StudentMistake::withoutGlobalScopes()
            ->where('student_id', $studentId)->where('question_id', $questionId)->firstOrFail();
    }

    private function actingAsStudent(?int $id = null): static
    {
        return $this->actingAs(Student::withoutGlobalScopes()->findOrFail($id ?? $this->studentId), 'v2_student');
    }

    public function test_foreign_student_cannot_open_review_or_studio(): void
    {
        $mistake = $this->makeMistake($this->studentId, self::STEM);

        $this->actingAsStudent($this->otherStudentId)
            ->get(route('v2.student.learning_hub.review', $mistake))->assertForbidden();
        $this->actingAsStudent($this->otherStudentId)
            ->get(route('v2.student.learning_hub.studio', $mistake))->assertForbidden();
    }

    public function test_unreleased_mistake_returns_404_for_review_and_studio(): void
    {
        $mistake = $this->makeMistake($this->studentId, self::STEM, released: false);

        $this->actingAsStudent()->get(route('v2.student.learning_hub.review', $mistake))->assertNotFound();
        $this->actingAsStudent()->get(route('v2.student.learning_hub.studio', $mistake))->assertNotFound();
    }

    public function test_hub_list_html_contains_no_options_answers_or_solutions(): void
    {
        $this->makeMistake($this->studentId, self::STEM);

        $response = $this->actingAsStudent()->get(route('v2.student.learning_hub.index'))->assertOk();

        foreach (['OPTION-ALPHA', 'OPTION-BRAVO', 'OPTION-CHARLIE', 'OPTION-DELTA'] as $optionText) {
            $response->assertDontSee($optionText);
        }
        $response->assertDontSee('APPROVED-SOLUTION');
        $response->assertDontSee('DRAFT-NOTES');
    }

    public function test_hub_list_contains_no_image_urls_or_storage_paths(): void
    {
        $this->makeMistake($this->studentId, self::STEM);

        $response = $this->actingAsStudent()->get(route('v2.student.learning_hub.index'))->assertOk();

        $response->assertDontSee('secret-diagram-path');
        $response->assertDontSee('v2/img');
        $response->assertDontSee('v2/questions/');
    }

    public function test_hub_list_uses_short_teaser_never_the_full_stem(): void
    {
        $this->makeMistake($this->studentId, self::STEM);

        $response = $this->actingAsStudent()->get(route('v2.student.learning_hub.index'))->assertOk();

        // The opening of the stem is visible (recognizable teaser)...
        $response->assertSee('A uniform beam of length', false);
        // ...but the tail past the ~100-char teaser never reaches the client.
        $response->assertDontSee('keeping the whole arrangement in equilibrium');
        $response->assertDontSee('during the experiment described below');
    }

    public function test_demo_and_showcase_routes_are_unregistered_outside_local(): void
    {
        // The unauthenticated developer showcases are registered only in the
        // local environment (routes/v2.php) - everywhere else they 404.
        $this->get('/v2/temp/9702-m25-q13')->assertNotFound();
        $this->get('/v2/temp/widget-lab')->assertNotFound();
        $this->get('/v2/learn/solution-demo')->assertNotFound();
        $this->get('/v2/learn')->assertNotFound();
    }

    public function test_studio_payload_contains_only_current_question_and_debug_is_off(): void
    {
        $mine = $this->makeMistake($this->studentId, self::STEM);
        $this->makeMistake($this->otherStudentId, self::OTHER_STEM);

        $response = $this->actingAsStudent()
            ->get(route('v2.student.learning_hub.studio', $mine))
            ->assertOk();

        $html = $response->getContent();

        // Only the current question travels; nothing from any other mistake.
        $this->assertStringContainsString('A uniform beam of length', $html);
        $this->assertStringNotContainsString('SECRET-OTHER-STUDENT-QUESTION', $html);
        // Draft assets never reach a student payload.
        $this->assertStringNotContainsString('DRAFT-NOTES', $html);
        // The raw-JSON inspector is a local developer aid only.
        $this->assertStringContainsString('&quot;debug&quot;:false', $html);
        // The internal numeric id is not exposed - the hashid route key is.
        $this->assertStringContainsString('&quot;id&quot;:&quot;'.$mine->getRouteKey().'&quot;', $html);
    }
}
