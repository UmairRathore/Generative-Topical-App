<?php

namespace Tests\Feature\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\SyllabusPolicy;
use App\Models\V2\SyllabusSource;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end guard for the syllabus source pipeline:
 * register -> extract (untrusted) -> apply-lo-review (validated) -> policies.
 *
 * Invariants proven here:
 *  - the existing v2_topics/v2_subtopics skeleton is READ, never written;
 *  - extraction is idempotent and NEVER clobbers human-validated rows;
 *  - questions gain no learning-objective linkage of any kind.
 *
 * Uses DatabaseMigrations (not RefreshDatabase) per V2 test convention so a
 * minimal row graph can be inserted without every parent row.
 */
class SyllabusAuthoringPipelineTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        // Minimal curriculum graph: the pre-existing skeleton the pipeline maps onto.
        DB::table('v2_subjects')->insert([
            'id' => 1, 'name' => 'Physics', 'code' => '5054', 'level' => 'O Level',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_topics')->insert([
            'id' => 10, 'subject_id' => 1, 'external_id' => '1',
            'title' => 'Motion, forces and energy', 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_subtopics')->insert([
            ['id' => 20, 'topic_id' => 10, 'external_id' => '1.1', 'title' => 'Physical quantities and measurement techniques', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'topic_id' => 10, 'external_id' => '1.2', 'title' => 'Motion', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            // 2.1.1 deliberately NOT present: exercises the unmapped-section path.
        ]);

        // Fixture artifacts on disk (absolute paths are accepted by the commands).
        $this->dir = sys_get_temp_dir().'/syllabus_pipeline_'.uniqid();
        mkdir($this->dir, 0777, true);
        file_put_contents("{$this->dir}/source.pdf", '%PDF-1.4 fake fixture pdf');
        file_put_contents("{$this->dir}/source.txt", $this->fixtureText());
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function fixtureText(): string
    {
        return <<<'TXT'
===== PAGE 12 =====
Cambridge O Level Physics 5054 syllabus for 2023, 2024 and 2025.
10
www.cambridgeinternational.org/olevel Back to contents page
3 Subject content
1 Motion, forces and energy
1.1 Physical quantities and measurement techniques
1 Describe how to measure a variety of lengths
1.2 Motion
1 Define speed as distance travelled per unit time
2 Recall and use the equation
 speed = distance
time
2 Thermal physics
2.1 Kinetic particle model of matter
2.1.1 States of matter
1 Know the distinguishing properties of solids, liquids and gases
4 Details of the assessment
TXT;
    }

    private function register(): SyllabusSource
    {
        $this->artisan('v2:register-syllabus-source', [
            '--subject' => '5054',
            '--version-label' => '2023-2025',
            '--pdf' => "{$this->dir}/source.pdf",
            '--text' => "{$this->dir}/source.txt",
            '--title' => 'Cambridge O Level Physics 5054 syllabus for 2023, 2024 and 2025',
            '--years' => '2023,2024,2025',
        ])->assertSuccessful();

        return SyllabusSource::where('syllabus_code', '5054')->firstOrFail();
    }

    public function test_register_pins_hashes_and_is_upsert_by_code_and_version(): void
    {
        $source = $this->register();

        $this->assertSame(hash_file('sha256', "{$this->dir}/source.pdf"), $source->source_pdf_sha256);
        $this->assertSame(hash_file('sha256', "{$this->dir}/source.txt"), $source->extracted_text_sha256);
        $this->assertSame([2023, 2024, 2025], $source->exam_years);

        // Re-registering the same (code, version) updates in place - no duplicate source.
        $this->register();
        $this->assertSame(1, SyllabusSource::count());
    }

    public function test_extract_creates_untrusted_objectives_mapped_onto_existing_subtopics(): void
    {
        $source = $this->register();
        $this->artisan('v2:extract-learning-objectives', ['--source' => '5054@2023-2025'])->assertSuccessful();

        $this->assertSame(4, LearningObjective::count()); // 1 + 2 + 1
        $this->assertSame(4, LearningObjective::where('status', 'extracted')->count()); // all untrusted

        // Mapped by external_id onto the EXISTING skeleton...
        $lo = LearningObjective::where('section_code', '1.2')->where('objective_number', 1)->firstOrFail();
        $this->assertSame(21, $lo->subtopic_id);
        $this->assertSame('Motion', $lo->section_title);
        $this->assertSame([12], $lo->provenance['pdf_pages']);

        // ...unmapped official sections keep canonical identity with a NULL mapping.
        $orphan = LearningObjective::where('section_code', '2.1.1')->firstOrFail();
        $this->assertNull($orphan->subtopic_id);

        // The skeleton itself was only read: same rows, same titles.
        $this->assertSame(2, DB::table('v2_subtopics')->count());
        $this->assertSame(1, DB::table('v2_topics')->count());

        // Structure map captured on the source, including the non-leaf 2.1 header.
        $codes = array_column($source->fresh()->structure_json['sections'], 'code');
        $this->assertSame(['1.1', '1.2', '2.1', '2.1.1'], $codes);

        // No learning-objective linkage exists on questions - by design.
        $this->assertFalse(Schema::hasColumn('v2_questions', 'learning_objective_id'));
    }

    public function test_overlay_validates_and_reextraction_never_clobbers_reviewed_rows(): void
    {
        $this->register();
        $this->artisan('v2:extract-learning-objectives', ['--source' => '5054@2023-2025'])->assertSuccessful();

        $overlay = [
            'section_code' => '1.2',
            'reviewed_by' => 'test-reviewer',
            'objectives' => [[
                'number' => 2,
                'text' => 'Recall and use the equation speed = distance / time; v = s / t',
                'equations' => [
                    ['kind' => 'word', 'text' => 'speed = distance / time'],
                    ['kind' => 'symbol', 'text' => 'v = s / t'],
                ],
                'constraints' => [['type' => 'depth', 'text' => 'recall and use']],
                'review_note' => 'Stacked fraction restored.',
            ]],
        ];
        file_put_contents("{$this->dir}/overlay.json", json_encode($overlay));

        $this->artisan('v2:apply-lo-review', [
            'overlay' => "{$this->dir}/overlay.json",
            '--source' => '5054@2023-2025',
        ])->assertSuccessful();

        $lo = LearningObjective::where('section_code', '1.2')->where('objective_number', 2)->firstOrFail();
        $this->assertSame('validated', $lo->status);
        $this->assertSame('test-reviewer', $lo->validated_by);
        $this->assertSame('speed = distance / time', $lo->equations[0]['text']);
        $this->assertSame(['depth' => 'recall and use'], [$lo->constraints[0]['type'] => $lo->constraints[0]['text']]);
        // The untouched machine output survives for audit even after overrides.
        $this->assertStringContainsString('speed = distance time', $lo->raw_extract['text']);
        $this->assertStringContainsString('overlay.json@', $lo->provenance['overlay']);

        // Re-extraction must skip the validated row (human review is authoritative)...
        $this->artisan('v2:extract-learning-objectives', ['--source' => '5054@2023-2025'])->assertSuccessful();
        $this->assertSame('Recall and use the equation speed = distance / time; v = s / t', $lo->fresh()->text);
        // ...while unreviewed siblings remain refreshable.
        $this->assertSame('extracted', LearningObjective::where('objective_number', 1)->where('section_code', '1.2')->first()->status);
    }

    public function test_overlay_requires_reviewer_identity(): void
    {
        $this->register();
        $this->artisan('v2:extract-learning-objectives', ['--source' => '5054@2023-2025'])->assertSuccessful();

        file_put_contents("{$this->dir}/anon.json", json_encode([
            'section_code' => '1.2',
            'objectives' => [['number' => 1]],
        ]));

        $this->artisan('v2:apply-lo-review', [
            'overlay' => "{$this->dir}/anon.json",
            '--source' => '5054@2023-2025',
        ])->assertFailed();

        $this->assertSame(0, LearningObjective::where('status', 'validated')->count());
    }

    public function test_policies_import_untrusted_by_default_and_validate_explicitly(): void
    {
        $this->register();

        mkdir("{$this->dir}/policies");
        file_put_contents("{$this->dir}/policies/command_words.json", json_encode([
            'policy_type' => 'command_words',
            'title' => 'Command words',
            'reviewed_by' => 'test-reviewer',
            'provenance' => ['pdf_pages' => [46]],
            'content' => ['words' => [['word' => 'State', 'meaning' => 'express in clear terms']]],
        ]));
        file_put_contents("{$this->dir}/policies/bogus.json", json_encode([
            'policy_type' => 'not_a_real_type',
            'title' => 'Bogus',
            'content' => ['x' => 1],
        ]));

        // Unknown policy_type is rejected; the valid file lands as extracted (untrusted).
        $this->artisan('v2:import-syllabus-policies', [
            '--source' => '5054@2023-2025',
            '--path' => "{$this->dir}/policies",
        ])->assertFailed();

        $policy = SyllabusPolicy::where('policy_type', 'command_words')->firstOrFail();
        $this->assertSame('extracted', $policy->status);
        $this->assertSame(1, SyllabusPolicy::count()); // bogus type never persisted

        unlink("{$this->dir}/policies/bogus.json");
        $this->artisan('v2:import-syllabus-policies', [
            '--source' => '5054@2023-2025',
            '--path' => "{$this->dir}/policies",
            '--validate' => true,
        ])->assertSuccessful();

        $this->assertSame('validated', $policy->fresh()->status);
        $this->assertSame('test-reviewer', $policy->fresh()->validated_by);
        $this->assertStringContainsString('source.pdf@', $policy->fresh()->provenance['curated_from']);
    }
}
