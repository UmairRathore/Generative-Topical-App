<?php

namespace Tests\Feature\V2;

use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\AuthoringContextBuilder;
use App\Services\V2\SyllabusReferenceResolver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Guards the trust and determinism contract of the lesson-authoring context:
 *  - unvalidated learning objectives are REFUSED (Fable never sees untrusted
 *    curriculum) unless explicitly overridden, and then loudly flagged;
 *  - only validated policies and approved/edited assets enter the context
 *    (drafts appear as counts only, never content);
 *  - identical inputs produce byte-identical context (determinism);
 *  - unselected same-leaf objectives are surfaced as out-of-scope.
 */
class AuthoringContextBuilderTest extends TestCase
{
    use DatabaseMigrations;

    private string $registryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

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
            'id' => 21, 'topic_id' => 10, 'external_id' => '1.5.4', 'title' => 'Circular motion',
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('v2_syllabus_sources')->insert([
            'id' => 1, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => '2023-2025',
            'title' => 'Cambridge O Level Physics 5054 syllabus for 2023, 2024 and 2025',
            'exam_years' => json_encode([2023, 2024, 2025]),
            'source_pdf_path' => 'storage/x.pdf', 'source_pdf_sha256' => str_repeat('a', 64),
            'structure_json' => json_encode([
                'topics' => [1 => 'Motion, forces and energy'],
                'sections' => [
                    ['code' => '1.5', 'title' => 'Forces', 'topic' => 1, 'leaf' => false, 'lo_count' => 0],
                    ['code' => '1.5.4', 'title' => 'Circular motion', 'topic' => 1, 'leaf' => true, 'lo_count' => 2],
                ],
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('v2_learning_objectives')->insert([
            [
                'id' => 100, 'syllabus_source_id' => 1, 'subtopic_id' => 21, 'topic_number' => 1,
                'section_code' => '1.5.4', 'section_title' => 'Circular motion', 'objective_number' => 1,
                'text' => 'Describe, qualitatively, motion in a circular path due to a force perpendicular to the motion as:',
                'sub_items' => json_encode([['key' => 'a', 'text' => 'speed increases if force increases']]),
                'equations' => null,
                'constraints' => json_encode([
                    ['type' => 'exclusion', 'text' => 'F = mv²/r is not required'],
                    ['type' => 'depth', 'text' => 'qualitative treatment only'],
                ]),
                'raw_extract' => null, 'provenance' => json_encode(['pdf_pages' => [15]]),
                'status' => 'validated', 'review_note' => null,
                'validated_by' => 'test-reviewer', 'validated_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 101, 'syllabus_source_id' => 1, 'subtopic_id' => 21, 'topic_number' => 1,
                'section_code' => '1.5.4', 'section_title' => 'Circular motion', 'objective_number' => 2,
                'text' => 'A second, machine-extracted-only objective',
                'sub_items' => null, 'equations' => null, 'constraints' => null,
                'raw_extract' => null, 'provenance' => null,
                'status' => 'extracted', 'review_note' => null,
                'validated_by' => null, 'validated_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('v2_syllabus_policies')->insert([
            [
                'syllabus_source_id' => 1, 'policy_type' => 'command_words', 'title' => 'Command words',
                'content' => json_encode(['words' => [['word' => 'Describe', 'meaning' => 'state the points of a topic']]]),
                'provenance' => null, 'status' => 'validated', 'review_note' => null,
                'validated_by' => 'test-reviewer', 'validated_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'syllabus_source_id' => 1, 'policy_type' => 'symbols_units', 'title' => 'Symbols and units',
                'content' => json_encode(['quantities' => []]),
                'provenance' => null, 'status' => 'extracted', 'review_note' => null,
                'validated_by' => null, 'validated_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // Two questions in the leaf; one approved + one draft asset on the first.
        DB::table('v2_questions')->insert([
            [
                'id' => 500, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 21,
                'year' => 2019, 'question_number' => 7, 'question_text' => 'Q text', 'layout_type' => 'text_only',
                'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => false,
                'source_paper' => '5054_s19_qp_11', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 501, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 21,
                'year' => 2024, 'question_number' => 8, 'question_text' => 'Q text 2', 'layout_type' => 'question_diagram',
                'correct_answer' => null, 'marks' => 1, 'status' => 'active', 'needs_review' => true,
                'source_paper' => '5054_s24_qp_12', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        DB::table('v2_question_learning_assets')->insert([
            [
                'question_id' => 500, 'asset_type' => 'worked_solution', 'asset_key' => '',
                'title' => 'Worked solution', 'content' => 'Step 1 ... Step 2 ...', 'payload_json' => null,
                'format' => 'markdown', 'status' => 'approved', 'source_hash' => null,
                'generated_by' => 'test', 'reviewed_by' => null, 'reviewed_at' => null, 'approved_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'question_id' => 500, 'asset_type' => 'flashcards', 'asset_key' => 'set',
                'title' => 'Cards', 'content' => 'DRAFT CONTENT MUST NOT LEAK', 'payload_json' => null,
                'format' => 'json', 'status' => 'draft', 'source_hash' => null,
                'generated_by' => 'test', 'reviewed_by' => null, 'reviewed_at' => null, 'approved_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // Widget registry fixture: the builder must read type keys from the JS file.
        $this->registryFixture = tempnam(sys_get_temp_dir(), 'registry').'.js';
        file_put_contents($this->registryFixture, <<<'JS'
export const registry = {
    graph_explorer: () => import('./widgets/GraphExplorer.jsx'),
    circular_motion: () => import('./widgets/CircularMotion.jsx'),
    // commented_out: () => import('./widgets/Nope.jsx'),
};
JS);
    }

    protected function tearDown(): void
    {
        @unlink($this->registryFixture);
        parent::tearDown();
    }

    private function build(array $numbers = [], bool $includeDraft = false): array
    {
        return (new AuthoringContextBuilder(new SyllabusReferenceResolver, $this->registryFixture))->build(
            SyllabusSource::findOrFail(1),
            Subtopic::findOrFail(21),
            $numbers,
            $includeDraft,
        );
    }

    public function test_refuses_unvalidated_objectives_by_default(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/UNVALIDATED objectives/');

        $this->build([1, 2]); // #2 is only machine-extracted
    }

    public function test_builds_canonical_context_from_validated_objectives_only(): void
    {
        $result = $this->build(); // no explicit numbers => all validated (just #1)
        $context = $result['context'];

        // Canonical LO with structured constraints, sub-items and provenance.
        $this->assertCount(1, $context['learning_objectives']);
        $lo = $context['learning_objectives'][0];
        $this->assertSame('1.5.4 #1', $lo['reference']);
        $this->assertSame([15], $lo['pdf_pages']);
        // Constraints surface as LO-tagged hard bounds (v2 shape).
        $this->assertSame(
            [['learning_objective' => '1.5.4 #1', 'text' => 'F = mv²/r is not required']],
            $context['hard_constraints']['exclusions']
        );
        $this->assertSame(
            [['learning_objective' => '1.5.4 #1', 'text' => 'qualitative treatment only']],
            $context['hard_constraints']['depth']
        );

        // The unvalidated sibling is out of scope, visibly flagged as extracted.
        $this->assertSame(2, $context['out_of_scope_objectives'][0]['number']);
        $this->assertSame('extracted', $context['out_of_scope_objectives'][0]['status']);

        // Intermediate header recovered from the source structure map.
        $this->assertSame(['section_code' => '1.5', 'title' => 'Forces'], $context['curriculum']['parent_section']);

        // v2: broad policies are gone - only the derived command-word slice
        // (Describe appears in the validated LO wording; the extracted
        // symbols_units policy contributes nothing because nothing maps to it).
        $this->assertSame(['command_words'], array_keys($context['policy_slices']));
        $this->assertSame(['Describe'], array_column($context['policy_slices']['command_words']['items'], 'key'));
        // No reference rows are seeded in this fixture - empty, never invented.
        $this->assertSame([], $context['academic_references']['relationships']);
        $this->assertSame([], $context['academic_references']['quantities']);

        // Approved asset content inlined; draft content only ever a count.
        $this->assertCount(1, $context['internal_assets']['approved']);
        $this->assertSame('worked_solution', $context['internal_assets']['approved'][0]['asset_type']);
        $this->assertSame(['flashcards' => 1], $context['internal_assets']['draft_counts_by_type']);
        $this->assertStringNotContainsString('DRAFT CONTENT MUST NOT LEAK', json_encode($context));

        // Question corpus counts + rows (correct answers are allowed here: this
        // is server-side authoring input, never student-facing).
        $this->assertSame(2, $context['question_corpus']['counts']['total']);
        $this->assertSame(1, $context['question_corpus']['counts']['answerable']);
        $this->assertSame('B', $context['question_corpus']['questions'][0]['correct_answer']);

        // Widget vocabulary parsed from the JS registry, commented entries excluded.
        $this->assertSame(['circular_motion', 'graph_explorer'], $context['widgets']['types']);

        // The precedence doctrine and authoring rules ride along in every context.
        $this->assertSame('official_versioned_syllabus', $context['source_precedence'][0]['source']);
        $this->assertSame('fable_generated_prose', $context['source_precedence'][4]['source']);
        $this->assertTrue($context['validation']['objectives_all_validated']);

        // Audit manifest references every included item.
        $types = array_count_values(array_column($result['manifest'], 'item_type'));
        $this->assertSame(1, $types['syllabus_source']);
        $this->assertSame(1, $types['learning_objective']);
        $this->assertSame(1, $types['policy_slice']);
        $this->assertSame(1, $types['learning_asset']);
    }

    public function test_draft_override_is_explicit_and_loudly_flagged(): void
    {
        $context = $this->build([1, 2], includeDraft: true)['context'];

        $this->assertCount(2, $context['learning_objectives']);
        $this->assertFalse($context['validation']['objectives_all_validated']);
        $this->assertSame('extracted', $context['learning_objectives'][1]['status']);
        $this->assertNotEmpty(array_filter(
            $context['validation']['warnings'],
            fn ($w) => str_contains($w, 'UNVALIDATED objectives included by explicit override')
        ));
    }

    public function test_context_is_deterministic(): void
    {
        $first = json_encode($this->build()['context']);
        $second = json_encode($this->build()['context']);

        $this->assertSame($first, $second);
    }

    public function test_unknown_objective_numbers_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->build([1, 99], includeDraft: true);
    }
}
