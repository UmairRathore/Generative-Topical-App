<?php

namespace Tests\Feature\V2;

use App\Models\V2\AuthoringReviewFeedback;
use App\Models\V2\ReviewSignoff;
use App\Models\V2\SuperAdmin;
use App\Models\V2\SyllabusSource;
use App\Services\V2\AuthoringArtifactCatalog;
use App\Services\V2\AuthoringArtifactReader;
use App\Services\V2\StageBLessonValidator;
use App\Services\V2\StageBReviewService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Super Admin Authoring Review Studio boundaries: route authorization, safe
 * artifact-id resolution (no browser-supplied paths), correct-answer isolation
 * in the student-preview payload, exact-SHA approval through the shared
 * service (web + CLI), additive Needs Revision feedback, and stale detection
 * when the lesson artifact changes.
 */
class AuthoringReviewStudioTest extends TestCase
{
    use DatabaseMigrations;

    private string $root;

    private string $artifactId = 'stage_b_test_artifact';

    private array $packet;

    private array $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        $this->withoutVite();

        $this->root = storage_path('framework/testing/authoring_root_'.uniqid());
        File::ensureDirectoryExists($this->root.'/'.$this->artifactId);

        $this->packet = $this->packetFixture();
        $plan = ['plan_schema' => 'topicaled.stage-a-plan.v2', 'target_los' => ['1.2 #7'], 'phases' => [['title' => 'Phase One']]];

        $dir = $this->root.'/'.$this->artifactId;
        file_put_contents($dir.'/packet.json', json_encode($this->packet));
        file_put_contents($dir.'/approved_stage_a_plan.json', json_encode($plan));
        $this->lesson = $this->lessonFixture(
            $this->packet,
            hash_file('sha256', $dir.'/packet.json'),
            hash_file('sha256', $dir.'/approved_stage_a_plan.json'),
        );
        file_put_contents($dir.'/revised_lesson.json', json_encode($this->lesson));
        file_put_contents($dir.'/metadata.json', json_encode([
            'stage' => 'stage_b', 'generated_at' => '2026-07-11T10:00:00+05:00',
            'authoring_mode' => 'offline_claude_code', 'model' => 'claude-fable-5',
        ]));

        $this->app->bind(AuthoringArtifactReader::class,
            fn () => new AuthoringArtifactReader(new StageBLessonValidator, $this->root));

