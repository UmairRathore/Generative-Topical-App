<?php

namespace App\Services\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\SyllabusSource;
use Illuminate\Support\Collection;

/**
 * Deterministic structural comparison of two registered syllabus sources
 * (e.g. 5054@2023-2025 vs 5054@2026-2028). Pure data/text comparison - no
 * LLM. Semantic judgement on flagged rows is a separate, explicitly-marked
 * offline review step (agent_reviewed_pending_human_signoff).
 *
 * Objective classification (deterministic tiers):
 *  - unchanged_exact          raw texts identical (trimmed)
 *  - formatting_only_changed  identical once ALL whitespace is stripped
 *                             (PDF extraction artifacts like "Wo r k")
 *  - subitems_changed         text equal but (a)(b)(c) sub-items differ
 *  - text_changed             same official reference, different words ->
 *                             ambiguous_manual_review by definition
 *  - moved_exact              same (whitespace-stripped) text under a
 *                             different official reference
 *  - added / removed          reference exists on one side only
 *
 * Exclusion/depth surface: deterministic pattern scan of LO text + sub-items
 * for "not required" / "only" / "limited to" bounds, compared as sets.
 */
class SyllabusSourceDiff
{
    private const EXCLUSION_PATTERN = '/\(([^()]*?(?:is not required|are not required|not required|only|limited to)[^()]*)\)/iu';

    public function compare(SyllabusSource $old, SyllabusSource $new): array
    {
        $oldStructure = $old->structure_json ?? ['topics' => [], 'sections' => []];
        $newStructure = $new->structure_json ?? ['topics' => [], 'sections' => []];

        $oldLos = LearningObjective::where('syllabus_source_id', $old->id)
            ->orderBy('section_code')->orderBy('objective_number')->get()
            ->keyBy(fn ($lo) => "{$lo->section_code}#{$lo->objective_number}");
        $newLos = LearningObjective::where('syllabus_source_id', $new->id)
            ->orderBy('section_code')->orderBy('objective_number')->get()
            ->keyBy(fn ($lo) => "{$lo->section_code}#{$lo->objective_number}");

        $objectives = $this->classifyObjectives($oldLos, $newLos);

        return [
            'old_source' => ['reference' => $old->reference(), 'pdf_sha256' => $old->source_pdf_sha256],
            'new_source' => ['reference' => $new->reference(), 'pdf_sha256' => $new->source_pdf_sha256],
            'topics' => $this->diffTitled($oldStructure['topics'], $newStructure['topics']),
            'sections' => $this->diffSections($oldStructure['sections'], $newStructure['sections']),
            'objectives' => $objectives,
            'objective_counts' => array_map('count', $objectives),
            'exclusions' => $this->diffExclusions($oldLos, $newLos),
        ];
    }

    /** @param  Collection  $oldLos  keyed by "code#n" */
    private function classifyObjectives($oldLos, $newLos): array
    {
        $out = [
            'unchanged_exact' => [], 'formatting_only_changed' => [], 'subitems_changed' => [],
            'text_changed' => [], 'moved_exact' => [], 'added' => [], 'removed' => [],
        ];

        $removedKeys = $oldLos->keys()->diff($newLos->keys());
        $addedKeys = $newLos->keys()->diff($oldLos->keys());

        // moved_exact: a removed objective whose stripped text matches an added one.
        $addedByText = $addedKeys->mapWithKeys(
            fn ($k) => [$this->strip($newLos[$k]->text) => $k]
        );
        $moved = [];
        foreach ($removedKeys as $oldKey) {
            $stripped = $this->strip($oldLos[$oldKey]->text);
            if ($stripped !== '' && $addedByText->has($stripped)) {
                $newKey = $addedByText[$stripped];
                $moved[$oldKey] = $newKey;
                $out['moved_exact'][] = $this->row($oldLos[$oldKey], $newLos[$newKey], 'identical text under a different official reference');
            }
        }
        foreach ($removedKeys as $key) {
            if (! isset($moved[$key])) {
                $out['removed'][] = $this->row($oldLos[$key], null, 'reference absent from new source');
            }
        }
        $movedNewKeys = array_values($moved);
        foreach ($addedKeys as $key) {
            if (! in_array($key, $movedNewKeys, true)) {
                $out['added'][] = $this->row(null, $newLos[$key], 'reference absent from old source');
            }
        }

        foreach ($oldLos->keys()->intersect($newLos->keys()) as $key) {
            [$o, $n] = [$oldLos[$key], $newLos[$key]];
            $sameRaw = trim($o->text) === trim($n->text);
            $sameStripped = $this->strip($o->text) === $this->strip($n->text);
            $sameSubs = $this->strip(json_encode($o->sub_items ?? [])) === $this->strip(json_encode($n->sub_items ?? []));

            if ($sameRaw && $sameSubs) {
                $out['unchanged_exact'][] = $key;
            } elseif ($sameStripped && $sameSubs) {
                $out['formatting_only_changed'][] = $this->row($o, $n, 'whitespace/extraction artifacts only');
            } elseif ($sameStripped) {
                $out['subitems_changed'][] = $this->row($o, $n, 'sub-items differ');
            } else {
                $out['text_changed'][] = $this->row($o, $n, 'official wording differs')
                    + ['review_status' => 'ambiguous_manual_review'];
            }
        }

        return $out;
    }

