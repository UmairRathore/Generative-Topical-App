<?php

namespace Tests\Feature\V2;

use App\Models\V2\ReviewSignoff;
use App\Models\V2\SyllabusSource;
use App\Services\V2\StageAPlanValidator;
use App\Services\V2\StageBLessonValidator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Deterministic Stage B lesson validation: schema/identity/binding checks,
 * reference-handle discipline, widget config validation, formative-check
 * answer isolation, target coverage, plan phase anchoring, and the
 * approval-gate checks (foundation + Stage A plan must be VALID). Stage A
 * validation is untouched - these rules are additive.
 */
class StageBLessonValidatorTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
    }

    private function packet(): array
    {
        return [
            'identity' => [
                'subject' => 'Physics', 'syllabus_code' => '5054', 'level' => 'O Level',
                'syllabus_version' => '2026-2028', 'source_pdf_sha256' => str_repeat('a', 64),
                'leaf_section' => ['section_code' => '1.2', 'title' => 'Motion'],
            ],
            'target_coverage' => ['objectives' => [
                ['number' => 7, 'reference' => '1.2 #7', 'requirements' => []],
            ]],
            'out_of_scope_curriculum' => ['objectives' => [['number' => 1, 'text' => 'Define speed', 'status' => 'validated']]],
            'direct_academic_truth' => ['terminology' => [], 'quantities' => [], 'definitions' => [], 'relationships' => [], 'constants' => []],
            'supporting_academic_truth' => ['references' => []],
            'allowed_reference_handles' => [
                'direct' => ['term:distance_time_graph'],
                'supporting' => ['definition:speed', 'relationship:speed_distance_time'],
            ],
            'hard_constraints' => ['exclusions' => [['text' => 'equations of motion']], 'depth' => [], 'conditions' => []],
            'policy_slices' => ['command_words' => ['items' => [['key' => 'Determine', 'text' => 'establish an answer']]]],
            'pedagogical_evidence' => [
                'misconceptions' => [['id' => 'misc.x', 'priority' => 'high', 'statement' => 's']],
                'allowed_misconception_ids' => ['misc.x'],
            ],
            'relevant_widgets' => ['widgets' => [
                ['key' => 'graph_explorer', 'compatibility_verdict' => 'compatible'],
                ['key' => 'wave_tank', 'compatibility_verdict' => 'incompatible'],
            ]],
            'assessment_evidence' => [
                'allowed_evidence_ids' => [101],
                'questions' => [[
                    'evidence_id' => 101, 'question' => 'X Q1', 'year' => 2025,
                    'classification' => 'compatible_prior_cycle',
                    'compatibility' => ['scope' => '1.2', 'decision' => 'evidence-compat#1@x'],
                    'stem' => 'The speed-time graph shows part of the journey of a vehicle for a time of ten seconds in total.',
                ]],
            ],
        ];
    }

    private function plan(): array
    {
        return [
            'plan_schema' => 'topicaled.stage-a-plan.v2',
            'target_los' => ['1.2 #7'],
            'phases' => [['title' => 'Phase One']],
        ];
    }

    private function lesson(array $packet): array
    {
        return [
            'lesson_schema' => StageBLessonValidator::LESSON_SCHEMA,
            'identity' => [
                'subject' => 'Physics', 'syllabus_code' => '5054', 'level' => 'O Level',
                'syllabus_version' => '2026-2028', 'source_pdf_sha256' => str_repeat('a', 64),
                'leaf_section' => ['section_code' => '1.2', 'title' => 'Motion'],
                'lesson_title' => 'Test lesson',
                'target_los' => ['1.2 #7'],
            ],
            'bindings' => [
                'packet_sha256' => 'packet-sha',
                'stage_a_plan_sha256' => 'plan-sha',
                'foundation_fingerprint' => ReviewSignoff::foundationFingerprint($packet),
                'authoring' => ['mode' => 'offline_claude_code', 'model' => 'claude-fable-5'],
            ],
            'status' => ['canonical' => false, 'published' => false, 'review_state' => 'pending_human_review'],
            'conceptual_spine' => 'height, slope, area',
            'phases' => [[
                'phase_id' => 'p1',
                'stage_a_phase_title' => 'Phase One',
                'title' => 'Phase One realized',
                'advances_los' => ['1.2 #7'],
                'direct_reference_handles' => ['term:distance_time_graph'],
                'supporting_reference_handles' => ['definition:speed'],
                'blocks' => [
                    [
                        'block_id' => 'p1-b1', 'type' => 'explanation',
                        'body_md' => 'Slope carries the state of motion.',
                        'grounding_reference_handles' => ['term:distance_time_graph'],
                    ],
                    [
                        'block_id' => 'p1-b2', 'type' => 'formative_check', 'check_id' => 'chk-1',
                        'command_word' => 'Determine',
                        'prompt_md' => 'Determine the motion in each section.',
                        'response_type' => 'numeric_with_unit',
                        'unit_options' => ['m/s'],
                        'target_lo_refs' => ['1.2 #7'],
                        'evidence_question_ids' => [101],
                        'diagnostic_intent' => 'slope vs height readers',
                        'grounding_reference_handles' => ['term:distance_time_graph'],
                        'evaluation' => ['server_only' => true, 'mode' => 'auto', 'data' => ['value' => 2.5, 'unit' => 'm/s']],
                    ],
                ],
            ]],
        ];
    }

    private function validate(array $lesson, ?array $packet = null, ?array $plan = null, array $context = []): array
    {
        $packet ??= $this->packet();
        $plan ??= $this->plan();
        $context += ['packet_sha256' => 'packet-sha', 'plan_sha256' => 'plan-sha'];

        return (new StageBLessonValidator)->validate($lesson, $packet, $plan, $context);
    }

    public function test_valid_lesson_passes_with_no_blocking(): void
    {
        $result = $this->validate($this->lesson($this->packet()));
        $this->assertSame([], $result['blocking']);
    }

    public function test_unsupported_schema_and_identity_mismatch_block(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['lesson_schema'] = 'topicaled.stage-b-lesson.v999';
        $lesson['identity']['syllabus_version'] = '2023-2025';
        $blocking = $this->validate($lesson)['blocking'];
        $this->assertStringContainsString('lesson_schema', implode(' ', $blocking));
        $this->assertStringContainsString('syllabus_version', implode(' ', $blocking));
    }

    public function test_foundation_fingerprint_and_plan_sha_bindings_block_on_mismatch(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['bindings']['foundation_fingerprint'] = str_repeat('b', 64);
        $lesson['bindings']['stage_a_plan_sha256'] = 'other-plan';
        $text = implode(' ', $this->validate($lesson)['blocking']);
        $this->assertStringContainsString('foundation_fingerprint', $text);
        $this->assertStringContainsString('stage_a_plan_sha256', $text);
    }

    public function test_target_lo_set_must_exactly_match_the_approved_plan(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['identity']['target_los'] = ['1.2 #7', '1.2 #1'];
        $text = implode(' ', $this->validate($lesson)['blocking']);
        $this->assertStringContainsString('does not exactly match', $text);
        $this->assertStringContainsString('out-of-scope sibling', $text);
    }

    public function test_phase_anchoring_must_follow_plan_order(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['phases'][0]['stage_a_phase_title'] = 'Another Phase';
        $this->assertStringContainsString('anchor to the approved Stage A phases',
            implode(' ', $this->validate($lesson)['blocking']));
    }

    public function test_non_canonical_status_is_required(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['status'] = ['canonical' => true, 'published' => false, 'review_state' => 'pending_human_review'];
        $this->assertStringContainsString('never canonical', implode(' ', $this->validate($lesson)['blocking']));
    }

    public function test_unknown_handles_supporting_as_direct_and_unknown_relationship_block(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['phases'][0]['blocks'][0]['grounding_reference_handles'] = ['term:made_up'];
        $lesson['phases'][0]['blocks'][0]['direct_reference_handles'] = ['definition:speed'];
        $lesson['phases'][0]['blocks'][0]['relationships_used'] = ['relationship:suvat'];
        $text = implode(' ', $this->validate($lesson)['blocking']);
        $this->assertStringContainsString('term:made_up', $text);
        $this->assertStringContainsString('as direct', $text);
        $this->assertStringContainsString('relationship:suvat', $text);
    }

    public function test_excluded_content_in_prose_blocks(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['phases'][0]['blocks'][0]['body_md'] = 'Now apply the equations of motion here.';
        $this->assertStringContainsString('excluded content', implode(' ', $this->validate($lesson)['blocking']));
    }

    public function test_widget_rules_unknown_incompatible_and_config_validation(): void
    {
        $packet = $this->packet();
        $widget = [
            'block_id' => 'p1-w', 'type' => 'interactive_widget', 'widget_key' => 'graph_explorer',
            'pedagogical_purpose' => 'p', 'student_instruction' => 'i',
            'expected_observation' => 'o', 'intended_inference' => 'inf',
            'target_lo_refs' => ['1.2 #7'],
            'reference_handles_demonstrated' => ['term:distance_time_graph'],
            'config' => ['curve' => ['type' => 'linear', 'm' => 1], 'modes' => ['gradient'], 'gradientTriangle' => true, 'gradientUnit' => 'm/s'],
        ];

        $lesson = $this->lesson($packet);
        $lesson['phases'][0]['blocks'][] = $widget;
        $this->assertSame([], $this->validate($lesson)['blocking']);

        // Unknown key.
        $lesson['phases'][0]['blocks'][2]['widget_key'] = 'not_a_widget';
        $this->assertStringContainsString('not in the packet shortlist', implode(' ', $this->validate($lesson)['blocking']));

        // Incompatible verdict.
        $lesson['phases'][0]['blocks'][2]['widget_key'] = 'wave_tank';
        $this->assertStringContainsString("verdict 'incompatible'", implode(' ', $this->validate($lesson)['blocking']));

        // gradientTriangle without an explicit unit fails config validation.
        $lesson['phases'][0]['blocks'][2]['widget_key'] = 'graph_explorer';
        unset($lesson['phases'][0]['blocks'][2]['config']['gradientUnit']);
        $this->assertStringContainsString('gradientUnit', implode(' ', $this->validate($lesson)['blocking']));

        // String config is rejected (the JSON-null lesson from Stage A).
        $lesson['phases'][0]['blocks'][2]['config'] = 'null';
        $this->assertStringContainsString('JSON object', implode(' ', $this->validate($lesson)['blocking']));
    }

    public function test_check_answer_isolation_evidence_and_stem_copy_rules(): void
    {
        $lesson = $this->lesson($this->packet());
        $check = &$lesson['phases'][0]['blocks'][1];

        // Answer key outside the evaluation envelope.
        $check['options'] = [['id' => 'A', 'label_md' => 'x', 'correct' => true]];
        $this->assertStringContainsString("options[0] carries 'correct'", implode(' ', $this->validate($lesson)['blocking']));
        unset($check['options']);

        // Missing server_only evaluation.
        $evaluation = $check['evaluation'];
        $check['evaluation'] = ['mode' => 'auto', 'data' => ['value' => 1]];
        $this->assertStringContainsString('server_only=true', implode(' ', $this->validate($lesson)['blocking']));

        // Auto mode without data.
        $check['evaluation'] = ['server_only' => true, 'mode' => 'auto', 'data' => []];
        $this->assertStringContainsString('evaluation.data', implode(' ', $this->validate($lesson)['blocking']));
        $check['evaluation'] = $evaluation;

        // Unknown evidence id.
        $check['evidence_question_ids'] = [999];
        $this->assertStringContainsString('999', implode(' ', $this->validate($lesson)['blocking']));

        // No evidence and no independence reason.
        $check['evidence_question_ids'] = [];
        $this->assertStringContainsString('evidence_independence_reason', implode(' ', $this->validate($lesson)['blocking']));
        $check['evidence_question_ids'] = [101];

        // Copying a full evidence stem into the prompt.
        $check['prompt_md'] = 'Intro. The speed-time graph shows part of the journey of a vehicle for a time of ten seconds in total. What next?';
        $this->assertStringContainsString('evidence must not be copied', implode(' ', $this->validate($lesson)['blocking']));
    }

    public function test_misconception_id_must_exist_or_be_unverified_proposal(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['phases'][0]['blocks'][] = [
            'block_id' => 'p1-m', 'type' => 'misconception_intervention',
            'misconception_id' => 'misc.unknown',
            'trigger_context' => 't', 'incorrect_reasoning' => 'i', 'correction_md' => 'c',
            'grounding_reference_handles' => ['term:distance_time_graph'],
        ];
        $this->assertStringContainsString('misc.unknown', implode(' ', $this->validate($lesson)['blocking']));

        $lesson['phases'][0]['blocks'][2]['misconception_id'] = 'misc.x';
        $this->assertSame([], $this->validate($lesson)['blocking']);

        unset($lesson['phases'][0]['blocks'][2]['misconception_id']);
        $lesson['phases'][0]['blocks'][2]['unverified_proposal'] = 'learners read height as speed';
        $this->assertSame([], $this->validate($lesson)['blocking']);
    }

    public function test_target_coverage_content_blocking_and_check_warning(): void
    {
        $lesson = $this->lesson($this->packet());
        // Remove the check: LO loses both its check (warning) and its only targeting block (blocking).
        array_splice($lesson['phases'][0]['blocks'], 1, 1);
        $result = $this->validate($lesson);
        $this->assertStringContainsString('no authored content coverage', implode(' ', $result['blocking']));

        // With a widget targeting the LO but still no check: content ok, warning remains.
        $lesson['phases'][0]['blocks'][] = [
            'block_id' => 'p1-w', 'type' => 'interactive_widget', 'widget_key' => 'graph_explorer',
            'pedagogical_purpose' => 'p', 'student_instruction' => 'i', 'expected_observation' => 'o',
            'intended_inference' => 'inf', 'target_lo_refs' => ['1.2 #7'],
            'reference_handles_demonstrated' => [], 'config' => ['curve' => ['type' => 'linear', 'm' => 1]],
        ];
        $result = $this->validate($lesson);
        $this->assertSame([], $result['blocking']);
        $this->assertStringContainsString('no formative check', implode(' ', $result['warnings']));
    }

    public function test_all_multiple_choice_checks_warn(): void
    {
        $lesson = $this->lesson($this->packet());
        $lesson['phases'][0]['blocks'][1]['response_type'] = 'single_choice';
        $lesson['phases'][0]['blocks'][1]['options'] = [['id' => 'A', 'label_md' => 'x']];
        $lesson['phases'][0]['blocks'][1]['evaluation']['data'] = ['correct_option' => 'A'];
        $result = $this->validate($lesson);
        $this->assertSame([], $result['blocking']);
        $this->assertStringContainsString('multiple-choice', implode(' ', $result['warnings']));
    }

    public function test_approval_gates_none_and_stale_block_valid_passes(): void
    {
        $packet = $this->packet();
        $lesson = $this->lesson($packet);
        $context = ['verify_approvals' => true];

        // No approvals recorded at all.
        $text = implode(' ', $this->validate($lesson, $packet, null, $context)['blocking']);
        $this->assertStringContainsString('No academic-foundation sign-off', $text);
        $this->assertStringContainsString('No Stage A plan approval', $text);

        $source = SyllabusSource::create([
            'subject_id' => 1,
            'syllabus_code' => '5054', 'version_label' => '2026-2028', 'title' => 'Physics 5054',
            'source_pdf_path' => 'x.pdf', 'source_pdf_sha256' => str_repeat('a', 64),
        ]);
        $fingerprint = ReviewSignoff::foundationFingerprint($packet);
        $foundationScope = ReviewSignoff::scopeFor($packet);
        $planScope = 'stage-a-plan:'.substr($foundationScope, strlen('stage-a-foundation:'));
        $base = [
            'syllabus_source_id' => $source->id, 'reviewer' => 'human',
            'packet_sha256' => 'packet-sha', 'signed_at' => now(),
        ];

        // Stale foundation (old fingerprint) + stale plan sha.
        ReviewSignoff::create($base + ['scope' => $foundationScope, 'foundation_fingerprint' => str_repeat('c', 64)]);
        ReviewSignoff::create($base + ['scope' => $planScope, 'foundation_fingerprint' => $fingerprint, 'plan_sha256' => 'old-plan']);
        $text = implode(' ', $this->validate($lesson, $packet, null, $context)['blocking']);
        $this->assertStringContainsString('STALE', $text);

        // Valid approvals for the exact hashes.
        ReviewSignoff::create($base + ['scope' => $foundationScope, 'foundation_fingerprint' => $fingerprint, 'signed_at' => now()->addMinute()]);
        ReviewSignoff::create($base + ['scope' => $planScope, 'foundation_fingerprint' => $fingerprint, 'plan_sha256' => 'plan-sha', 'signed_at' => now()->addMinute()]);
        $this->assertSame([], $this->validate($lesson, $packet, null, $context)['blocking']);
    }

    public function test_canonical_terminology_lint_flags_packet_unsupported_concepts(): void
    {
        // 'position' used as a motion concept on a distance-time lesson whose
        // packet has no position/displacement handle -> warning (never blocking).
        $lesson = $this->lesson($this->packet());
        $lesson['phases'][0]['blocks'][0]['body_md'] = 'Height is the position on a distance–time graph.';
        $result = $this->validate($lesson);
        $this->assertSame([], $result['blocking']);
        $this->assertStringContainsString("'position'", implode(' ', $result['warnings']));

        // Graph-type compound drift.
        $lesson['phases'][0]['blocks'][0]['body_md'] = 'Now read the velocity–time graph.';
        $this->assertStringContainsString('velocity-time graph concept', implode(' ', $this->validate($lesson)['warnings']));

        // Bare 'displacement' drift - including inside check feedback strings.
        $lesson['phases'][0]['blocks'][0]['body_md'] = 'Slope carries the state of motion.';
        $lesson['phases'][0]['blocks'][1]['evaluation']['data']['feedback_incorrect_md'] = 'That is the displacement, not the distance.';
        $this->assertStringContainsString("'displacement'", implode(' ', $this->validate($lesson)['warnings']));
    }

    public function test_canonical_terminology_lint_stays_quiet_when_legitimate(): void
    {
        // Verb usage ("position the ...") is not concept drift.
        $lesson = $this->lesson($this->packet());
        $lesson['phases'][0]['blocks'][0]['body_md'] = 'Position the two corners of the triangle on the line.';
        $this->assertSame([], $this->validate($lesson)['warnings']);

        // A packet that DOES carry the concept silences the lint.
        $packet = $this->packet();
        $packet['allowed_reference_handles']['supporting'][] = 'term:position_time_graph';
        $lesson = $this->lesson($packet);
        $lesson['phases'][0]['blocks'][0]['body_md'] = 'Compare this with the position–time graph and the position of the object.';
        $this->assertSame([], $this->validate($lesson, $packet)['warnings']);
    }

    public function test_stage_a_validator_is_untouched(): void
    {
        // The Stage B validator must be additive: Stage A's contract constant
        // and entry point still exist unchanged.
        $this->assertSame('topicaled.stage-a-plan.v2', StageAPlanValidator::PLAN_SCHEMA);
        $this->assertTrue(method_exists(StageAPlanValidator::class, 'validate'));
    }
}
