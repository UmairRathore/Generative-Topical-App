<?php

namespace Tests\Feature\V2;

use App\Models\V2\ReviewSignoff;
use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\LessonPlanPacketCompiler;
use App\Services\V2\SyllabusReferenceGuard;
use App\Services\V2\SyllabusReferenceResolver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the Stage A acceptance-gate additions: the curated assessment-
 * evidence include (deterministic base + file-driven curation with reason +
 * provenance) and the hash-bound, scoped human sign-off (fingerprint goes
 * stale when the reviewed foundation changes; unrelated content never
 * affects it; row-level provenance is never rewritten).
 */
class StageAAcceptanceGateTest extends TestCase
{
    use DatabaseMigrations;

    private string $registryFixture;

    private string $shortlistDir;

    private string $misconceptionDir;

    private string $evidenceDir;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        DB::table('v2_subjects')->insert(['id' => 1, 'name' => 'Physics', 'code' => '5054', 'level' => 'O Level', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_topics')->insert(['id' => 10, 'subject_id' => 1, 'external_id' => '1', 'title' => 'Motion, forces and energy', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_subtopics')->insert(['id' => 20, 'topic_id' => 10, 'external_id' => '1.2', 'title' => 'Motion', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_syllabus_sources')->insert([
            'id' => 1, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => '2023-2025',
            'title' => 'Physics 5054 (2023-2025)', 'exam_years' => json_encode([2023, 2024, 2025]),
            'source_pdf_path' => 'storage/a.pdf', 'source_pdf_sha256' => str_repeat('a', 64),
            'structure_json' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_learning_objectives')->insert([
            'id' => 101, 'syllabus_source_id' => 1, 'subtopic_id' => 20, 'topic_number' => 1,
            'section_code' => '1.2', 'section_title' => 'Motion', 'objective_number' => 13,
            'text' => 'Calculate acceleration from the gradient of a speed–time graph',
            'sub_items' => null, 'equations' => null, 'constraints' => null, 'raw_extract' => null,
            'provenance' => null, 'status' => 'validated', 'review_note' => null,
            'validated_by' => 'fable-curation', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_syllabus_references')->insert([
            'id' => 204, 'syllabus_source_id' => 1, 'reference_type' => 'term', 'key' => 'gradient', 'title' => 'gradient',
            'payload' => json_encode(['term' => 'gradient']), 'provenance' => json_encode(['learning_objective' => '1.2 #11']),
            'status' => 'validated', 'review_note' => null, 'validated_by' => 'fable-curation', 'validated_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_learning_objective_references')->insert([
            'learning_objective_id' => 101, 'syllabus_reference_id' => 204, 'policy_type' => null,
            'policy_item_key' => null, 'role' => 'calculates_with', 'note' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_syllabus_policies')->insert([
            'syllabus_source_id' => 1, 'policy_type' => 'command_words', 'title' => 'Command words',
            'content' => json_encode(['words' => [['word' => 'Calculate', 'meaning' => 'work out']]]),
            'provenance' => null, 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 10 clean era questions: newest-first base cap (8) excludes the two
        // oldest/lowest ids (500, 501) - curation can then re-include one.
        foreach (range(500, 509) as $id) {
            DB::table('v2_questions')->insert([
                'id' => $id, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 20,
                'year' => $id < 505 ? 2023 : 2024, 'question_number' => $id - 499, 'question_text' => "Stem {$id}",
                'layout_type' => 'text_only', 'correct_answer' => 'B', 'marks' => 1, 'status' => 'active',
                'needs_review' => false, 'source_paper' => '5054_s2x_qp_11', 'current_version_id' => $id * 10,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // A dirty question that curation must NOT be able to include.
        DB::table('v2_questions')->insert([
            'id' => 599, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 20,
            'year' => 2024, 'question_number' => 99, 'question_text' => 'Dirty', 'layout_type' => 'text_only',
            'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => true,
            'source_paper' => '5054_s24_qp_11', 'current_version_id' => 5990, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->registryFixture = tempnam(sys_get_temp_dir(), 'reg').'.js';
        file_put_contents($this->registryFixture, "export const registry = {\n    graph_explorer: () => import('./x.jsx'),\n};\n");
        $this->shortlistDir = $this->fixtureDir('short', '1.2.json', ['widgets' => [
            ['key' => 'graph_explorer', 'capability' => 'x', 'relevant_to_objectives' => [13], 'demonstrates' => ['term:gradient'], 'formulas' => []],
        ]]);
        $this->misconceptionDir = $this->fixtureDir('misc', '1.2.json', ['misconceptions' => [
            ['id' => 'misc.x', 'statement' => 's', 'affected_target_los' => ['1.2 #13'],
                'related_reference_handles' => ['term:gradient'], 'provenance_type' => 'curated_authoring', 'priority' => 'high'],
        ]]);
        // Base picks 2024s (505-509) then 2023s by id (500,501,502) - so 503 is
        // genuinely outside the newest-first cap and curation re-includes it.
        $this->evidenceDir = $this->fixtureDir('evid', '1.2.json', ['include' => [
            ['evidence_id' => 503, 'reason' => 'd-t gradient coverage gap'],
            ['evidence_id' => 599, 'reason' => 'must be rejected (needs_review)'],
        ]]);
    }

    private function fixtureDir(string $prefix, string $file, array $data): string
    {
        $dir = sys_get_temp_dir()."/{$prefix}_".uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/{$file}", json_encode($data));

        return $dir;
    }

    protected function tearDown(): void
    {
        @unlink($this->registryFixture);
        foreach ([$this->shortlistDir, $this->misconceptionDir, $this->evidenceDir] as $dir) {
            foreach (glob("{$dir}/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    private function packet(): array
    {
        return (new LessonPlanPacketCompiler(
            new SyllabusReferenceResolver,
            new SyllabusReferenceGuard,
            $this->registryFixture,
            $this->shortlistDir,
            $this->misconceptionDir,
            $this->evidenceDir,
        ))->compile(SyllabusSource::findOrFail(1), Subtopic::findOrFail(20), [13])['packet'];
    }

    public function test_curated_evidence_is_included_with_reason_provenance_and_safety_filters(): void
    {
        $packet = $this->packet();
        $evidence = $packet['assessment_evidence'];

        // Base = newest-first 8 (505-509 + 500-502); curated re-includes 503.
        $this->assertCount(9, $evidence['questions']);
        $this->assertContains(503, $evidence['allowed_evidence_ids']);
        $curated = collect($evidence['questions'])->firstWhere('evidence_id', 503);
        $this->assertSame('curated: d-t gradient coverage gap', $curated['reason_included']);
        $this->assertStringContainsString('1.2.json@', $evidence['curation']); // file provenance

        // The dirty curated id is rejected by the base safety filters, loudly.
        $this->assertNotContains(599, $evidence['allowed_evidence_ids']);
        $this->assertNotEmpty(array_filter(
            $packet['validation']['warnings'],
            fn ($w) => str_contains($w, 'Curated evidence 599')
        ));

        // Determinism holds with curation active (modulo the expected warning).
        $this->assertSame(json_encode($this->packet()), json_encode($packet));
    }

    public function test_signoff_fingerprint_is_stable_scoped_and_goes_stale_on_content_change(): void
    {
        $packet = $this->packet();
        $fingerprint = ReviewSignoff::foundationFingerprint($packet);

        // Stable across identical recompiles.
        $this->assertSame($fingerprint, ReviewSignoff::foundationFingerprint($this->packet()));

        // Scope string identifies exactly this lesson foundation.
        $this->assertSame('stage-a-foundation:5054@2023-2025:1.2:13', ReviewSignoff::scopeFor($packet));

        // Unrelated content (another source's reference) does NOT invalidate it.
        DB::table('v2_syllabus_sources')->insert([
            'id' => 2, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => '2026-2028',
            'title' => 'x', 'exam_years' => json_encode([2026]), 'source_pdf_path' => 'storage/b.pdf',
            'source_pdf_sha256' => str_repeat('b', 64), 'structure_json' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_syllabus_references')->insert([
            'syllabus_source_id' => 2, 'reference_type' => 'term', 'key' => 'gradient', 'title' => 'gradient v2',
            'payload' => json_encode(['term' => 'gradient']), 'provenance' => null, 'status' => 'validated',
            'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame($fingerprint, ReviewSignoff::foundationFingerprint($this->packet()));

        // Changing a reviewed canonical entry changes the fingerprint -> STALE.
        DB::table('v2_syllabus_references')->where('id', 204)->update(['title' => 'gradient (slope)']);
        $this->assertNotSame($fingerprint, ReviewSignoff::foundationFingerprint($this->packet()));

        // Row-level provenance was never rewritten by any of this.
        $this->assertSame('fable-curation', DB::table('v2_syllabus_references')->where('id', 204)->value('validated_by'));
    }

    public function test_signoff_command_sign_and_verify_lifecycle(): void
    {
        $dir = sys_get_temp_dir().'/signoff_'.uniqid();
        mkdir($dir);
        // Sign a CLEAN packet: drop the intentionally-rejected curated id so the
        // packet carries zero warnings (signing refuses warned packets by design).
        file_put_contents("{$this->evidenceDir}/1.2.json", json_encode(['include' => [
            ['evidence_id' => 503, 'reason' => 'd-t gradient coverage gap'],
        ]]));
        file_put_contents("{$dir}/packet.json", json_encode($this->packet()));

        // Verify before any sign-off -> NONE (failure).
        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet.json", '--verify' => true])->assertFailed();
        // Signing requires a human reviewer identity.
        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet.json"])->assertFailed();

        $this->artisan('v2:signoff-lesson-foundation', [
            'packet' => "{$dir}/packet.json", '--reviewer' => 'umair', '--notes' => 'reviewed pack',
        ])->assertSuccessful();
        $this->assertSame(1, ReviewSignoff::count());
        $this->assertSame('umair', ReviewSignoff::first()->reviewer);

        // Still-valid content verifies OK.
        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet.json", '--verify' => true])->assertSuccessful();

        // Underlying canonical change -> recompiled packet -> STALE (failure).
        DB::table('v2_syllabus_references')->where('id', 204)->update(['title' => 'gradient (slope)']);
        file_put_contents("{$dir}/packet2.json", json_encode($this->packet()));
        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet2.json", '--verify' => true])->assertFailed();

        @unlink("{$dir}/packet.json");
        @unlink("{$dir}/packet2.json");
        @rmdir($dir);
    }
}
