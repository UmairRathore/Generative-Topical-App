<?php

namespace Tests\Feature\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\LearningObjectiveReference;
use App\Models\V2\ReviewSignoff;
use App\Models\V2\SyllabusReference;
use App\Models\V2\SyllabusSource;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The subject-wide rollout dashboard: v2:authoring-readiness measures each
 * leaf section against the same inputs the packet compiler fails closed on.
 * Read-only - it must classify, never repair or generate.
 */
class AuthoringReadinessCommandTest extends TestCase
{
    use DatabaseMigrations;

    private string $refRoot = 'storage/framework/testing/readiness_src';

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        File::ensureDirectoryExists(base_path($this->refRoot.'/references/evidence'));
        File::ensureDirectoryExists(base_path($this->refRoot.'/references/misconceptions'));
        File::ensureDirectoryExists(base_path($this->refRoot.'/references/widgets'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->refRoot));
        parent::tearDown();
    }

    private function source(): SyllabusSource
    {
        return SyllabusSource::create([
            'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => '2026-2028',
            'title' => 'Physics 5054', 'source_pdf_path' => $this->refRoot.'/syllabus.pdf',
            'source_pdf_sha256' => str_repeat('a', 64),
        ]);
    }

    private function lo(SyllabusSource $source, string $section, int $number, string $status): LearningObjective
    {
        return LearningObjective::create([
            'syllabus_source_id' => $source->id, 'topic_number' => 1,
            'section_code' => $section, 'section_title' => "Section {$section}",
            'objective_number' => $number, 'text' => "Objective {$number}", 'status' => $status,
        ]);
    }

    public function test_requires_a_registered_source(): void
    {
        $this->artisan('v2:authoring-readiness')->assertExitCode(1);
    }

    public function test_classifies_sections_by_gate_order(): void
    {
        $source = $this->source();

        // 9.1: one extracted LO -> needs_lo_validation.
        $this->lo($source, '9.1', 1, 'extracted');

        // 9.2: validated LOs, nothing curated -> needs_curation.
        $this->lo($source, '9.2', 1, 'validated');

        // 9.3: validated + curated + validated ref mapped -> packet_ready.
        $validated = $this->lo($source, '9.3', 1, 'validated');
        $ref = SyllabusReference::create([
            'syllabus_source_id' => $source->id, 'reference_type' => 'terminology',
            'key' => 'thing', 'title' => 'thing', 'payload' => ['term' => 'thing'],
            'provenance' => [], 'status' => 'validated',
        ]);
        LearningObjectiveReference::create([
            'learning_objective_id' => $validated->id, 'syllabus_reference_id' => $ref->id, 'role' => 'direct',
        ]);
        foreach (['evidence', 'misconceptions', 'widgets'] as $kind) {
            file_put_contents(base_path($this->refRoot."/references/{$kind}/9.3.json"), '{}');
        }

        // 9.4: validated + foundation and plan approvals recorded -> stage_b_ready.
        $this->lo($source, '9.4', 1, 'validated');
        foreach (['stage-a-foundation', 'stage-a-plan'] as $kind) {
            ReviewSignoff::create([
                'syllabus_source_id' => $source->id, 'scope' => "{$kind}:5054@2026-2028:9.4:1",
                'reviewer' => 'human', 'packet_sha256' => str_repeat('b', 64),
                'foundation_fingerprint' => str_repeat('c', 64), 'signed_at' => now(),
            ]);
        }

        $this->withoutMockingConsoleOutput();
        $exit = Artisan::call('v2:authoring-readiness', ['--source' => '5054@2026-2028', '--json' => true]);
        $this->assertSame(0, $exit);
        $json = json_decode(Artisan::output(), true);
        $verdicts = collect($json['sections'])->pluck('verdict', 'section');
        $this->assertSame('needs_lo_validation', $verdicts['9.1']);
        $this->assertSame('needs_curation', $verdicts['9.2']);
        $this->assertSame('packet_ready', $verdicts['9.3']);
        $this->assertSame('stage_b_ready', $verdicts['9.4']);
    }
}
