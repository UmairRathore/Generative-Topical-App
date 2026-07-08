<?php

namespace Tests\Feature\V2;

use App\Models\V2\AiInteractionLog;
use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorContextItem;
use App\Models\V2\AiTutorMessage;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Student AI Tutor - chat guards, context grounding, persistence and the
 * interaction log. The python-ai service is faked (Http::fake); these tests
 * assert what Laravel SENDS as much as what it stores.
 */
class AiTutorTest extends TestCase
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
        $this->studentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Stu Dent', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeQuestion(string $correct = 'A'): int
    {
        return DB::table('v2_questions')->insertGetId([
            'paper_id' => 1, 'subject_id' => $this->subjectId, 'topic_id' => $this->topicId,
            'question_number' => ++$this->qNum, 'correct_answer' => $correct,
            'question_text' => 'A ball is projected horizontally...',
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

    /** Wrong answer -> real Mistake Bank capture (same path as production). */
    private function makeMistake(bool $released = true, ?int $studentId = null, string $selected = 'B', string $correct = 'A'): StudentMistake
    {
        $studentId ??= $this->studentId;
        $examId = $this->makeExam($released);
        $questionId = $this->makeQuestion($correct);

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
            'attempt_id' => $attemptId, 'question_id' => $questionId, 'selected_option' => $selected,
            'correct_option' => $correct, 'is_correct' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(MistakeBankService::class)->syncFromAttempt(
            \App\Models\V2\ExamAttempt::withoutGlobalScopes()->findOrFail($attemptId)
        );

        return StudentMistake::withoutGlobalScopes()
            ->where('student_id', $studentId)->where('question_id', $questionId)->firstOrFail();
    }

    private function student(?int $id = null): Student
    {
        return Student::withoutGlobalScopes()->findOrFail($id ?? $this->studentId);
    }

    private function actingAsStudent(?int $id = null): static
    {
        return $this->actingAs($this->student($id), 'v2_student');
    }

    private function fakeChatOk(string $answer = 'What does the horizontal velocity do during flight?'): void
    {
        Http::fake([
            self::AI_URL.'/tutor/chat' => Http::response([
                'ok'     => true,
                'answer' => $answer,
                'meta'   => [
                    'provider' => 'openai', 'model' => 'gpt-4o-mini',
                    'input_tokens' => 900, 'output_tokens' => 150, 'total_tokens' => 1050,
                    'cost_usd' => 0.000225, 'latency_ms' => 1200,
                ],
            ]),
        ]);
    }

    private function openChat(StudentMistake $mistake, string $sourceType = 'mistake'): AiTutorChat
    {
        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.open', $mistake), ['source_type' => $sourceType])
            ->assertOk();

        return AiTutorChat::withoutGlobalScopes()
            ->where('student_mistake_id', $mistake->id)->where('source_type', $sourceType)->firstOrFail();
    }

    public function test_foreign_student_gets_403(): void
    {
        $mistake = $this->makeMistake();

        $otherId = DB::table('v2_students')->insertGetId([
            'school_id' => $this->schoolId, 'name' => 'Other Kid', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsStudent($otherId)
            ->postJson(route('v2.student.tutor.open', $mistake), ['source_type' => 'mistake'])
            ->assertForbidden();
    }

    public function test_unreleased_mistake_is_blocked(): void
    {
        $mistake = $this->makeMistake(released: false);

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.open', $mistake), ['source_type' => 'mistake'])
            ->assertNotFound();

        // A pre-existing chat also goes dark if results are (still) unreleased.
        $chat = AiTutorChat::create([
            'student_id' => $this->studentId, 'school_id' => $this->schoolId,
            'source_type' => 'mistake', 'question_id' => $mistake->question_id,
            'student_mistake_id' => $mistake->id, 'subject_id' => $this->subjectId,
        ]);

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.message', $chat), ['message' => 'help'])
            ->assertNotFound();
    }

    public function test_open_is_idempotent_per_source_type(): void
    {
        $this->fakeChatOk();
        $mistake = $this->makeMistake();

        $this->openChat($mistake, 'mistake');
        $this->openChat($mistake, 'mistake');
        $this->assertSame(1, AiTutorChat::withoutGlobalScopes()->count());

        $this->openChat($mistake, 'question');
        $this->assertSame(2, AiTutorChat::withoutGlobalScopes()->count());
    }

    public function test_open_rejects_inactive_source_types_and_missing_grounding(): void
    {
        $mistake = $this->makeMistake();

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.open', $mistake), ['source_type' => 'topic'])
            ->assertStatus(422); // schema supports it; V1 does not activate it

        // No known correct answer anywhere -> the tutor cannot stay grounded.
        DB::table('v2_questions')->where('id', $mistake->question_id)->update(['correct_answer' => null]);
        DB::table('v2_student_mistakes')->where('id', $mistake->id)->update(['correct_option' => null]);

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.open', $mistake), ['source_type' => 'mistake'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_grounding');
    }

    public function test_context_payload_contains_only_allowed_data(): void
    {
        $this->fakeChatOk();
        $mistake = $this->makeMistake();
        $qid = $mistake->question_id;

        // Another student in the same school - must never leak into the prompt.
        DB::table('v2_students')->insert([
            'school_id' => $this->schoolId, 'name' => 'Evil Other', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['A', 'v = u'], ['B', 'v = 2u'], ['C', 'v = u/2'], ['D', 'v = 0']] as $i => [$label, $text]) {
            DB::table('v2_question_options')->insert([
                'question_id' => $qid, 'label' => $label, 'text' => $text, 'sort_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('v2_question_images')->insert([
            'question_id' => $qid, 'image_path' => 'papers/9702/q01_diagram.png',
            'role' => 'question_image_after_text', 'option_label' => null, 'sort_order' => 0,
            'caption' => 'Projectile setup', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Only the approved solution may ground the tutor - the draft must not.
        DB::table('v2_question_learning_assets')->insert([
            ['question_id' => $qid, 'asset_type' => 'worked_solution', 'asset_key' => '', 'content' => 'DRAFT - not reviewed', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('v2_question_learning_assets')->insert([
            ['question_id' => $qid, 'asset_type' => 'worked_solution', 'asset_key' => 'x', 'content' => 'APPROVED step-by-step solution', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $chat = $this->openChat($mistake, 'mistake');

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.message', $chat), ['message' => 'Why is B wrong?'])
            ->assertOk();

        Http::assertSent(function (ClientRequest $request) {
            if (! str_ends_with($request->url(), '/tutor/chat')) {
                return false;
            }
            $data = $request->data();
            $raw = $request->body();
            $context = $data['context'];

            // First name only - no surname, no email, no ids.
            $this->assertSame(['first_name' => 'Stu'], $context['student']);
            $this->assertStringNotContainsString('Dent', $raw);

            // Never another student's data.
            $this->assertStringNotContainsString('Evil', $raw);

            // Only the approved asset grounds the tutor.
            $this->assertSame('APPROVED step-by-step solution', $context['grounding_assets']['worked_solution']['content']);
            $this->assertStringNotContainsString('DRAFT', $raw);

            // Diagram travels as a reference - never a signed URL.
            $ref = $context['question']['diagram_references'][0];
            $this->assertSame('papers/9702/q01_diagram.png', $ref['image_path']);
            $this->assertStringNotContainsString('/v2/img', $raw);
            $this->assertStringNotContainsString('http://python-ai.test/storage', $raw);

            // The answer key + the student's own wrong answer are present.
            $this->assertSame('A', $context['question']['correct_answer']);
            $this->assertSame('B', $context['student_answer']['selected_option']);
            $this->assertCount(4, $context['question']['options']);

            // Internal auth header travels with the request.
            $this->assertSame('test-token', $request->header('X-Internal-Token')[0]);

            return isset($data['message'], $data['history']);
        });
    }

    public function test_question_mode_omits_personal_attempt_data_and_logs_question_context(): void
    {
        $this->fakeChatOk();
        $mistake = $this->makeMistake();
        $chat = $this->openChat($mistake, 'question');

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.message', $chat), ['message' => 'Explain this question'])
            ->assertOk();

        Http::assertSent(function (ClientRequest $request) {
            $context = $request->data()['context'];
            $this->assertArrayNotHasKey('student_answer', $context);
            $this->assertArrayNotHasKey('behaviour', $context);

            return true;
        });

        // source_context mirrors the selected tutor mode.
        $log = AiInteractionLog::latest('id')->firstOrFail();
        $this->assertSame('question', $log->source_context_type);
        $this->assertSame($mistake->question_id, (int) $log->source_context_id);
    }

    public function test_assistant_message_and_context_items_are_saved(): void
    {
        $this->fakeChatOk('Try resolving the velocity into components first.');
        $mistake = $this->makeMistake();
        $chat = $this->openChat($mistake, 'mistake');

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.message', $chat), ['message' => 'Why is B wrong?'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', 'Try resolving the velocity into components first.');

        $messages = AiTutorMessage::where('chat_id', $chat->id)->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('assistant', $messages[1]->role);
        $this->assertSame('gpt-4o-mini', $messages[1]->model);
        $this->assertSame(900, $messages[1]->prompt_tokens);
        $this->assertSame(150, $messages[1]->completion_tokens);

        $items = AiTutorContextItem::where('chat_id', $chat->id)->get();
        $this->assertTrue($items->isNotEmpty());
        $this->assertTrue($items->every(fn ($i) => $i->message_id === $messages[1]->id));
        $this->assertContains('question_stem', $items->pluck('item_type'));

        $this->assertNotNull($chat->fresh()->last_message_at);
    }

    public function test_interaction_log_on_success(): void
    {
        $this->fakeChatOk();
        $mistake = $this->makeMistake();
        $chat = $this->openChat($mistake, 'mistake');

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.message', $chat), ['message' => 'Why is B wrong?'])
            ->assertOk();

        $log = AiInteractionLog::latest('id')->firstOrFail();
        $this->assertSame('tutor_chat', $log->feature);
        $this->assertSame('ok', $log->status);
        $this->assertSame('Student', $log->actor_type);
        $this->assertSame($this->studentId, (int) $log->actor_id);
        $this->assertSame('v2_student', $log->role);
        $this->assertSame('mistake', $log->source_context_type);
        $this->assertSame($mistake->id, (int) $log->source_context_id);
        $this->assertSame('openai', $log->provider);
        $this->assertSame('gpt-4o-mini', $log->model);
        $this->assertSame(1050, (int) $log->total_tokens);
        $this->assertSame($this->schoolId, (int) $log->school_id);
        // Redacted request: a summary, never the full context payload.
        $this->assertArrayNotHasKey('context', $log->request_json);
        $this->assertSame('Why is B wrong?', $log->request_json['message']);
    }

    public function test_interaction_log_and_graceful_json_on_failure(): void
    {
        Http::fake([self::AI_URL.'/*' => Http::response(['ok' => false], 500)]);
        $mistake = $this->makeMistake();
        $chat = $this->openChat($mistake, 'mistake');

        $this->actingAsStudent()
            ->postJson(route('v2.student.tutor.chats.message', $chat), ['message' => 'help me'])
            ->assertStatus(502)
            ->assertJsonPath('ok', false);

        $log = AiInteractionLog::where('feature', 'tutor_chat')->latest('id')->firstOrFail();
        $this->assertSame('error', $log->status);
        $this->assertNotNull($log->error);
        $this->assertNotNull($log->latency_ms);

        // The user's message survives for retry; no assistant row is written.
        $roles = AiTutorMessage::where('chat_id', $chat->id)->pluck('role');
        $this->assertSame(['user'], $roles->all());
    }
}
