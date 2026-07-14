<?php

namespace Tests\Feature\V2;

use Tests\TestCase;

/**
 * Guards the offline-authoring gate command (v2:validate-stage-a-plan):
 * file handling, packet pre-authoring checks, and FAILURE exit on blocking
 * violations. Pure file-based - no database rows needed.
 */
class ValidateStageAPlanCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/stage_a_cli_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function packet(): array
    {
        return [
            'packet_schema' => 'topicaled.stage-a-packet.v2',
            'identity' => ['source_pdf_sha256' => str_repeat('a', 64), 'leaf_section' => ['section_code' => '1.2', 'title' => 'Motion']],
            'target_coverage' => ['objectives' => [[
                'reference' => '1.2 #13', 'number' => 13,
                'requirements' => [['role' => 'prerequisite', 'target' => 'definition:acceleration', 'title' => 'acceleration']],
            ]]],
            'out_of_scope_curriculum' => ['objectives' => [['number' => 4]]],
            'allowed_reference_handles' => ['direct' => ['term:gradient'], 'supporting' => ['definition:acceleration']],
            'hard_constraints' => ['exclusions' => [], 'depth' => [], 'conditions' => []],
            'policy_slices' => ['command_words' => ['items' => [['key' => 'Calculate', 'text' => 'work out']]]],
            'assessment_evidence' => ['allowed_evidence_ids' => [502], 'questions' => [['evidence_id' => 502, 'needs_review' => false]]],
            'pedagogical_evidence' => ['misconceptions' => [['id' => 'misc.x', 'provenance_type' => 'curated_authoring', 'priority' => 'high']], 'allowed_misconception_ids' => ['misc.x']],
            'relevant_widgets' => ['widgets' => [['key' => 'graph_explorer', 'compatibility_verdict' => 'compatible']]],
            'validation' => ['objectives_all_validated' => true, 'warnings' => []],
        ];
    }

    private function plan(array $overrides = []): array
    {
        return array_merge([
            'plan_schema' => 'topicaled.stage-a-plan.v2',
            'conceptual_spine' => 'gradient meaning depends on the axes',
            'learning_dependencies' => [['before' => 'definition:acceleration', 'after' => '1.2 #13', 'reason' => 'x']],
            'target_los' => ['1.2 #13'],
            'supporting_assumptions' => ['definition:acceleration'],
            'phases' => [[
                'title' => 'P1', 'purpose' => 'x',
                'rationale' => ['why_now' => 'x', 'assumes' => 'x', 'prepares_for' => 'x'],
                'advances_los' => ['1.2 #13'],
                'direct_reference_handles' => ['term:gradient'],
                'supporting_reference_handles' => ['definition:acceleration'],
                'misconceptions' => [['id' => 'misc.x']],
                'widget' => null,
                'checks' => [['command_word' => 'Calculate', 'intent' => 'x', 'evidence_question_ids' => [502]]],
            ]],
        ], $overrides);
    }

    private function write(string $name, array $data): string
    {
        $path = "{$this->dir}/{$name}";
        file_put_contents($path, json_encode($data));

        return $path;
    }

    public function test_valid_plan_passes_with_packet_prechecks(): void
    {
        $this->artisan('v2:validate-stage-a-plan', [
            'plan' => $this->write('plan.json', $this->plan()),
            'packet' => $this->write('packet.json', $this->packet()),
            '--expect-objectives' => '13',
        ])->assertSuccessful();
    }

    public function test_blocking_violation_exits_failure(): void
    {
        // The exact call-01 regression: group-name-derived handle.
        $plan = $this->plan();
        $plan['phases'][0]['direct_reference_handles'] = ['terminology:gradient'];

        $this->artisan('v2:validate-stage-a-plan', [
            'plan' => $this->write('plan.json', $plan),
            'packet' => $this->write('packet.json', $this->packet()),
        ])->assertFailed();
    }

    public function test_packet_precheck_failure_blocks_before_validation(): void
    {
        $packet = $this->packet();
        $packet['validation']['warnings'] = ['something unsafe'];

        $this->artisan('v2:validate-stage-a-plan', [
            'plan' => $this->write('plan.json', $this->plan()),
            'packet' => $this->write('packet.json', $packet),
            '--expect-objectives' => '13',
        ])->assertFailed();
    }

    public function test_missing_or_invalid_files_fail_cleanly(): void
    {
        $this->artisan('v2:validate-stage-a-plan', [
            'plan' => "{$this->dir}/nope.json",
            'packet' => $this->write('packet.json', $this->packet()),
        ])->assertFailed();

        file_put_contents("{$this->dir}/bad.json", 'not json');
        $this->artisan('v2:validate-stage-a-plan', [
            'plan' => "{$this->dir}/bad.json",
            'packet' => $this->write('packet.json', $this->packet()),
        ])->assertFailed();
    }
}
