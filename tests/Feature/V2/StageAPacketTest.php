<?php

namespace Tests\Feature\V2;

use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\LessonPlanPacketCompiler;
use App\Services\V2\StageAPlanValidator;
use App\Services\V2\SyllabusReferenceGuard;
use App\Services\V2\SyllabusReferenceResolver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the Stage A v2 pipeline contract: first-class reference handles
 * (regression: call-01 derived `terminology:x` from group names), canonical
 * JSON null, operational supporting truth, explicit evidence/misconception
 * identity, structured widget placements, and the blocking/warnings split of
 * StageAPlanValidator. Fails closed - unknown is never approval.
 */
class StageAPacketTest extends TestCase
{
    use DatabaseMigrations;

    private string $registryFixture;

    private string $shortlistDir;

    private string $misconceptionDir;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        DB::table('v2_subjects')->insert(['id' => 1, 'name' => 'Physics', 'code' => '5054', 'level' => 'O Level', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_topics')->insert(['id' => 10, 'subject_id' => 1, 'external_id' => '1', 'title' => 'Motion, forces and energy', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_subtopics')->insert([
            ['id' => 20, 'topic_id' => 10, 'external_id' => '1.2', 'title' => 'Motion', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'topic_id' => 10, 'external_id' => '1.5.4', 'title' => 'Circular motion', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('v2_syllabus_sources')->insert([
            'id' => 1, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => '2023-2025',
            'title' => 'Physics 5054 (2023-2025)', 'exam_years' => json_encode([2023, 2024, 2025]),
            'source_pdf_path' => 'storage/a.pdf', 'source_pdf_sha256' => str_repeat('a', 64),
            'structure_json' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('v2_learning_objectives')->insert([
            ['id' => 100, 'syllabus_source_id' => 1, 'subtopic_id' => 20, 'topic_number' => 1, 'section_code' => '1.2', 'section_title' => 'Motion', 'objective_number' => 4,
                'text' => 'Define acceleration as change in velocity per unit time', 'sub_items' => null, 'equations' => null, 'constraints' => null, 'raw_extract' => null,
                'provenance' => null, 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 101, 'syllabus_source_id' => 1, 'subtopic_id' => 20, 'topic_number' => 1, 'section_code' => '1.2', 'section_title' => 'Motion', 'objective_number' => 13,
                'text' => 'Calculate acceleration from the gradient of a speed–time graph', 'sub_items' => null, 'equations' => null, 'constraints' => null, 'raw_extract' => null,
                'provenance' => null, 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 110, 'syllabus_source_id' => 1, 'subtopic_id' => 21, 'topic_number' => 1, 'section_code' => '1.5.4', 'section_title' => 'Circular motion', 'objective_number' => 1,
                'text' => 'Describe, qualitatively, motion in a circular path', 'sub_items' => null, 'equations' => null,
                'constraints' => json_encode([['type' => 'exclusion', 'text' => 'F = mv²/r is not required']]), 'raw_extract' => null,
                'provenance' => null, 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('v2_syllabus_references')->insert([
            ['id' => 200, 'syllabus_source_id' => 1, 'reference_type' => 'quantity', 'key' => 'acceleration', 'title' => 'acceleration',
                'payload' => json_encode(['name' => 'acceleration', 'symbols' => ['a'], 'units' => ['m/s²']]),
                'provenance' => json_encode(['policy' => 'symbols_units']), 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 202, 'syllabus_source_id' => 1, 'reference_type' => 'definition', 'key' => 'acceleration', 'title' => 'acceleration',
                'payload' => json_encode(['term' => 'acceleration', 'text' => 'change in velocity per unit time']),
                'provenance' => json_encode(['learning_objective' => '1.2 #4']), 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 204, 'syllabus_source_id' => 1, 'reference_type' => 'term', 'key' => 'gradient', 'title' => 'gradient',
                'payload' => json_encode(['term' => 'gradient']),
                'provenance' => json_encode(['learning_objective' => '1.2 #11']), 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 206, 'syllabus_source_id' => 1, 'reference_type' => 'term', 'key' => 'circular_motion', 'title' => 'circular motion',
                'payload' => json_encode(['term' => 'circular motion']),
                'provenance' => json_encode(['learning_objective' => '1.5.4 #1']), 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('v2_learning_objective_references')->insert([
            ['learning_objective_id' => 100, 'syllabus_reference_id' => 202, 'policy_type' => null, 'policy_item_key' => null, 'role' => 'defines', 'note' => null, 'created_at' => now(), 'updated_at' => now()],
            ['learning_objective_id' => 101, 'syllabus_reference_id' => 204, 'policy_type' => null, 'policy_item_key' => null, 'role' => 'calculates_with', 'note' => null, 'created_at' => now(), 'updated_at' => now()],
            ['learning_objective_id' => 101, 'syllabus_reference_id' => 200, 'policy_type' => null, 'policy_item_key' => null, 'role' => 'uses', 'note' => null, 'created_at' => now(), 'updated_at' => now()],
            ['learning_objective_id' => 101, 'syllabus_reference_id' => 202, 'policy_type' => null, 'policy_item_key' => null, 'role' => 'prerequisite', 'note' => null, 'created_at' => now(), 'updated_at' => now()],
            ['learning_objective_id' => 110, 'syllabus_reference_id' => 206, 'policy_type' => null, 'policy_item_key' => null, 'role' => 'uses', 'note' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('v2_syllabus_policies')->insert([
            'syllabus_source_id' => 1, 'policy_type' => 'command_words', 'title' => 'Command words',
            'content' => json_encode(['words' => [['word' => 'Calculate', 'meaning' => 'work out'], ['word' => 'Describe', 'meaning' => 'state points'], ['word' => 'Define', 'meaning' => 'precise meaning']]]),
            'provenance' => null, 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([[500, 2019, false], [501, 2024, true], [502, 2024, false], [503, 2023, false]] as [$id, $year, $needsReview]) {
            DB::table('v2_questions')->insert([
                'id' => $id, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 20,
                'year' => $year, 'question_number' => $id - 499, 'question_text' => "Stem {$id}", 'layout_type' => 'text_only',
                'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => $needsReview,
                'source_paper' => "5054_s{$year}_qp_11", 'current_version_id' => $id * 10, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->registryFixture = tempnam(sys_get_temp_dir(), 'reg').'.js';
        file_put_contents($this->registryFixture, "export const registry = {\n    graph_explorer: () => import('./x.jsx'),\n    circular_motion: () => import('./y.jsx'),\n};\n");

        $this->shortlistDir = sys_get_temp_dir().'/shortlist_'.uniqid();
        mkdir($this->shortlistDir);
        file_put_contents("{$this->shortlistDir}/1.2.json", json_encode(['widgets' => [
            ['key' => 'graph_explorer', 'capability' => 'motion graphs', 'relevant_to_objectives' => [13], 'demonstrates' => ['term:gradient'], 'formulas' => []],
        ]]));
        file_put_contents("{$this->shortlistDir}/1.5.4.json", json_encode(['widgets' => [
            ['key' => 'circular_motion', 'capability' => 'centripetal demo', 'relevant_to_objectives' => [1], 'demonstrates' => ['term:circular_motion'], 'formulas' => ['F = mv²/r']],
        ]]));

        $this->misconceptionDir = sys_get_temp_dir().'/misc_'.uniqid();
        mkdir($this->misconceptionDir);
        file_put_contents("{$this->misconceptionDir}/1.2.json", json_encode(['misconceptions' => [
            ['id' => 'misc.gradient_confused_with_area', 'statement' => 'Gradient vs area swap.',
                'affected_target_los' => ['1.2 #13'], 'related_reference_handles' => ['term:gradient'],
                'provenance_type' => 'curated_authoring', 'priority' => 'high'],
        ]]));
    }

    protected function tearDown(): void
    {
        @unlink($this->registryFixture);
        foreach ([$this->shortlistDir, $this->misconceptionDir] as $dir) {
            foreach (glob("{$dir}/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    private function packet(string $ext = '1.2', array $numbers = [13]): array
    {
        return (new LessonPlanPacketCompiler(
            new SyllabusReferenceResolver,
            new SyllabusReferenceGuard,
            $this->registryFixture,
            $this->shortlistDir,
            $this->misconceptionDir,
        ))->compile(
            SyllabusSource::findOrFail(1),
            Subtopic::where('external_id', $ext)->firstOrFail(),
            $numbers,
        )['packet'];
    }

    /** A structurally clean v2 plan for the 1.2 #13 fixture packet. */
    private function validPlan(): array
    {
        return [
            'plan_schema' => StageAPlanValidator::PLAN_SCHEMA,
            'lesson_title' => 'Acceleration from speed–time graphs',
            'conceptual_spine' => 'The gradient of a speed–time graph IS the acceleration - reading rate of change off a graph.',
            'learning_dependencies' => [
                ['before' => 'definition:acceleration', 'after' => '1.2 #13', 'reason' => 'gradient meaning presupposes what acceleration is'],
            ],
            'target_los' => ['1.2 #13'],
            'supporting_assumptions' => ['definition:acceleration'],
            'phases' => [
                [
                    'title' => 'Recall acceleration', 'purpose' => 'reactivate the definition',
                    'rationale' => ['why_now' => 'needed before gradient work', 'assumes' => 'prior lesson', 'prepares_for' => 'gradient phase'],
                    'supporting_review' => true, 'advances_los' => [],
                    'supporting_reference_handles' => ['definition:acceleration'],
                    'checks' => [],
                ],
                [
                    'title' => 'Gradient method', 'purpose' => 'calculate acceleration from the graph',
                    'rationale' => ['why_now' => 'core skill', 'assumes' => 'recall phase', 'prepares_for' => 'practice'],
                    'advances_los' => ['1.2 #13'],
                    'direct_reference_handles' => ['term:gradient', 'quantity:acceleration'],
                    'supporting_reference_handles' => ['definition:acceleration'],
                    'widget' => [
                        'widget_key' => 'graph_explorer', 'purpose' => 'manipulate gradient',
                        'target_lo_refs' => ['1.2 #13'],
                        'canonical_reference_handles_demonstrated' => ['term:gradient'],
                        'student_manipulation' => 'drag graph segments', 'expected_observation' => 'steeper = bigger a',
                        'intended_inference' => 'gradient is acceleration',
                    ],
                    'misconceptions' => [['id' => 'misc.gradient_confused_with_area']],
                    'checks' => [['command_word' => 'Calculate', 'intent' => 'gradient calculation', 'evidence_question_ids' => [502]]],
                ],
            ],
        ];
    }

    // ── Packet contract ────────────────────────────────────────────────────

    public function test_every_reference_entry_exposes_exact_handle_and_allowlists(): void
    {
        $packet = $this->packet();

        foreach ($packet['direct_academic_truth'] as $rows) {
            foreach ($rows as $row) {
                $this->assertArrayHasKey('handle', $row);
            }
        }
        foreach ($packet['supporting_academic_truth']['references'] as $rows) {
            foreach ($rows as $row) {
                $this->assertArrayHasKey('handle', $row);
            }
        }

        // Handles use the canonical type prefix - group names never leak in.
        $this->assertSame(['quantity:acceleration', 'term:gradient'], $packet['allowed_reference_handles']['direct']);
        $this->assertSame(['definition:acceleration'], $packet['allowed_reference_handles']['supporting']);
        $this->assertNotContains('terminology:gradient', $packet['allowed_reference_handles']['direct']);

        // Unique across the packet; grouping does not change a handle.
        $all = array_merge($packet['allowed_reference_handles']['direct'], $packet['allowed_reference_handles']['supporting']);
        $this->assertSame($all, array_values(array_unique($all)));
    }

    public function test_evidence_and_misconception_identity_is_explicit(): void
    {
        $packet = $this->packet();

        $this->assertSame([502, 503], $packet['assessment_evidence']['allowed_evidence_ids']);
        $this->assertSame(502, $packet['assessment_evidence']['questions'][0]['evidence_id']);
        $this->assertSame(['misc.gradient_confused_with_area'], $packet['pedagogical_evidence']['allowed_misconception_ids']);
        $this->assertSame('curated_authoring', $packet['pedagogical_evidence']['misconceptions'][0]['provenance_type']);
        $this->assertSame('topicaled.stage-a-packet.v2', $packet['packet_schema']);
    }

    // ── Validator: clean plan ──────────────────────────────────────────────

    public function test_validator_accepts_a_coherent_v2_plan_with_zero_blocking(): void
    {
        $result = (new StageAPlanValidator)->validate($this->validPlan(), $this->packet());

        $this->assertSame([], $result['blocking']);
        $this->assertSame([], $result['warnings']);
    }

    // ── Validator: call-01 regressions ─────────────────────────────────────

    public function test_group_name_derived_handles_fail_with_deterministic_hint(): void
    {
        $plan = $this->validPlan();
        $plan['phases'][1]['direct_reference_handles'] = ['terminology:gradient']; // exact call-01 failure

        $result = (new StageAPlanValidator)->validate($plan, $this->packet());
        $text = implode("\n", $result['blocking']);

        $this->assertStringContainsString("'terminology:gradient' does not exist", $text);
        $this->assertStringContainsString('Did you mean: term:gradient?', $text);
    }

    public function test_string_null_widget_fails_json_null_passes(): void
    {
        $packet = $this->packet();

        $plan = $this->validPlan();
        $plan['phases'][1]['widget'] = 'null'; // exact call-01 failure
        $result = (new StageAPlanValidator)->validate($plan, $packet);
        $this->assertStringContainsString("the string 'null' is not a null", implode("\n", $result['blocking']));

        $plan['phases'][1]['widget'] = null; // canonical empty representation
        $this->assertSame([], (new StageAPlanValidator)->validate($plan, $packet)['blocking']);
    }

    // ── Validator: supporting truth ────────────────────────────────────────

    public function test_supporting_truth_rules(): void
    {
        $packet = $this->packet();

        // Supporting-as-direct misuse blocks.
        $plan = $this->validPlan();
        $plan['phases'][1]['direct_reference_handles'][] = 'definition:acceleration';
        $this->assertStringContainsString('as direct', implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));

        // Supporting review with zero supporting handles blocks.
        $plan = $this->validPlan();
        $plan['phases'][0]['supporting_reference_handles'] = [];
        $this->assertStringContainsString('cites zero supporting_reference_handles', implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));

        // Mapped supporting truth unused by the advancing phase -> warning, not block.
        $plan = $this->validPlan();
        $plan['phases'][1]['supporting_reference_handles'] = [];
        $result = (new StageAPlanValidator)->validate($plan, $packet);
        $this->assertSame([], $result['blocking']);
        $this->assertStringContainsString('has mapped supporting truth (definition:acceleration) but no phase advancing it uses any of it', implode("\n", $result['warnings']));

        // Using supporting truth never claims the sibling: #4 stays out of scope.
        $this->assertContains(4, array_column($packet['out_of_scope_curriculum']['objectives'], 'number'));
    }

    // ── Validator: evidence + misconceptions + pedagogy ───────────────────

    public function test_evidence_rules(): void
    {
        $packet = $this->packet();

        $plan = $this->validPlan();
        $plan['phases'][1]['checks'] = [['command_word' => 'Calculate', 'intent' => 'x', 'evidence_question_ids' => [999]]];
        $this->assertStringContainsString('evidence id 999 which is not packet evidence', implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));

        $plan['phases'][1]['checks'] = [['command_word' => 'Calculate', 'intent' => 'x', 'evidence_question_ids' => []]];
        $this->assertStringContainsString('no evidence_independence_reason', implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));

        $plan['phases'][1]['checks'] = [['command_word' => 'Calculate', 'intent' => 'x', 'evidence_question_ids' => [], 'evidence_independence_reason' => 'pure concept check']];
        $result = (new StageAPlanValidator)->validate($plan, $packet);
        $this->assertSame([], $result['blocking']);
        $this->assertStringContainsString('Every formative check ignores all supplied assessment evidence', implode("\n", $result['warnings']));
    }

    public function test_misconception_and_pedagogy_rules(): void
    {
        $packet = $this->packet();

        $plan = $this->validPlan();
        $plan['phases'][1]['misconceptions'] = [['id' => 'misc.invented']];
        $this->assertStringContainsString("misconception id 'misc.invented' does not exist", implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));

        // Unverified proposals are allowed but must be classified; dropping the
        // packet's high-priority misconception warns.
        $plan['phases'][1]['misconceptions'] = [['unverified_proposal' => 'students misread axes']];
        $result = (new StageAPlanValidator)->validate($plan, $packet);
        $this->assertSame([], $result['blocking']);
        $this->assertStringContainsString('No high-priority packet misconception is addressed', implode("\n", $result['warnings']));

        // Unclassified entries block.
        $plan['phases'][1]['misconceptions'] = [['statement' => 'raw unclassified']];
        $this->assertStringContainsString('packet id or a classified unverified_proposal', implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));

        // Missing spine/dependencies block; >2-LO phase without justification warns.
        $plan = $this->validPlan();
        unset($plan['conceptual_spine'], $plan['learning_dependencies']);
        $text = implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']);
        $this->assertStringContainsString('conceptual_spine is missing', $text);
        $this->assertStringContainsString('learning_dependencies missing', $text);
    }

    public function test_widget_placement_rules(): void
    {
        $packet = $this->packet();

        $plan = $this->validPlan();
        $plan['phases'][1]['widget']['canonical_reference_handles_demonstrated'] = ['terminology:gradient'];
        $this->assertStringContainsString("demonstrates 'terminology:gradient' which is not a packet handle", implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));

        $plan = $this->validPlan();
        $plan['phases'][1]['widget']['target_lo_refs'] = ['1.2 #4'];
        $this->assertStringContainsString("widget targets '1.2 #4' which is not a packet target objective", implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']));
    }

    // ── Exclusion + scope regressions ──────────────────────────────────────

    public function test_circular_motion_exclusion_and_widget_veto_regression(): void
    {
        $packet = $this->packet('1.5.4', [1]);

        $this->assertSame('incompatible_excluded_content', $packet['relevant_widgets']['widgets'][0]['compatibility_verdict']);
        $this->assertSame([['learning_objective' => '1.5.4 #1', 'text' => 'F = mv²/r is not required']], $packet['hard_constraints']['exclusions']);

        $plan = [
            'plan_schema' => StageAPlanValidator::PLAN_SCHEMA,
            'conceptual_spine' => 'qualitative circular motion reasoning',
            'learning_dependencies' => [['before' => 'term:circular_motion', 'after' => '1.5.4 #1', 'reason' => 'x']],
            'target_los' => ['1.5.4 #1'],
            'supporting_assumptions' => [],
            'phases' => [[
                'title' => 'P1', 'purpose' => 'x',
                'rationale' => ['why_now' => 'x', 'assumes' => 'x', 'prepares_for' => 'x'],
                'advances_los' => ['1.5.4 #1'],
                'direct_reference_handles' => ['term:circular_motion'],
                'concepts' => ['F = mv²/r'],
                'widget' => ['widget_key' => 'circular_motion', 'purpose' => 'x', 'target_lo_refs' => ['1.5.4 #1'],
                    'canonical_reference_handles_demonstrated' => ['term:circular_motion'],
                    'student_manipulation' => 'x', 'expected_observation' => 'x', 'intended_inference' => 'x'],
                'checks' => [],
            ]],
        ];
        $text = implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']);

        $this->assertStringContainsString('references excluded content', $text);
        $this->assertStringContainsString("verdict 'incompatible_excluded_content' and cannot be used", $text);
    }

    public function test_sibling_coverage_and_unknown_content_still_fail(): void
    {
        $packet = $this->packet();
        $plan = $this->validPlan();
        $plan['target_los'] = ['1.2 #13', '1.2 #4'];
        $plan['phases'][1]['direct_reference_handles'][] = 'relationship:not_real';

        $text = implode("\n", (new StageAPlanValidator)->validate($plan, $packet)['blocking']);
        $this->assertStringContainsString("out-of-scope sibling '1.2 #4'", $text);
        $this->assertStringContainsString("'relationship:not_real' does not exist in the packet (unknown is not approval)", $text);
    }
}
