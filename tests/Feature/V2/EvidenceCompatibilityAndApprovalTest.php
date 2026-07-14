<?php

namespace Tests\Feature\V2;

use App\Models\V2\EvidenceCompatibility;
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
 * Guards the version-migration seams: scoped prior-cycle evidence
 * compatibility (source-pair x leaf-section, status-gated, diff-hash stale
 * detection, explicit labelling, tier order) and the separated human
 * approvals (academic-foundation sign-off vs Stage A plan approval, each
 * hash-bound and independently stale-able).
 */
class EvidenceCompatibilityAndApprovalTest extends TestCase
{
    use DatabaseMigrations;

    private string $registryFixture;

    private string $shortlistDir;

    private string $misconceptionDir;

    private string $evidenceDir;

    private string $diffArtifact;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        DB::table('v2_subjects')->insert(['id' => 1, 'name' => 'Physics', 'code' => '5054', 'level' => 'O Level', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_topics')->insert(['id' => 10, 'subject_id' => 1, 'external_id' => '1', 'title' => 'Motion, forces and energy', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_subtopics')->insert([
            ['id' => 20, 'topic_id' => 10, 'external_id' => '1.2', 'title' => 'Motion', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'topic_id' => 10, 'external_id' => '1.3', 'title' => 'Mass and weight', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([[1, '2023-2025', [2023, 2024, 2025], 'a'], [2, '2026-2028', [2026, 2027, 2028], 'b']] as [$id, $version, $years, $ch]) {
            DB::table('v2_syllabus_sources')->insert([
                'id' => $id, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => $version,
                'title' => "Physics 5054 ({$version})", 'exam_years' => json_encode($years),
                'source_pdf_path' => "storage/{$ch}.pdf", 'source_pdf_sha256' => str_repeat($ch, 64),
                'structure_json' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Validated target LO + references under the NEW source (2) for 1.2 and 1.3.
        foreach ([[201, 20, '1.2'], [202, 22, '1.3']] as [$id, $subtopicId, $section]) {
            DB::table('v2_learning_objectives')->insert([
                'id' => $id, 'syllabus_source_id' => 2, 'subtopic_id' => $subtopicId, 'topic_number' => 1,
                'section_code' => $section, 'section_title' => 'x', 'objective_number' => 13,
                'text' => 'Calculate something from the gradient of a graph', 'sub_items' => null, 'equations' => null,
                'constraints' => null, 'raw_extract' => null, 'provenance' => null, 'status' => 'validated',
                'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('v2_syllabus_references')->insert([
            'id' => 300, 'syllabus_source_id' => 2, 'reference_type' => 'term', 'key' => 'gradient', 'title' => 'gradient',
            'payload' => json_encode(['term' => 'gradient']), 'provenance' => null, 'status' => 'validated',
            'review_note' => null, 'validated_by' => 't', 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([201, 202] as $loId) {
            DB::table('v2_learning_objective_references')->insert([
                'learning_objective_id' => $loId, 'syllabus_reference_id' => 300, 'policy_type' => null,
                'policy_item_key' => null, 'role' => 'calculates_with', 'note' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('v2_syllabus_policies')->insert([
            'syllabus_source_id' => 2, 'policy_type' => 'command_words', 'title' => 'Command words',
            'content' => json_encode(['words' => [['word' => 'Calculate', 'meaning' => 'work out']]]),
            'provenance' => null, 'status' => 'validated', 'review_note' => null, 'validated_by' => 't', 'validated_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Prior-cycle questions (2024/2025) in 1.2 and 1.3; none from 2026+ yet.
        foreach ([[600, 2024, 20], [601, 2025, 20], [610, 2025, 22]] as [$id, $year, $subtopicId]) {
            DB::table('v2_questions')->insert([
                'id' => $id, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => $subtopicId,
                'year' => $year, 'question_number' => $id - 599, 'question_text' => "Stem {$id}", 'layout_type' => 'text_only',
                'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => false,
                'source_paper' => "5054_s{$year}_qp_11", 'current_version_id' => $id * 10, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->registryFixture = tempnam(sys_get_temp_dir(), 'reg').'.js';
        file_put_contents($this->registryFixture, "export const registry = {\n    graph_explorer: () => import('./x.jsx'),\n};\n");
        $this->shortlistDir = $this->fixtureDir('short', ['1.2.json', '1.3.json'], ['widgets' => [
            ['key' => 'graph_explorer', 'capability' => 'x', 'relevant_to_objectives' => [13], 'demonstrates' => ['term:gradient'], 'formulas' => []],
        ]]);
        $this->misconceptionDir = $this->fixtureDir('misc', ['1.2.json', '1.3.json'], ['misconceptions' => [
            ['id' => 'misc.x', 'statement' => 's', 'affected_target_los' => ['1.2 #13'],
                'related_reference_handles' => ['term:gradient'], 'provenance_type' => 'curated_authoring', 'priority' => 'high'],
        ]]);
        $this->evidenceDir = $this->fixtureDir('evid', [], []);

        $this->diffArtifact = tempnam(sys_get_temp_dir(), 'diff').'.json';
        file_put_contents($this->diffArtifact, json_encode(['objectives' => 'identical']));
    }

    private function fixtureDir(string $prefix, array $files, array $data): string
    {
        $dir = sys_get_temp_dir()."/{$prefix}_".uniqid();
        mkdir($dir);
        foreach ($files as $file) {
            file_put_contents("{$dir}/{$file}", json_encode($data));
        }

        return $dir;
    }

    protected function tearDown(): void
    {
        @unlink($this->registryFixture);
        @unlink($this->diffArtifact);
        foreach ([$this->shortlistDir, $this->misconceptionDir, $this->evidenceDir] as $dir) {
            foreach (glob("{$dir}/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    private function compat(string $section = '1.2', string $status = 'active'): EvidenceCompatibility
    {
        return EvidenceCompatibility::create([
            'old_syllabus_source_id' => 1, 'new_syllabus_source_id' => 2, 'section_code' => $section,
            'basis' => ['section verified identical by version-gate diff'],
            'diff_artifact_path' => $this->diffArtifact,
            'diff_sha256' => hash_file('sha256', $this->diffArtifact),
            'status' => $status, 'reviewed_by' => 'test',
        ]);
    }

    private function packet(string $ext = '1.2'): array
    {
        return (new LessonPlanPacketCompiler(
            new SyllabusReferenceResolver,
            new SyllabusReferenceGuard,
            $this->registryFixture,
            $this->shortlistDir,
            $this->misconceptionDir,
            $this->evidenceDir,
        ))->compile(
            SyllabusSource::findOrFail(2),
            Subtopic::where('external_id', $ext)->firstOrFail(),
            [13],
        )['packet'];
    }

    public function test_prior_cycle_evidence_requires_an_active_section_scoped_compatibility(): void
    {
        // No record at all: 2026-2028 packet gets NO evidence (bank ends 2025).
        $this->assertSame([], $this->packet()['assessment_evidence']['questions']);

        // A merely-proposed record is not used.
        $record = $this->compat('1.2', 'proposed');
        $this->assertSame([], $this->packet()['assessment_evidence']['questions']);

        // Active: prior-cycle items flow in, newest first, explicitly labelled.
        $record->update(['status' => 'active']);
        $evidence = $this->packet()['assessment_evidence'];
        $this->assertSame([601, 600], array_column($evidence['questions'], 'evidence_id'));
        $item = $evidence['questions'][0];
        $this->assertSame('compatible_prior_cycle', $item['classification']);
        $this->assertSame(2025, $item['year']);                          // true paper identity preserved
        $this->assertSame('5054_s2025_qp_11 Q2', $item['question']);
        $this->assertSame('2026-2028', $item['compatibility']['authoring_syllabus_version']);
        $this->assertSame('2023-2025', $item['compatibility']['evidence_cycle']);
        $this->assertSame('1.2', $item['compatibility']['scope']);
        $this->assertSame('non-authoritative assessment evidence', $item['compatibility']['role']);
        $this->assertStringContainsString('evidence-compat#', $item['compatibility']['decision']);

        // Scope isolation: the 1.2 record does NOT authorize 1.3 evidence.
        $this->assertSame([], $this->packet('1.3')['assessment_evidence']['questions']);
    }

    public function test_stale_compatibility_basis_is_rejected(): void
    {
        $this->compat();
        $this->assertCount(2, $this->packet()['assessment_evidence']['questions']);

        // The diff artifact this decision rests on changes -> STALE -> withheld.
        file_put_contents($this->diffArtifact, json_encode(['objectives' => 'CHANGED']));
        $packet = $this->packet();
        $this->assertSame([], $packet['assessment_evidence']['questions']);
        $this->assertNotEmpty(array_filter(
            $packet['validation']['warnings'],
            fn ($w) => str_contains($w, 'STALE') && str_contains($w, 'prior-cycle evidence withheld')
        ));
    }

    public function test_selector_prefers_authoring_cycle_questions_when_they_exist(): void
    {
        $this->compat();
        DB::table('v2_questions')->insert([
            'id' => 700, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 20,
            'year' => 2026, 'question_number' => 50, 'question_text' => 'New cycle stem', 'layout_type' => 'text_only',
            'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => false,
            'source_paper' => '5054_s26_qp_11', 'current_version_id' => 7000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $questions = $this->packet()['assessment_evidence']['questions'];
        $this->assertSame([700, 601, 600], array_column($questions, 'evidence_id'));
        $this->assertSame('authoring_cycle', $questions[0]['classification']);
        $this->assertArrayNotHasKey('compatibility', $questions[0]);
        $this->assertSame('compatible_prior_cycle', $questions[1]['classification']);
    }

    public function test_curated_includes_pass_compatibility_and_safety(): void
    {
        $this->compat();
        // 601 already in base; curate 600 explicitly? 600 also in base (pool of 2).
        // Add a curated-only prior-cycle question and a dirty one.
        DB::table('v2_questions')->insert([
            ['id' => 602, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 20,
                'year' => 2023, 'question_number' => 3, 'question_text' => 'Old but curated', 'layout_type' => 'text_only',
                'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => false,
                'source_paper' => '5054_s23_qp_11', 'current_version_id' => 6020, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 699, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 20,
                'year' => 2024, 'question_number' => 99, 'question_text' => 'Dirty', 'layout_type' => 'text_only',
                'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => true,
                'source_paper' => '5054_s24_qp_11', 'current_version_id' => 6990, 'created_at' => now(), 'updated_at' => now()],
        ]);
        // 550 sits OUTSIDE the compatibility window (2022 < old cycle 2023) and
        // must be rejected even when curated.
        DB::table('v2_questions')->insert([
            'id' => 550, 'paper_id' => 1, 'subject_id' => 1, 'topic_id' => 10, 'subtopic_id' => 20,
            'year' => 2022, 'question_number' => 4, 'question_text' => 'Pre-window', 'layout_type' => 'text_only',
            'correct_answer' => 'B', 'marks' => 1, 'status' => 'active', 'needs_review' => false,
            'source_paper' => '5054_s22_qp_11', 'current_version_id' => 5500, 'created_at' => now(), 'updated_at' => now(),
        ]);
        file_put_contents("{$this->evidenceDir}/1.2.json", json_encode(['include' => [
            ['evidence_id' => 602, 'reason' => 'd-t gradient coverage'],
            ['evidence_id' => 699, 'reason' => 'must be rejected (needs_review)'],
            ['evidence_id' => 550, 'reason' => 'must be rejected (outside compat window)'],
        ]]));

        $packet = $this->packet();
        $ids = array_column($packet['assessment_evidence']['questions'], 'evidence_id');
        // 602 is present and correctly labelled prior-cycle (it enters via the
        // tier-2 base when the pool is under the cap; curation would add it with
        // a curated reason only when the base cap excludes it).
        $this->assertContains(602, $ids);
        $curated = collect($packet['assessment_evidence']['questions'])->firstWhere('evidence_id', 602);
        $this->assertSame('compatible_prior_cycle', $curated['classification']);
        $this->assertNotContains(699, $ids); // safety filters still absolute
        $this->assertNotContains(550, $ids); // compat window still absolute
        $this->assertNotEmpty(array_filter(
            $packet['validation']['warnings'],
            fn ($w) => str_contains($w, 'Curated evidence 550')
        ));
    }

    public function test_foundation_signoff_and_plan_approval_are_independent_and_stale_correctly(): void
    {
        $this->compat();
        $dir = sys_get_temp_dir().'/appr_'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/packet.json", json_encode($this->packet()));
        file_put_contents("{$dir}/plan.json", json_encode(['plan_schema' => 'topicaled.stage-a-plan.v2', 'target_los' => ['1.2 #13']]));

        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet.json", '--reviewer' => 'umair'])->assertSuccessful();
        $this->artisan('v2:approve-stage-a-plan', ['plan' => "{$dir}/plan.json", 'packet' => "{$dir}/packet.json", '--reviewer' => 'umair'])->assertSuccessful();
        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet.json", '--verify' => true])->assertSuccessful();
        $this->artisan('v2:approve-stage-a-plan', ['plan' => "{$dir}/plan.json", 'packet' => "{$dir}/packet.json", '--verify' => true])->assertSuccessful();

        // Plan edit stales ONLY the plan approval.
        file_put_contents("{$dir}/plan.json", json_encode(['plan_schema' => 'topicaled.stage-a-plan.v2', 'target_los' => ['1.2 #13'], 'edited' => true]));
        $this->artisan('v2:approve-stage-a-plan', ['plan' => "{$dir}/plan.json", 'packet' => "{$dir}/packet.json", '--verify' => true])->assertFailed();
        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet.json", '--verify' => true])->assertSuccessful();

        // Canonical truth change stales BOTH (via recompiled packet fingerprint).
        DB::table('v2_syllabus_references')->where('id', 300)->update(['title' => 'gradient (slope)']);
        file_put_contents("{$dir}/packet2.json", json_encode($this->packet()));
        $this->artisan('v2:signoff-lesson-foundation', ['packet' => "{$dir}/packet2.json", '--verify' => true])->assertFailed();
        file_put_contents("{$dir}/plan.json", json_encode(['plan_schema' => 'topicaled.stage-a-plan.v2', 'target_los' => ['1.2 #13']]));
        $this->artisan('v2:approve-stage-a-plan', ['plan' => "{$dir}/plan.json", 'packet' => "{$dir}/packet2.json", '--verify' => true])->assertFailed();

        foreach (glob("{$dir}/*") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
