<?php

namespace Tests\Feature\V2\Student;

use App\Models\V2\ExamAttempt;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The full-question surfaces (review + studio) sit behind the mistake-view
 * limiter so a student session can't bulk-iterate its whole bank. The tests
 * re-register the named limiter with a tiny limit - what's under test is the
 * wiring (route -> limiter -> 429), not the production numbers.
 */
class LearningHubThrottleTest extends TestCase
{
    use DatabaseMigrations;

    private int $schoolId;
    private int $subjectId;
    private int $topicId;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        $this->schoolId  = DB::table('v2_schools')->insertGetId(['name' => 'Sch', 'contact_email' => 's@s.edu', 'created_at' => now(), 'updated_at' => now()]);
        $this->subjectId = DB::table('v2_subjects')->insertGetId(['name' => 'Physics', 'created_at' => now(), 'updated_at' => now()]);
        $this->topicId   = DB::table('v2_topics')->insertGetId(['subject_id' => $this->subjectId, 'external_id' => '1', 'title' => 'Kinematics', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Stu', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);

        // Tiny limit so the test proves the wiring in 3 requests.
        RateLimiter::for('mistake-view', fn () => Limit::perMinute(2)->by('test-key'));
    }

    private function makeMistake(): StudentMistake
    {
        $examId = DB::table('v2_exams')->insertGetId([
            'school_id' => $this->schoolId, 'class_id' => 1, 'subject_id' => $this->subjectId, 'created_by' => 1,
            'title' => 'Test', 'question_count' => 1, 'status' => 'released', 'published_at' => now(),
            'results_released_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $questionId = DB::table('v2_questions')->insertGetId([
            'paper_id' => 1, 'subject_id' => $this->subjectId, 'topic_id' => $this->topicId,
            'question_number' => 1, 'correct_answer' => 'A', 'question_text' => 'Throttle probe question?',
            'difficulty' => 'medium', 'year' => 2020, 'source_paper' => '5054_x_qp_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $attemptId = DB::table('v2_exam_attempts')->insertGetId([
            'exam_id' => $examId, 'student_id' => $this->studentId, 'school_id' => $this->schoolId,
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

        app(MistakeBankService::class)->syncFromAttempt(ExamAttempt::withoutGlobalScopes()->findOrFail($attemptId));

        return StudentMistake::withoutGlobalScopes()->firstOrFail();
    }

    private function actingAsStudent(): static
    {
        return $this->actingAs(Student::withoutGlobalScopes()->findOrFail($this->studentId), 'v2_student');
    }

    public function test_review_requests_throttle_after_limit(): void
    {
        $mistake = $this->makeMistake();
        $url = route('v2.student.learning_hub.review', $mistake);

        $this->actingAsStudent()->get($url)->assertOk();
        $this->actingAsStudent()->get($url)->assertOk();
        $this->actingAsStudent()->get($url)->assertStatus(429);
    }

    public function test_studio_requests_throttle_after_limit(): void
    {
        $mistake = $this->makeMistake();
        $url = route('v2.student.learning_hub.studio', $mistake);

        $this->actingAsStudent()->get($url)->assertOk();
        $this->actingAsStudent()->get($url)->assertOk();
        $this->actingAsStudent()->get($url)->assertStatus(429);
    }
}
