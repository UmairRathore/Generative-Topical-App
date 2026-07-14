<?php

namespace Tests\Feature\V2;

use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\SyllabusReferenceGuard;
use App\Services\V2\SyllabusReferenceResolver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Guards the Canonical Syllabus Reference Library contract:
 * source/version scoping, validated-only resolution, many-to-many LO
 * mappings, deterministic relevance-sliced unions, exclusions as hard
 * bounds, and the guard verdicts a future Fable validator relies on.
 *
 * Fixture: one 5054-like source with a Motion-like leaf (2 LOs) and a
 * Circular-motion-like leaf (1 LO with an exclusion), plus a SECOND source
 * owning a same-key reference to prove version scoping.
 */
class SyllabusReferenceLibraryTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        DB::table('v2_subjects')->insert([
            'id' => 1, 'name' => 'Physics', 'code' => '5054', 'level' => 'O Level',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_topics')->insert([
            'id' => 10, 'subject_id' => 1, 'external_id' => '1', 'title' => 'Motion, forces and energy',
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_subtopics')->insert([
            ['id' => 20, 'topic_id' => 10, 'external_id' => '1.2', 'title' => 'Motion', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'topic_id' => 10, 'external_id' => '1.5.4', 'title' => 'Circular motion', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('v2_syllabus_sources')->insert([
            [
                'id' => 1, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => '2023-2025',
                'title' => 'Physics 5054 (2023-2025)', 'exam_years' => json_encode([2023, 2024, 2025]),
                'source_pdf_path' => 'storage/a.pdf', 'source_pdf_sha256' => str_repeat('a', 64),
                'structure_json' => json_encode(['topics' => [1 => 'Motion, forces and energy'], 'sections' => [
                    ['code' => '1.5', 'title' => 'Forces', 'topic' => 1, 'leaf' => false, 'lo_count' => 0],
                ]]),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                // Second syllabus version: owns its own 'acceleration' reference.
                'id' => 2, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => '2026-2028',
                'title' => 'Physics 5054 (2026-2028)', 'exam_years' => json_encode([2026]),
                'source_pdf_path' => 'storage/b.pdf', 'source_pdf_sha256' => str_repeat('b', 64),
                'structure_json' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('v2_learning_objectives')->insert([
            $this->lo(100, '1.2', 4, 20, 'Define acceleration as change in velocity per unit time; recall and use the equation acceleration = change in velocity / time taken; a = Δv / Δt'),
            $this->lo(101, '1.2', 13, 20, 'Calculate acceleration from the gradient of a speed–time graph'),
            $this->lo(102, '1.2', 5, 20, 'State what is meant by uniform acceleration', 'extracted'),
            $this->lo(110, '1.5.4', 1, 21, 'Describe, qualitatively, motion in a circular path due to a force perpendicular to the motion as:', 'validated', [
                ['type' => 'exclusion', 'text' => 'F = mv²/r is not required'],
                ['type' => 'depth', 'text' => 'qualitative treatment only'],
            ]),
        ]);

        DB::table('v2_syllabus_references')->insert([
            $this->ref(200, 1, 'quantity', 'acceleration', ['name' => 'acceleration', 'symbols' => ['a'], 'units' => ['m/s²']]),
            $this->ref(201, 1, 'quantity', 'time', ['name' => 'time', 'symbols' => ['t'], 'units' => ['s']]),
            $this->ref(202, 1, 'definition', 'acceleration', ['term' => 'acceleration', 'text' => 'change in velocity per unit time']),
            $this->ref(203, 1, 'relationship', 'acceleration_delta_v', [
                'word_form' => 'acceleration = change in velocity / time taken',
                'symbol_form' => 'a = Δv / Δt',
                'quantities' => ['acceleration', 'time'],
            ]),
            $this->ref(204, 1, 'term', 'gradient', ['term' => 'gradient']),
            $this->ref(205, 1, 'term', 'radioactivity', ['term' => 'radioactivity']), // mapped nowhere near 1.2
            $this->ref(206, 1, 'quantity', 'force', ['name' => 'force', 'symbols' => ['F'], 'units' => ['N']]),
            $this->ref(207, 1, 'definition', 'unreviewed', ['term' => 'x', 'text' => 'y'], 'extracted'),
            // Same key, DIFFERENT source/version - must never leak into source 1.
            $this->ref(300, 2, 'quantity', 'acceleration', ['name' => 'acceleration', 'symbols' => ['a'], 'units' => ['m/s²'], 'note' => 'v2026']),
        ]);

        DB::table('v2_syllabus_policies')->insert([
            [
                'syllabus_source_id' => 1, 'policy_type' => 'command_words', 'title' => 'Command words',
                'content' => json_encode(['words' => [
                    ['word' => 'Define', 'meaning' => 'give precise meaning'],
                    ['word' => 'Calculate', 'meaning' => 'work out from given facts'],
                    ['word' => 'Describe', 'meaning' => 'state the points of a topic'],
                    ['word' => 'Sketch', 'meaning' => 'make a simple freehand drawing'],
                ]]),
                'provenance' => json_encode(['pdf_pages' => [46]]), 'status' => 'validated',
                'review_note' => null, 'validated_by' => 't', 'validated_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'syllabus_source_id' => 1, 'policy_type' => 'graph_data_conventions', 'title' => 'Graphs',
                'content' => json_encode(['rules' => [
                    ['key' => 'g_gradient_triangle', 'text' => 'Gradient via a large triangle.'],
                    ['key' => 'g_axes_labels', 'text' => 'Label axes quantity / unit.'],
                    ['key' => 'tr_half_division', 'text' => 'Read to half a division.'],
                ]]),
                'provenance' => json_encode(['pdf_pages' => [44]]), 'status' => 'validated',
                'review_note' => null, 'validated_by' => 't', 'validated_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('v2_learning_objective_references')->insert([
            // 1.2 #4: defines the definition, requires the relationship (multi-ref LO).
            $this->map(100, 202, 'defines'),
            $this->map(100, 203, 'requires'),
            $this->map(100, 200, 'uses'),
            // 1.2 #13: REUSES acceleration quantity + gradient term (multi-LO reference).
            $this->map(101, 200, 'uses'),
            $this->map(101, 204, 'calculates_with'),
            ['learning_objective_id' => 101, 'syllabus_reference_id' => null, 'policy_type' => 'graph_data_conventions', 'policy_item_key' => 'g_gradient_triangle', 'role' => 'applies', 'note' => null, 'created_at' => now(), 'updated_at' => now()],
            // 1.5.4 #1.
            $this->map(110, 206, 'uses'),
            // Unvalidated reference mapped (must warn, never resolve).
            $this->map(101, 207, 'uses'),
        ]);
    }

    private function lo(int $id, string $section, int $number, int $subtopicId, string $text, string $status = 'validated', ?array $constraints = null): array
    {
        return [
            'id' => $id, 'syllabus_source_id' => 1, 'subtopic_id' => $subtopicId, 'topic_number' => 1,
            'section_code' => $section, 'section_title' => $section === '1.2' ? 'Motion' : 'Circular motion',
            'objective_number' => $number, 'text' => $text, 'sub_items' => null, 'equations' => null,
            'constraints' => $constraints ? json_encode($constraints) : null,
            'raw_extract' => null, 'provenance' => json_encode(['pdf_pages' => [13]]),
            'status' => $status, 'review_note' => null,
            'validated_by' => $status === 'validated' ? 't' : null,
            'validated_at' => $status === 'validated' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function ref(int $id, int $sourceId, string $type, string $key, array $payload, string $status = 'validated'): array
    {
        return [
            'id' => $id, 'syllabus_source_id' => $sourceId, 'reference_type' => $type, 'key' => $key,
            'title' => $payload['name'] ?? $payload['term'] ?? $key, 'payload' => json_encode($payload),
            'provenance' => json_encode(['learning_objective' => '1.2 #4', 'source_pdf_sha256' => str_repeat($sourceId === 1 ? 'a' : 'b', 64)]),
            'status' => $status, 'review_note' => null,
            'validated_by' => $status === 'validated' ? 't' : null,
            'validated_at' => $status === 'validated' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function map(int $loId, int $refId, string $role): array
    {
        return [
            'learning_objective_id' => $loId, 'syllabus_reference_id' => $refId,
            'policy_type' => null, 'policy_item_key' => null, 'role' => $role, 'note' => null,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function resolve(string $subtopicExt, array $numbers, bool $draft = false): array
    {
        return (new SyllabusReferenceResolver)->resolve(
            SyllabusSource::findOrFail(1),
            Subtopic::where('external_id', $subtopicExt)->firstOrFail(),
            $numbers,
            $draft,
        )['package'];
    }

    public function test_multi_lo_union_is_deduplicated_and_relevance_sliced(): void
    {
        $package = $this->resolve('1.2', [4, 13]);

        // acceleration quantity mapped by BOTH LOs appears exactly once (union dedup).
        $quantityKeys = array_column($package['references']['quantities'], 'key');
        $this->assertSame(['acceleration', 'time'], $quantityKeys); // time via transitive hop from the relationship
        $this->assertSame([['key' => 'gradient', 'title' => 'gradient', 'term' => 'gradient']],
            array_map(fn ($t) => array_intersect_key($t, array_flip(['key', 'title', 'term'])), $package['references']['terminology']));

        // Word + symbolic forms live in ONE canonical relationship row.
        $this->assertCount(1, $package['references']['relationships']);
        $this->assertSame('acceleration = change in velocity / time taken', $package['references']['relationships'][0]['word_form']);
        $this->assertSame('a = Δv / Δt', $package['references']['relationships'][0]['symbol_form']);

        // Irrelevant references (radioactivity, force) never leak in.
        $this->assertStringNotContainsString('radioactivity', json_encode($package));
        $this->assertNotContains('force', $quantityKeys);

        // Version scoping: the 2026-2028 source's same-key row never leaks (its note would show).
        $this->assertStringNotContainsString('v2026', json_encode($package));
    }

    public function test_lo_13_reuses_acceleration_without_duplicating_the_definition(): void
    {
        $package = $this->resolve('1.2', [13]);

        // #13 gets acceleration symbols/units (terminology + quantity reuse)...
        $this->assertSame('acceleration', $package['references']['quantities'][0]['key']);
        $this->assertSame(['a'], $package['references']['quantities'][0]['symbols']);
        $this->assertSame(['m/s²'], $package['references']['quantities'][0]['units']);
        // ...but does NOT own the definition (that belongs to #4's mapping).
        $this->assertSame([], $package['references']['definitions']);

        // Its requirements carry role semantics without duplicating payloads.
        $requirements = $package['scope']['selected_objectives'][0]['requirements'];
        $this->assertContains(['role' => 'calculates_with', 'target' => 'term:gradient', 'title' => 'gradient'], $requirements);
        $this->assertContains(['role' => 'uses', 'target' => 'quantity:acceleration', 'title' => 'acceleration'], $requirements);
    }

    public function test_policy_slices_resolve_only_mapped_items_and_derived_command_words(): void
    {
        $package = $this->resolve('1.2', [4, 13]);

        // Graph slice: exactly the mapped item - not the whole policy.
        $this->assertSame([['key' => 'g_gradient_triangle', 'text' => 'Gradient via a large triangle.']],
            $package['policy_slices']['graph_data_conventions']['items']);

        // Command words derived from validated LO wording: Define (#4), Calculate (#13) - not Sketch/Describe.
        $commandKeys = array_column($package['policy_slices']['command_words']['items'], 'key');
        $this->assertSame(['Calculate', 'Define'], $commandKeys);

        // Policy provenance (pdf pages) survives into the slice.
        $this->assertSame([44], $package['policy_slices']['graph_data_conventions']['provenance']['pdf_pages']);
    }

    public function test_unvalidated_references_warn_and_never_resolve(): void
    {
        $package = $this->resolve('1.2', [13]);

        $this->assertStringNotContainsString('definition:unreviewed', json_encode($package['references']));
        $this->assertNotEmpty(array_filter(
            $package['validation']['warnings'],
            fn ($w) => str_contains($w, 'definition:unreviewed') && str_contains($w, 'NOT validated')
        ));
    }

    public function test_unvalidated_objectives_are_refused_without_explicit_override(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/UNVALIDATED objectives/');

        $this->resolve('1.2', [4, 5]); // #5 is extracted-only
    }

    public function test_exclusion_is_a_hard_constraint_and_not_a_required_formula(): void
    {
        $package = $this->resolve('1.5.4', [1]);

        // The exclusion is visible as an LO-scoped hard bound...
        $this->assertSame(
            [['learning_objective' => '1.5.4 #1', 'text' => 'F = mv²/r is not required']],
            $package['hard_constraints']['exclusions']
        );
        $this->assertSame(
            [['learning_objective' => '1.5.4 #1', 'text' => 'qualitative treatment only']],
            $package['hard_constraints']['depth']
        );
        // ...and the excluded equation is NOT a resolvable relationship.
        $this->assertSame([], $package['references']['relationships']);
        $this->assertSame(['force'], array_column($package['references']['quantities'], 'key'));
    }

    public function test_guard_verdicts_excluded_required_unknown(): void
    {
        $guard = new SyllabusReferenceGuard;

        $excluded = $guard->expressionVerdict([110], 'F = mv²/r');
        $this->assertSame('excluded', $excluded['verdict']);
        $this->assertSame('1.5.4 #1', $excluded['matches'][0]['learning_objective']);

        // Normalization: delta/superscript/spacing variants still hit the exclusion.
        $this->assertSame('excluded', $guard->expressionVerdict([110], 'f=mv2/r')['verdict']);

        $this->assertSame('required', $guard->expressionVerdict([100], 'a = Δv / Δt')['verdict']);
        $this->assertSame('required', $guard->expressionVerdict([100], 'acceleration = change in velocity / time taken')['verdict']);
        $this->assertSame('mapped', $guard->expressionVerdict([101], 'acceleration')['verdict']); // role 'uses'
        $this->assertSame('unknown', $guard->expressionVerdict([100, 101, 110], 'F = ma')['verdict']);
    }

    public function test_guard_enumerates_requirements_and_flags_unresolved_mappings(): void
    {
        $guard = new SyllabusReferenceGuard;

        $required = $guard->enumerateRequired([100, 101]);
        $this->assertSame(['relationship:acceleration_delta_v'], $required['requires']);
        $this->assertSame(['term:gradient'], $required['calculates_with']);
        $this->assertSame(['definition:acceleration'], $required['defines']);

        $issues = $guard->unresolvedMappings([100, 101]);
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('definition:unreviewed', $issues[0]['issue']);

        $this->assertSame([], $guard->selectionIssues(SyllabusSource::findOrFail(1), 20, [4, 13]));
        $issues = $guard->selectionIssues(SyllabusSource::findOrFail(1), 20, [5, 99]);
        $this->assertStringContainsString('not validated', $issues[0]);
        $this->assertStringContainsString('does not exist', $issues[1]);
    }

    public function test_out_of_scope_siblings_and_provenance_hashes_are_represented(): void
    {
        $package = $this->resolve('1.2', [4]);

        $this->assertSame([5, 13], array_column($package['scope']['out_of_scope_objectives'], 'number'));
        $this->assertSame(str_repeat('a', 64), $package['scope']['syllabus']['source_pdf_sha256']);
        $this->assertSame(str_repeat('a', 64), $package['references']['definitions'][0]['provenance']['source_pdf_sha256']);
    }

    public function test_cross_subtopic_selection_is_rejected_and_existing_data_untouched(): void
    {
        // 1.5.4 has no objective #4 - selection must fail loudly.
        try {
            $this->resolve('1.5.4', [4]);
            $this->fail('expected rejection');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not found', $e->getMessage());
        }

        // The curriculum skeleton and question taxonomy are untouched by resolution.
        $this->assertSame(2, DB::table('v2_subtopics')->count());
        $this->assertSame(1, DB::table('v2_topics')->count());
        $this->assertFalse(Schema::hasColumn('v2_questions', 'learning_objective_id'));
    }

    public function test_resolution_is_deterministic(): void
    {
        $this->assertSame(
            json_encode($this->resolve('1.2', [4, 13])),
            json_encode($this->resolve('1.2', [4, 13])),
        );
    }
}
