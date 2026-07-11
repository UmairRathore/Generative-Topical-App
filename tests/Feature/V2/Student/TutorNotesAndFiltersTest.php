<?php

namespace Tests\Feature\V2\Student;

use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorMessage;
use App\Models\V2\ExamAttempt;
use App\Models\V2\NotesPage;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Finish-V1: saving an AI Tutor answer into Notes (ownership/anchor/role
 * gated, stored as an ai-origin markdown block, never a source-of-truth
 * record) and the Learning Hub AI-engagement filters (status only).
 */
class TutorNotesAndFiltersTest extends TestCase
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

    private function makeMistake(int $studentId, bool $released = true): StudentMistake
    {
        $questionId = DB::table('v2_questions')->insertGetId([
            'paper_id' => 1, 'subject_id' => $this->subjectId, 'topic_id' => $this->topicId,
            'question_number' => ++$this->qNum, 'correct_answer' => 'A', 'question_text' => 'Q?',
            'difficulty' => 'medium', 'year' => 2020, 'source_paper' => '9702_x_qp_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $examId = DB::table('v2_exams')->insertGetId([
            'school_id' => $this->schoolId, 'class_id' => 1, 'subject_id' => $this->subjectId, 'created_by' => 1,
            'title' => 'Test', 'question_count' => 1, 'status' => 'released', 'published_at' => now(),
            'results_released_at' => $released ? now() : null, 'created_at' => now(), 'updated_at' => now(),
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

    /** A chat + one assistant message anchored to $mistake, owned by $studentId. */
    private function assistantMessage(StudentMistake $mistake, int $studentId, string $content = 'Here is a hint.'): AiTutorMessage
    {
        $chat = AiTutorChat::create([
            'student_id' => $studentId, 'school_id' => $this->schoolId, 'source_type' => 'mistake',
            'question_id' => $mistake->question_id, 'student_mistake_id' => $mistake->id, 'subject_id' => $this->subjectId,
        ]);

        return $chat->messages()->create(['role' => 'assistant', 'content' => $content, 'model' => 'gpt-4o-mini']);
    }

    private function makePage(): NotesPage
    {
        $this->actingAsStudent()->postJson(route('v2.student.notes.pages.store'))->assertOk();

        return NotesPage::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /* ============================ Save to Notes ============================ */

    public function test_student_can_save_their_ai_answer_to_notes(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $message = $this->assistantMessage($mistake, $this->studentId, 'The speed after 2s is 20 m/s because...');
        $page = $this->makePage();

        $this->actingAsStudent()
            ->postJson(route('v2.student.notes.pages.import', $page), [
                'source' => 'ai_answer', 'mistake_id' => $mistake->getRouteKey(), 'ai_message_id' => $message->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $doc = NotesPage::withoutGlobalScopes()->findOrFail($page->id)->document_json;
        $block = collect($doc)->firstWhere('type', 'asset_snapshot');
        $this->assertNotNull($block, 'an asset_snapshot block was inserted');
        $this->assertSame('ai_answer', $block['props']['assetType']);
        $this->assertSame('ai', $block['props']['origin']);
        $this->assertStringContainsString('20 m/s', $block['props']['markdown']);
    }

    public function test_cannot_save_another_students_ai_answer(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $foreignMistake = $this->makeMistake($this->otherStudentId);
        $foreignMessage = $this->assistantMessage($foreignMistake, $this->otherStudentId, 'SECRET other-student answer');
        $page = $this->makePage();

        // Anchor is my own mistake, but the message belongs to another student -> rejected.
        $this->actingAsStudent()
            ->postJson(route('v2.student.notes.pages.import', $page), [
                'source' => 'ai_answer', 'mistake_id' => $mistake->getRouteKey(), 'ai_message_id' => $foreignMessage->id,
            ])
            ->assertStatus(404);

        $this->assertStringNotContainsString('SECRET', json_encode(NotesPage::withoutGlobalScopes()->findOrFail($page->id)->document_json));
    }

    public function test_cannot_save_a_user_message_as_an_ai_answer(): void
    {
        $mistake = $this->makeMistake($this->studentId);
        $chat = AiTutorChat::create([
            'student_id' => $this->studentId, 'school_id' => $this->schoolId, 'source_type' => 'mistake',
            'question_id' => $mistake->question_id, 'student_mistake_id' => $mistake->id, 'subject_id' => $this->subjectId,
        ]);
        $userMessage = $chat->messages()->create(['role' => 'user', 'content' => 'my own question']);
        $page = $this->makePage();

        $this->actingAsStudent()
            ->postJson(route('v2.student.notes.pages.import', $page), [
                'source' => 'ai_answer', 'mistake_id' => $mistake->getRouteKey(), 'ai_message_id' => $userMessage->id,
            ])
            ->assertStatus(404);
    }

    /* ============================ AI filters ============================ */

    public function test_ai_engagement_filters_select_the_right_mistakes(): void
    {
        // (1) discussed with AI, (2) has an attempted quiz with a low score, (3) never discussed.
        $withChat = $this->makeMistake($this->studentId);
        $this->assistantMessage($withChat, $this->studentId);

        $withQuiz = $this->makeMistake($this->studentId);
        $quizChat = AiTutorChat::create([
            'student_id' => $this->studentId, 'school_id' => $this->schoolId, 'source_type' => 'mistake',
            'question_id' => $withQuiz->question_id, 'student_mistake_id' => $withQuiz->id, 'subject_id' => $this->subjectId,
        ]);
        $quiz = $quizChat->quizzes()->create([
            'student_id' => $this->studentId, 'school_id' => $this->schoolId,
            'question_id' => $withQuiz->question_id, 'student_mistake_id' => $withQuiz->id, 'status' => 'attempted',
        ]);
        $quiz->attempts()->create([
            'student_id' => $this->studentId, 'school_id' => $this->schoolId,
            'answers' => [], 'score' => 1, 'total' => 4, 'submitted_at' => now(),
        ]);

        $this->makeMistake($this->studentId); // untouched by AI

        $ids = fn (string $filter) => $this->svc->list($this->student(), ['filter' => $filter])
            ->pluck('id')->sort()->values()->all();

        // Discussed = has at least one message (only $withChat qualifies; the
        // quiz chat has a quiz but no messages).
        $this->assertEqualsCanonicalizing([$withChat->id], $ids('ai_discussed'));
        $this->assertEqualsCanonicalizing([$withQuiz->id], $ids('ai_has_quiz'));
        $this->assertEqualsCanonicalizing([$withQuiz->id], $ids('ai_low_quiz'));
        // Not-discussed = every mistake without any chat message.
        $this->assertContains($withQuiz->id, $ids('ai_not_discussed'));
        $this->assertNotContains($withChat->id, $ids('ai_not_discussed'));
    }
}