        // VALID foundation + Stage A plan approvals for the fixture hashes.
        $source = SyllabusSource::create([
            'subject_id' => 1,
            'syllabus_code' => '5054', 'version_label' => '2026-2028', 'title' => 'Physics 5054',
            'source_pdf_path' => 'x.pdf', 'source_pdf_sha256' => str_repeat('a', 64),
        ]);
        $fingerprint = ReviewSignoff::foundationFingerprint($this->packet);
        $foundationScope = ReviewSignoff::scopeFor($this->packet);
        $planScope = 'stage-a-plan:'.substr($foundationScope, strlen('stage-a-foundation:'));
        $base = ['syllabus_source_id' => $source->id, 'reviewer' => 'human', 'packet_sha256' => 'x', 'signed_at' => now()];
        ReviewSignoff::create($base + ['scope' => $foundationScope, 'foundation_fingerprint' => $fingerprint]);
        ReviewSignoff::create($base + [
            'scope' => $planScope, 'foundation_fingerprint' => $fingerprint,
            'plan_sha256' => hash('sha256', json_encode($plan)),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function packetFixture(): array
    {
        return [
            'identity' => [
                'subject' => 'Physics', 'syllabus_code' => '5054', 'level' => 'O Level',
                'syllabus_version' => '2026-2028', 'source_pdf_sha256' => str_repeat('a', 64),
                'leaf_section' => ['section_code' => '1.2', 'title' => 'Motion'],
            ],
            'target_coverage' => ['objectives' => [['number' => 7, 'reference' => '1.2 #7', 'requirements' => []]]],
            'out_of_scope_curriculum' => ['objectives' => []],
            'allowed_reference_handles' => ['direct' => ['term:distance_time_graph'], 'supporting' => ['definition:speed']],
            'hard_constraints' => ['exclusions' => [], 'depth' => [], 'conditions' => []],
            'policy_slices' => ['command_words' => ['items' => [['key' => 'Determine', 'text' => 'establish']]]],
            'pedagogical_evidence' => ['misconceptions' => [], 'allowed_misconception_ids' => []],
            'relevant_widgets' => ['widgets' => [['key' => 'graph_explorer', 'compatibility_verdict' => 'compatible']]],
            'assessment_evidence' => ['allowed_evidence_ids' => [101], 'questions' => [[
                'evidence_id' => 101, 'question' => 'X Q1', 'year' => 2025,
                'classification' => 'compatible_prior_cycle',
                'compatibility' => ['scope' => '1.2', 'decision' => 'd#1'],
                'stem' => 'irrelevant stem text for this fixture, kept well over forty characters long.',
                'correct_answer_server_only' => 'B',
            ]]],
        ];
    }

    private function lessonFixture(array $packet, string $packetSha = 'unbound', string $planSha = 'unbound'): array
    {
        return [
            'lesson_schema' => StageBLessonValidator::LESSON_SCHEMA,
            'identity' => [
                'subject' => 'Physics', 'syllabus_code' => '5054', 'level' => 'O Level',
                'syllabus_version' => '2026-2028', 'source_pdf_sha256' => str_repeat('a', 64),
                'leaf_section' => ['section_code' => '1.2', 'title' => 'Motion'],
                'lesson_title' => 'Fixture lesson', 'target_los' => ['1.2 #7'],
            ],
            'bindings' => [
                'packet_sha256' => $packetSha,
                'stage_a_plan_sha256' => $planSha,
                'foundation_fingerprint' => ReviewSignoff::foundationFingerprint($packet),
                'authoring' => ['mode' => 'offline_claude_code', 'model' => 'claude-fable-5'],
            ],
            'status' => ['canonical' => false, 'published' => false, 'review_state' => 'pending_human_review'],
            'conceptual_spine' => 'spine',
            'phases' => [[
                'phase_id' => 'p1', 'stage_a_phase_title' => 'Phase One', 'title' => 'Phase One',
                'advances_los' => ['1.2 #7'],
                'direct_reference_handles' => [], 'supporting_reference_handles' => [],
                'blocks' => [[
                    'block_id' => 'p1-b1', 'type' => 'formative_check', 'check_id' => 'chk-1',
                    'command_word' => 'Determine', 'prompt_md' => 'Pick one.',
                    'response_type' => 'single_choice',
                    'options' => [['id' => 'A', 'label_md' => 'first'], ['id' => 'B', 'label_md' => 'second']],
                    'target_lo_refs' => ['1.2 #7'],
                    'evidence_question_ids' => [101],
                    'diagnostic_intent' => 'd',
                    'grounding_reference_handles' => ['term:distance_time_graph'],
                    'evaluation' => ['server_only' => true, 'mode' => 'auto', 'data' => [
                        'correct_option' => 'B', 'feedback_correct_md' => 'yes', 'feedback_incorrect_md' => 'no',
                    ]],
                ]],
            ]],
        ];
    }

    private function admin(): SuperAdmin
    {
        return SuperAdmin::create([
            'name' => 'Root Admin', 'email' => 'root@topicaled.test',
            'password' => 'secret-password', 'must_change_password' => false,
        ]);
    }

    private function rewriteLesson(callable $mutate): void
    {
        $mutate($this->lesson);
        file_put_contents($this->root.'/'.$this->artifactId.'/revised_lesson.json', json_encode($this->lesson));
    }

    // ── Authorization ──────────────────────────────────────────────────────

    public function test_guest_cannot_access_authoring_review_routes(): void
    {
        $this->get(route('v2.super_admin.authoring.index'))->assertRedirect();
        $this->get(route('v2.super_admin.authoring.review', $this->artifactId))->assertRedirect();
        $this->post(route('v2.super_admin.authoring.approve', $this->artifactId), ['lesson_sha256' => str_repeat('a', 64)])->assertRedirect();
    }

    public function test_super_admin_sees_queue_and_review_screen(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'v2_super_admin')
            ->get(route('v2.super_admin.authoring.index'))
            ->assertOk()
            ->assertSee('Fixture lesson');

        $this->actingAs($admin, 'v2_super_admin')
            ->get(route('v2.super_admin.authoring.review', $this->artifactId))
            ->assertOk();
    }

    // ── Safe artifact resolution ───────────────────────────────────────────

    public function test_unsafe_artifact_ids_cannot_escape_the_authoring_root(): void
    {
        $reader = $this->app->make(AuthoringArtifactReader::class);

        foreach (['../secrets', '..', 'a/../../b', "bad\0id", '/etc/passwd'] as $bad) {
            try {
                $reader->resolveDirectory($bad);
                $this->fail("Artifact id '{$bad}' was not rejected.");
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $admin = $this->admin();
        $this->actingAs($admin, 'v2_super_admin')
            ->get(route('v2.super_admin.authoring.review', 'no_such_artifact'))
            ->assertNotFound();
    }

    public function test_malformed_metadata_and_unsupported_schema_are_rejected(): void
    {
        file_put_contents($this->root.'/'.$this->artifactId.'/metadata.json', '{not json');
        $catalog = new AuthoringArtifactCatalog($this->app->make(AuthoringArtifactReader::class));
        $index = $catalog->index();
        $this->assertSame([], $index['artifacts']);
        $this->assertSame($this->artifactId, $index['skipped'][0]['id']);

        file_put_contents($this->root.'/'.$this->artifactId.'/metadata.json', json_encode(['stage' => 'stage_b']));
        $this->rewriteLesson(function (array &$lesson) {
            $lesson['lesson_schema'] = 'topicaled.stage-b-lesson.v999';
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported lesson schema');
        $this->app->make(AuthoringArtifactReader::class)->load($this->artifactId);
    }

    // ── Correct-answer isolation ───────────────────────────────────────────

    public function test_student_preview_payload_contains_no_answer_or_evaluation_data(): void
    {
        $reader = $this->app->make(AuthoringArtifactReader::class);
        $artifact = $reader->load($this->artifactId);
        $studentJson = json_encode($reader->studentLesson($artifact['lesson']));

        $this->assertStringNotContainsString('correct_option', $studentJson);
        $this->assertStringNotContainsString('feedback_correct_md', $studentJson);
        $this->assertStringNotContainsString('"data"', $studentJson);
        // The evaluation envelope keeps only its mode + server_only marker.
        $this->assertStringContainsString('"mode":"auto"', $studentJson);

        // Reviewer payload (Super Admin-gated route) does carry it, keyed by check.
        $reviewerData = $reader->reviewerData($artifact);
        $this->assertSame('B', $reviewerData['evaluations']['chk-1']['data']['correct_option']);
    }

    // ── Approval ───────────────────────────────────────────────────────────

    public function test_exact_sha_approval_succeeds_and_binds_authenticated_reviewer(): void
    {
        $service = $this->app->make(StageBReviewService::class);
        $artifact = $this->app->make(AuthoringArtifactReader::class)->load($this->artifactId);

        $signoff = $service->approveExact($this->artifactId, $artifact['hashes']['lesson_sha256'], 'Root Admin <root@topicaled.test>');

        $this->assertSame('stage-b-lesson:5054@2026-2028:1.2:7', $signoff->scope);
        $this->assertSame($artifact['hashes']['lesson_sha256'], $signoff->lesson_sha256);
        $this->assertSame('Root Admin <root@topicaled.test>', $signoff->reviewer);

        $reloaded = $this->app->make(AuthoringArtifactReader::class)->load($this->artifactId);
        $this->assertSame('valid', $reloaded['approvals']['lesson']['status']);

        // Approving the same exact artifact again is refused.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already approved');
        $service->approveExact($this->artifactId, $artifact['hashes']['lesson_sha256'], 'Root Admin <root@topicaled.test>');
    }

    public function test_sha_mismatch_and_validation_blockers_refuse_approval(): void
    {
        $service = $this->app->make(StageBReviewService::class);

        try {
            $service->approveExact($this->artifactId, str_repeat('f', 64), 'Root Admin');
            $this->fail('SHA mismatch was not rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('changed since it was loaded', $e->getMessage());
        }

        // Introduce a blocking violation (canonical=true) → approval refused.
        $this->rewriteLesson(function (array &$lesson) {
            $lesson['status']['canonical'] = true;
        });
        $sha = $this->app->make(AuthoringArtifactReader::class)->load($this->artifactId)['hashes']['lesson_sha256'];
        try {
            $service->approveExact($this->artifactId, $sha, 'Root Admin');
            $this->fail('Blocking violations did not refuse approval.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('blocking violations', $e->getMessage());
        }
    }

    public function test_stale_foundation_or_plan_refuses_stage_b_approval(): void
    {
        // Change the packet on disk: foundation fingerprint recorded in the
        // approvals no longer matches → foundation gate not VALID.
        $packet = $this->packet;
        $packet['allowed_reference_handles']['direct'][] = 'term:new_truth';
        file_put_contents($this->root.'/'.$this->artifactId.'/packet.json', json_encode($packet));

        $reader = $this->app->make(AuthoringArtifactReader::class);
        $artifact = $reader->load($this->artifactId);
        $this->assertSame('stale', $artifact['approvals']['foundation']['status']);

        $this->expectException(RuntimeException::class);
        $this->app->make(StageBReviewService::class)
            ->approveExact($this->artifactId, $artifact['hashes']['lesson_sha256'], 'Root Admin');
    }

    public function test_lesson_edit_stales_only_the_stage_b_approval(): void
    {
        $service = $this->app->make(StageBReviewService::class);
        $reader = $this->app->make(AuthoringArtifactReader::class);
        $service->approveExact($this->artifactId, $reader->load($this->artifactId)['hashes']['lesson_sha256'], 'Root Admin');

        $this->rewriteLesson(function (array &$lesson) {
            $lesson['phases'][0]['blocks'][0]['prompt_md'] = 'Pick one, edited.';
        });

        $artifact = $reader->load($this->artifactId);
        $this->assertSame('stale', $artifact['approvals']['lesson']['status']);
        $this->assertSame('lesson_changed', $artifact['approvals']['lesson']['reason']);
        $this->assertSame('valid', $artifact['approvals']['foundation']['status']);
        $this->assertSame('valid', $artifact['approvals']['plan']['status']);
    }

    public function test_web_approval_records_the_authenticated_super_admin_not_client_input(): void
    {
        $admin = $this->admin();
        $sha = $this->app->make(AuthoringArtifactReader::class)->load($this->artifactId)['hashes']['lesson_sha256'];

        $this->actingAs($admin, 'v2_super_admin')
            ->post(route('v2.super_admin.authoring.approve', $this->artifactId), [
                'lesson_sha256' => $sha,
                'reviewer' => 'umair-spoofed', // must be ignored
            ])->assertSessionHasNoErrors();

        $signoff = ReviewSignoff::where('scope', 'like', 'stage-b-lesson:%')->latest('id')->first();
        $this->assertSame('Root Admin <root@topicaled.test>', $signoff->reviewer);
        $this->assertStringNotContainsString('spoofed', $signoff->reviewer);
    }

    // ── Needs Revision feedback ────────────────────────────────────────────

    public function test_revision_feedback_is_additive_and_never_mutates_the_artifact(): void
    {
        $service = $this->app->make(StageBReviewService::class);
        $before = hash_file('sha256', $this->root.'/'.$this->artifactId.'/revised_lesson.json');

        $service->requestRevision($this->artifactId, 'Root Admin <root@topicaled.test>', [
            'severity' => 'major', 'overall_note' => 'Phase pacing is off.',
            'items' => [[
                'severity' => 'minor', 'category' => 'formative_check',
                'phase_id' => 'p1', 'block_id' => 'p1-b1', 'note' => 'Distractor B is too obvious.',
            ]],
        ]);
        $service->requestRevision($this->artifactId, 'Root Admin <root@topicaled.test>', [
            'severity' => 'minor', 'overall_note' => 'Second opinion round.',
        ]);

        $this->assertSame(2, AuthoringReviewFeedback::count());
        $this->assertSame($before, hash_file('sha256', $this->root.'/'.$this->artifactId.'/revised_lesson.json'));

        $first = AuthoringReviewFeedback::orderBy('id')->first();
        $this->assertSame('p1-b1', $first->items[0]['block_id']);
        $this->assertSame($before, $first->lesson_sha256);

        // History surfaces both decisions in order.
        $artifact = $this->app->make(AuthoringArtifactReader::class)->load($this->artifactId);
        $events = array_column($this->app->make(AuthoringArtifactReader::class)->history($artifact), 'event');
        $this->assertSame(2, count(array_keys($events, 'revision_requested', true)));
    }

    public function test_revision_feedback_rejects_invalid_phase_or_block_targets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in the lesson');
        $this->app->make(StageBReviewService::class)->requestRevision($this->artifactId, 'Root Admin', [
            'severity' => 'minor', 'overall_note' => 'note',
            'items' => [['severity' => 'minor', 'category' => 'pedagogy', 'block_id' => 'p9-b9', 'note' => 'x']],
        ]);
    }

    // ── CLI seam ───────────────────────────────────────────────────────────

    public function test_cli_verify_and_approve_share_the_service(): void
    {
        $this->artisan('v2:approve-stage-b-lesson', ['artifact' => $this->artifactId, '--verify' => true])
            ->assertExitCode(1);

        $this->artisan('v2:approve-stage-b-lesson', ['artifact' => $this->artifactId, '--reviewer' => 'human-cli'])
            ->assertExitCode(0);

        $this->artisan('v2:approve-stage-b-lesson', ['artifact' => $this->artifactId, '--verify' => true])
            ->assertExitCode(0);

        $this->rewriteLesson(function (array &$lesson) {
            $lesson['conceptual_spine'] = 'edited spine';
        });
        $this->artisan('v2:approve-stage-b-lesson', ['artifact' => $this->artifactId, '--verify' => true])
            ->assertExitCode(1);
    }
}