    private function row(?LearningObjective $old, ?LearningObjective $new, string $evidence): array
    {
        return array_filter([
            'old_reference' => $old?->reference(),
            'old_text' => $old?->text,
            'old_sub_items' => $old?->sub_items,
            'old_pdf_pages' => $old?->provenance['pdf_pages'] ?? null,
            'new_reference' => $new?->reference(),
            'new_text' => $new?->text,
            'new_sub_items' => $new?->sub_items,
            'new_pdf_pages' => $new?->provenance['pdf_pages'] ?? null,
            'deterministic_evidence' => $evidence,
        ], fn ($v) => $v !== null);
    }

    private function diffTitled(array $old, array $new): array
    {
        $renamed = [];
        foreach (array_intersect_key($old, $new) as $k => $title) {
            if ($this->strip($title) !== $this->strip($new[$k])) {
                $renamed[] = ['key' => $k, 'old' => $title, 'new' => $new[$k]];
            }
        }

        return [
            'added' => array_values(array_diff_key($new, $old)),
            'removed' => array_values(array_diff_key($old, $new)),
            'renamed' => $renamed,
        ];
    }

    private function diffSections(array $old, array $new): array
    {
        $oldByCode = array_column($old, null, 'code');
        $newByCode = array_column($new, null, 'code');

        $renamed = [];
        $countChanged = [];
        foreach (array_intersect_key($oldByCode, $newByCode) as $code => $section) {
            if ($this->strip($section['title']) !== $this->strip($newByCode[$code]['title'])) {
                $renamed[] = ['code' => $code, 'old' => $section['title'], 'new' => $newByCode[$code]['title']];
            }
            if ($section['lo_count'] !== $newByCode[$code]['lo_count']) {
                $countChanged[] = ['code' => $code, 'old' => $section['lo_count'], 'new' => $newByCode[$code]['lo_count']];
            }
        }

        return [
            'added' => array_values(array_diff_key($newByCode, $oldByCode)),
            'removed' => array_values(array_diff_key($oldByCode, $newByCode)),
            'renamed' => $renamed,
            'lo_count_changed' => $countChanged,
        ];
    }

    /** @param  Collection  $oldLos */
    private function diffExclusions($oldLos, $newLos): array
    {
        $collect = function ($los): array {
            $found = [];
            foreach ($los as $key => $lo) {
                $haystack = $lo->text.' '.collect($lo->sub_items ?? [])->pluck('text')->implode(' ');
                if (preg_match_all(self::EXCLUSION_PATTERN, $haystack, $m)) {
                    foreach ($m[1] as $bound) {
                        $found[$this->strip($bound)] = ['reference' => $key, 'bound' => trim($bound)];
                    }
                }
            }

            return $found;
        };

        $old = $collect($oldLos);
        $new = $collect($newLos);

        return [
            'added' => array_values(array_diff_key($new, $old)),
            'removed' => array_values(array_diff_key($old, $new)),
            'total_old' => count($old),
            'total_new' => count($new),
        ];
    }

    /** Whitespace-stripped comparison form (immune to PDF intra-word spacing artifacts). */
    private function strip(?string $text): string
    {
        return preg_replace('/\s+/u', '', (string) $text);
    }
}
