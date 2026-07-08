<?php

namespace Tests\Feature\V2;

use App\Models\V2\AiInteractionLog;
use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorQuiz;
use App\Models\V2\AiTutorQuizAttempt;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AI Tutor mini quizzes - generation validation, scoring, and (critically)
 * the guarantee that quiz attempts never touch official exam tables.
 */
class AiTutorQuizTest extends TestCase
{
    use DatabaseMigrations;

    private const AI_URL = 'http://python-ai.test';

    private int $schoolId;
    private int $subjectId;
    private int $topicId;
    private int $studentId;
    private int $qNum = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        config([
            'services.python_ai.url'   => self::AI_URL,
            'services.python_ai.token' => 'test-token',
        ]);

        $this->schoolId  = DB::table('v2_schools')->insertGetId(['name' => 'Sch', 'contact_email' => 's@s.edu', 'created_at' => now(), 'updated_at' => now()]);
        $this->subjectId = DB::table('v2_subjects')->insertGetId(['name' => 'Physics', 'created_at' => now(), 'updated_at' => now()]);
        $this->topicId   = DB::table('v2_topics')->insertGetId(['subject_id' => $this->subjectId, 'external_id' => '1', 'title' => 'Kinematics', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Stu', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
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
            'question_number' => ++$this->qNum, 'correct_answer' => 'A',
            'question_text' => 'A ball is projected...', 'difficulty' => 'medium', 'year' => 2020,
            'source_paper' => '9702_x_qp_1', 'created_at' => now(), 'updated_at' => now(),
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

        app(MistakeBankService::class)->syncFromAttempt(
            \App\Models\V2\ExamAttempt::withoutGlobalScopes()->findOrFail($attemptId)
        );

        return StudentMistake::withoutGlobalScopes()->where('question_id', $questionId)->firstOrFail();
    }

    private function student(?int $id = null): Student
    {
        return Student::withoutGlobalScopes()->findOrFail($id ?? $this->studentId);
    }

    private function actingAsStudent(?int $id = null): static
    {
        return $this->actingAs($this->student($id), 'v2_student');
    }

    private function validQuizResponse(): array
    {
        return [
            'ok'   => true,
            'quiz' => [
                'title'     => 'Quick check: Kinematics',
                'questions' => [
                    ['stem' => 'Q1?', 'options' => [['label' => 'A', 'text' => '1'], ['label' => 'B', 'text' => '2']], 'correct_option' => 'A', 'explanation' => 'Because.'],
                    ['stem' => 'Q2?', 'options' => [['label' => 'A', 'text' => '1'], ['label' => 'B', 'text' => '2']], 'correct_option' => 'B', 'explanation' => 'Because.'],
                    ['stem' => 'Q3?', 'options' => [['label' => 'A', 'text' => '1'], ['label' => 'B', 'text' => '2']], 'correct_option' => 'A', 'explanation' => 'Because.'],
                    ['stem' => 'Q4?', 'options' => [['label' => 'A', 'text' => '1'], ['label' => 'B', 'text' => '2']], 'correct_option' => 'B', 'explanation' => 'Because.'],
                ],
            ],
            'meta' => [
                'provider' => 'openai', 'model' => 'gpt-4o-mini',
                'input_tokens' => 1200, 'output_tokens' => 400, 'total_tokens' => 1600,
                'cost_usd' => 0.00042, 'latency_ms' => 2000,
            ],
        ];
    }

    private function openChat(StudentMistake $mistake): AiTutorChat
    {
        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.open', $mistake), ['source_type' => 'mistake'])
            ->assertOk();

        return AiTutorChat::withoutGlobalScopes()->where('student_mistake_id', $mistake->id)->firstOrFail();
    }

    public function test_quiz_generated_and_stored_without_leaking_answers(): void
    {
        Http::fake([self::AI_URL.'/tutor/quiz' => Http::response($this->validQuizResponse())]);
        $chat = $this->openChat($this->makeMistake());

        $response = $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.quiz', $chat))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(4, 'quiz.questions');

        // The client payload must not reveal answers before the attempt.
        $this->assertStringNotContainsString('correct_option', $response->getContent());
        $this->assertStringNotContainsString('explanation', $response->getContent());

        $quiz = AiTutorQuiz::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('ready', $quiz->status);
        $this->assertSame($chat->id, $quiz->chat_id);
        $this->assertSame(4, $quiz->questions()->count());

        $log = AiInteractionLog::where('feature', 'tutor_quiz')->firstOrFail();
        $this->assertSame('ok', $log->status);
        $this->assertSame('mistake', $log->source_context_type);
    }

    public function test_invalid_quiz_payload_is_rejected_and_logged(): void
    {
        $bad = $this->validQuizResponse();
        $bad['quiz']['questions'] = array_slice($bad['quiz']['questions'], 0, 2); // < 3 questions

        Http::fake([self::AI_URL.'/tutor/quiz' => Http::response($bad)]);
        $chat = $this->openChat($this->makeMistake());

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.quiz', $chat))
            ->assertStatus(502)
            ->assertJsonPath('ok', false);

        $this->assertSame(0, AiTutorQuiz::withoutGlobalScopes()->count());
        $this->assertSame('error', AiInteractionLog::where('feature', 'tutor_quiz')->firstOrFail()->status);
    }

    public function test_attempt_is_scored_saved_and_never_touches_exam_tables(): void
    {
        Http::fake([self::AI_URL.'/tutor/quiz' => Http::response($this->validQuizResponse())]);
        $chat = $this->openChat($this->makeMistake());

        $this->actingAsStudent()->postJson(route('v2.student.tutor.chats.quiz', $chat))->assertOk();
        $quiz = AiTutorQuiz::withoutGlobalScopes()->firstOrFail();
        $qs = $quiz->questions;

        $examTables = ['v2_exam_attempts', 'v2_exam_answers', 'v2_exam_questions', 'v2_exams'];
        $before = collect($examTables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

        // 2 right (A, B), 2 wrong.
        $answers = [
            $qs[0]->id => 'A', // correct
            $qs[1]->id => 'B', // correct
            $qs[2]->id => 'B', // wrong
            $qs[3]->id => 'A', // wrong
        ];

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.quizzes.attempt', $quiz), ['answers' => $answers])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('score', 2)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('results.0.correct', true)
            ->assertJsonPath('results.2.correct', false)
            ->assertJsonPath('results.2.correct_option', 'A');

        $attempt = AiTutorQuizAttempt::firstOrFail();
        $this->assertSame(2, $attempt->score);
        $this->assertSame(4, $attempt->total);
        $this->assertSame($this->studentId, (int) $attempt->student_id);
        $this->assertSame('attempted', $quiz->fresh()->status);

        // Official exam tables are untouched by the whole quiz flow.
        foreach ($examTables as $t) {
            $this->assertSame($before[$t], DB::table($t)->count(), "$t must not change");
        }

        // Second attempt is rejected.
        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.quizzes.attempt', $quiz), ['answers' => $answers])
            ->assertStatus(409);
    }

    public function test_foreign_student_cannot_attempt_quiz(): void
    {
        Http::fake([self::AI_URL.'/tutor/quiz' => Http::response($this->validQuizResponse())]);
        $chat = $this->openChat($this->makeMistake());
        $this->actingAsStudent()->postJson(route('v2.student.tutor.chats.quiz', $chat))->assertOk();
        $quiz = AiTutorQuiz::withoutGlobalScopes()->firstOrFail();

        $otherId = DB::table('v2_students')->insertGetId([
            'school_id' => $this->schoolId, 'name' => 'Other Kid', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsStudent($otherId)
            ->postJson(route('v2.student.tutor.quizzes.attempt', $quiz), ['answers' => [1 => 'A']])
            ->assertForbidden();
    }
}
