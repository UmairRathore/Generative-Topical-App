<?php

namespace Tests\Feature\V2;

use App\Models\V2\ExamAttempt;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Models\V2\StudentMistakeEvent;
use App\Services\V2\MistakeBankService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Learning Hub - Mistake Bank capture, gating, mastery and asset visibility.
 * Uses DatabaseMigrations (not RefreshDatabase) so PRAGMA foreign_keys=OFF holds
 * outside a wrapping transaction, letting us build a minimal graph without every
 * parent row (there are no V2 factories yet).
 */
class LearningHubMistakeBankTest extends TestCase
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

    /** Build a submitted attempt where the student answered $selected to one question. */
    private function attempt(int $examId, int $questionId, string $selected, string $correct, bool $voided = false): ExamAttempt
    {
        $attemptId = DB::table('v2_exam_attempts')->insertGetId([
            'exam_id' => $examId, 'student_id' => $this->studentId, 'school_id' => $this->schoolId,
            'status' => 'submitted', 'total_questions' => 1, 'score' => $selected === $correct ? 1 : 0,
            'started_at' => now(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_exam_questions')->insert([
            'exam_id' => $examId, 'question_id' => $questionId, 'sort_order' => 1, 'marks' => 1,
            'is_voided' => $voided, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_exam_answers')->insert([
            'attempt_id' => $attemptId, 'question_id' => $questionId, 'selected_option' => $selected,
            'correct_option' => $correct, 'is_correct' => $selected === $correct,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ExamAttempt::withoutGlobalScopes()->findOrFail($attemptId);
    }

    private function student(): Student
    {
        return Student::withoutGlobalScopes()->findOrFail($this->studentId);
    }

    public function test_wrong_answer_is_captured_as_a_mistake(): void
    {
        $q = $this->makeQuestion('A');
        $attempt = $this->attempt($this->makeExam(), $q, 'B', 'A');

        $this->assertSame(1, $this->svc->syncFromAttempt($attempt));

        $m = StudentMistake::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->studentId, $m->student_id);
        $this->assertSame($q, $m->question_id);
        $this->assertSame('new', $m->status);
        $this->assertSame(1, $m->mistake_count);
        $this->assertSame('B', $m->selected_option);
        $this->assertSame('A', $m->correct_option);
        $this->assertSame($this->subjectId, $m->subject_id);
        $this->assertDatabaseHas('v2_student_mistake_events', ['student_mistake_id' => $m->id, 'event_type' => 'wrong']);
    }

    public function test_correct_answer_is_not_captured(): void
    {
        $q = $this->makeQuestion('A');
        $this->svc->syncFromAttempt($this->attempt($this->makeExam(), $q, 'A', 'A'));

        $this->assertSame(0, StudentMistake::withoutGlobalScopes()->count());
    }

    public function test_capture_is_idempotent_per_attempt(): void
    {
        $q = $this->makeQuestion('A');
        $attempt = $this->attempt($this->makeExam(), $q, 'C', 'A');

        $this->svc->syncFromAttempt($attempt);
        $this->assertSame(0, $this->svc->syncFromAttempt($attempt)); // re-run records nothing new

        $this->assertSame(1, StudentMistake::withoutGlobalScopes()->count());
        $this->assertSame(1, StudentMistake::withoutGlobalScopes()->firstOrFail()->mistake_count);
        $this->assertSame(1, StudentMistakeEvent::where('event_type', 'wrong')->count());
    }

    public function test_repeat_wrong_on_new_exam_increments_without_duplicating(): void
    {
        $q = $this->makeQuestion('A');
        $this->svc->syncFromAttempt($this->attempt($this->makeExam(), $q, 'B', 'A'));
        $exam2 = $this->makeExam();
        $this->svc->syncFromAttempt($this->attempt($exam2, $q, 'D', 'A'));

        $this->assertSame(1, StudentMistake::withoutGlobalScopes()->count());
        $m = StudentMistake::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(2, $m->mistake_count);
        $this->assertSame($exam2, $m->latest_exam_id);
        $this->assertSame('D', $m->selected_option);
        $this->assertSame(2, StudentMistakeEvent::where('event_type', 'wrong')->count());
    }

    public function test_voided_questions_are_excluded(): void
    {
        $q = $this->makeQuestion('A');
        $this->svc->syncFromAttempt($this->attempt($this->makeExam(), $q, 'B', 'A', voided: true));

        $this->assertSame(0, StudentMistake::withoutGlobalScopes()->count());
    }

    public function test_mistakes_are_hidden_until_the_exam_results_are_released(): void
    {
        $q = $this->makeQuestion('A');
        $exam = $this->makeExam(released: false);
        $this->svc->syncFromAttempt($this->attempt($exam, $q, 'B', 'A'));

        // Captured immediately...
        $this->assertSame(1, StudentMistake::withoutGlobalScopes()->count());
        // ...but not surfaced while results are unreleased.
        $this->assertSame(0, $this->svc->list($this->student())->total());
        $this->assertSame(1, $this->svc->analytics($this->student())['pending']);

        DB::table('v2_exams')->where('id', $exam)->update(['results_released_at' => now()]);

        $this->assertSame(1, $this->svc->list($this->student())->total());
        $this->assertSame(0, $this->svc->analytics($this->student())['pending']);
    }

    public function test_mastered_mistake_is_kept_and_can_be_reopened(): void
    {
        $q = $this->makeQuestion('A');
        $this->svc->syncFromAttempt($this->attempt($this->makeExam(), $q, 'B', 'A'));
        $m = StudentMistake::withoutGlobalScopes()->firstOrFail();

        $this->svc->markMastered($m);
        $m->refresh();
        $this->assertSame('mastered', $m->status);
        $this->assertNotNull($m->mastered_at);
        $this->assertDatabaseHas('v2_student_mistakes', ['id' => $m->id]); // never deleted

        $this->svc->reopen($m);
        $m->refresh();
        $this->assertSame('new', $m->status);
        $this->assertNull($m->mastered_at);
    }

    public function test_getting_a_mastered_question_wrong_again_reopens_it(): void
    {
        $q = $this->makeQuestion('A');
        $this->svc->syncFromAttempt($this->attempt($this->makeExam(), $q, 'B', 'A'));
        $m = StudentMistake::withoutGlobalScopes()->firstOrFail();
        $this->svc->markMastered($m);

        $this->svc->syncFromAttempt($this->attempt($this->makeExam(), $q, 'C', 'A'));
        $m->refresh();

        $this->assertSame('new', $m->status);
        $this->assertSame(2, $m->mistake_count);
        $this->assertNull($m->mastered_at);
        $this->assertSame(1, StudentMistakeEvent::where('event_type', 'reopened')->count());
    }

    public function test_only_visible_assets_are_returned(): void
    {
        $q = $this->makeQuestion('A');

        $asset = QuestionLearningAsset::create([
            'question_id' => $q, 'asset_type' => 'worked_solution', 'content' => 'Step 1...', 'status' => 'draft',
        ]);
        $this->assertNull($this->svc->visibleAsset($q, 'worked_solution')); // draft is hidden

        $asset->update(['status' => 'generated']);
        $this->assertNotNull($this->svc->visibleAsset($q, 'worked_solution')); // now visible

        $this->assertNull($this->svc->visibleAsset($q, 'bogus_type')); // type whitelist
    }
}
