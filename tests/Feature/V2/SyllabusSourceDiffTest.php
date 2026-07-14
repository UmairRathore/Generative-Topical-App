<?php

namespace Tests\Feature\V2;

use App\Models\V2\ReviewSignoff;
use App\Models\V2\SyllabusSource;
use App\Services\V2\SyllabusSourceDiff;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the version-gate diff engine: deterministic classification tiers
 * (exact / formatting-only / text-changed / added / removed / moved),
 * exclusion-bound surfacing, source coexistence, and the cross-version
 * sign-off isolation (a 2023-2025 sign-off can never validate 2026-2028).
 */
class SyllabusSourceDiffTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        DB::table('v2_subjects')->insert(['id' => 1, 'name' => 'Physics', 'code' => '5054', 'level' => 'O Level', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[1, '2023-2025', 'a'], [2, '2026-2028', 'b']] as [$id, $version, $ch]) {
            DB::table('v2_syllabus_sources')->insert([
                'id' => $id, 'subject_id' => 1, 'syllabus_code' => '5054', 'version_label' => $version,
                'title' => "Physics 5054 ({$version})", 'exam_years' => json_encode([2023]),
                'source_pdf_path' => "storage/{$ch}.pdf", 'source_pdf_sha256' => str_repeat($ch, 64),
                'structure_json' => json_encode([
                    'topics' => [1 => 'Motion, forces and energy'],
                    'sections' => [['code' => '1.2', 'title' => $id === 1 ? 'Motion' : 'Mo tion', 'topic' => 1, 'leaf' => true, 'lo_count' => 4]],
                ]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $lo = fn ($id, $src, $sec, $n, $text, $subs = null, $raw = null) => [
            'id' => $id, 'syllabus_source_id' => $src, 'subtopic_id' => null, 'topic_number' => 1,
            'section_code' => $sec, 'section_title' => 'Motion', 'objective_number' => $n, 'text' => $text,
            'sub_items' => $subs ? json_encode($subs) : null, 'equations' => null, 'constraints' => null,
            'raw_extract' => $raw ? json_encode(['text' => $raw]) : null, 'provenance' => json_encode(['pdf_pages' => [13]]),
            'status' => 'extracted', 'review_note' => null, 'validated_by' => null, 'validated_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ];

        DB::table('v2_learning_objectives')->insert([
            // exact match
            $lo(1, 1, '1.2', 1, 'Define speed as distance travelled per unit time'),
            $lo(11, 2, '1.2', 1, 'Define speed as distance travelled per unit time'),
            // formatting-only (intra-word spacing artifact)
            $lo(2, 1, '1.2', 2, 'Recall and use the equation Ek = 1/2 mv2'),
            $lo(12, 2, '1.2', 2, 'Recall and use the equation Ek = 1 /2 mv2'),
            // real text change
            $lo(3, 1, '1.2', 3, 'Sketch distance–time graphs'),
            $lo(13, 2, '1.2', 3, 'Sketch and interpret distance–time graphs'),
            // removed from new (with an exclusion bound that disappears)
            $lo(4, 1, '1.2', 4, 'Old objective (F = mv2/r is not required)'),
            // added in new (a new exclusion bound appears)
            $lo(14, 2, '1.2', 5, 'New objective (limited to uniform acceleration only)'),
            // moved: identical text under a new reference
            $lo(5, 1, '1.3', 1, 'State that mass resists change of motion'),
            $lo(15, 2, '1.4', 1, 'State that mass resists change of motion'),
        ]);
    }

    public function test_diff_classifies_all_tiers_deterministically(): void
    {
        $old = SyllabusSource::findOrFail(1);
        $new = SyllabusSource::findOrFail(2);
        $result = (new SyllabusSourceDiff)->compare($old, $new);

        $this->assertSame(['1.2#1'], $result['objectives']['unchanged_exact']);
        $this->assertSame('1.2 #2', $result['objectives']['formatting_only_changed'][0]['old_reference']);

        // Real wording change is NEVER silently exact - flagged for manual review.
        $changed = $result['objectives']['text_changed'][0];
        $this->assertSame('1.2 #3', $changed['old_reference']);
        $this->assertSame('ambiguous_manual_review', $changed['review_status']);
        $this->assertSame('Sketch distance–time graphs', $changed['old_text']);
        $this->assertSame('Sketch and interpret distance–time graphs', $changed['new_text']);

        // Moved (identical text, new reference) is not double-counted as add+remove.
        $this->assertSame('1.3 #1', $result['objectives']['moved_exact'][0]['old_reference']);
        $this->assertSame('1.4 #1', $result['objectives']['moved_exact'][0]['new_reference']);
        $this->assertSame(['1.2 #4'], array_column($result['objectives']['removed'], 'old_reference'));
        $this->assertSame(['1.2 #5'], array_column($result['objectives']['added'], 'new_reference'));

        // Exclusion bounds surfaced both directions.
        $this->assertSame('limited to uniform acceleration only', $result['exclusions']['added'][0]['bound']);
        $this->assertSame('F = mv2/r is not required', $result['exclusions']['removed'][0]['bound']);

        // Whitespace-only section title difference is NOT a rename.
        $this->assertSame([], $result['sections']['renamed']);

        // Deterministic: identical output on re-run; sources untouched.
        $this->assertSame(json_encode($result), json_encode((new SyllabusSourceDiff)->compare($old, $new)));
        $this->assertSame(str_repeat('a', 64), $old->fresh()->source_pdf_sha256);
        $this->assertSame(2, SyllabusSource::count()); // both versions coexist
    }

    public function test_cross_version_signoff_isolation(): void
    {
        // Minimal packet skeletons differing ONLY in source identity.
        $packet = fn (string $version, string $sha) => [
            'identity' => ['syllabus_code' => '5054', 'syllabus_version' => $version, 'source_pdf_sha256' => $sha,
                'leaf_section' => ['section_code' => '1.2', 'title' => 'Motion']],
            'target_coverage' => ['objectives' => [['reference' => '1.2 #13', 'number' => 13]]],
            'out_of_scope_curriculum' => ['objectives' => []],
            'direct_academic_truth' => [], 'supporting_academic_truth' => ['references' => []],
            'allowed_reference_handles' => ['direct' => [], 'supporting' => []],
            'hard_constraints' => [], 'policy_slices' => [], 'pedagogical_evidence' => [], 'relevant_widgets' => [],
        ];

        $old = $packet('2023-2025', str_repeat('a', 64));
        $new = $packet('2026-2028', str_repeat('b', 64));

        // Same targets, same (empty) truth - but the version identity alone
        // yields a different fingerprint AND a different scope.
        $this->assertNotSame(ReviewSignoff::foundationFingerprint($old), ReviewSignoff::foundationFingerprint($new));
        $this->assertSame('stage-a-foundation:5054@2023-2025:1.2:13', ReviewSignoff::scopeFor($old));
        $this->assertSame('stage-a-foundation:5054@2026-2028:1.2:13', ReviewSignoff::scopeFor($new));

        // A recorded 2023-2025 sign-off never matches the 2026-2028 scope.
        ReviewSignoff::create([
            'syllabus_source_id' => 1, 'scope' => ReviewSignoff::scopeFor($old), 'reviewer' => 'umair',
            'packet_sha256' => hash('sha256', json_encode($old)),
            'foundation_fingerprint' => ReviewSignoff::foundationFingerprint($old), 'signed_at' => now(),
        ]);
        $this->assertNull(ReviewSignoff::where('scope', ReviewSignoff::scopeFor($new))->first());
        // And the historical record itself remains intact and valid for old content.
        $this->assertSame(ReviewSignoff::foundationFingerprint($old), ReviewSignoff::first()->foundation_fingerprint);
    }
}
