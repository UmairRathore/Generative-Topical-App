<?php

namespace App\Services\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\LearningObjectiveReference;
use App\Models\V2\SyllabusReference;
use App\Models\V2\SyllabusSource;

/**
 * The deterministic validation seam for future authoring validators: a
 * structured truth/constraint API over the canonical reference library.
 * No NLP, no prose inspection - it answers exactly the questions a Fable
 * output checker needs from data:
 *
 *   - is this relationship/expression REQUIRED, MAPPED, EXCLUDED or UNKNOWN
 *     for the selected LO context?
 *   - which canonical references must an authored artifact cover?
 *   - are there unresolved/unvalidated mappings that should block canon?
 *
 * Verdict semantics for expressionVerdict():
 *   excluded  - an LO-scoped exclusion matches (hard bound; wins over everything)
 *   required  - matches a validated relationship mapped with role requires/calculates_with
 *   mapped    - matches a validated reference mapped with any other role
 *   unknown   - no canonical statement about it (a validator should treat as
 *               out-of-scope content, not as permission)
 */
class SyllabusReferenceGuard
{
    private const REQUIRED_ROLES = [
        LearningObjectiveReference::ROLE_REQUIRES,
        LearningObjectiveReference::ROLE_CALCULATES_WITH,
    ];

    /**
     * @param  array<int, int>  $learningObjectiveIds
     * @return array{verdict: string, matches: array<int, array>}
     */
    public function expressionVerdict(array $learningObjectiveIds, string $expression): array
    {
        $needle = $this->normalize($expression);
        $matches = [];

        // 1. Exclusions win: LO-scoped hard bounds, checked against raw constraint text.
        foreach (LearningObjective::whereIn('id', $learningObjectiveIds)->get() as $lo) {
            foreach ($lo->constraints ?? [] as $constraint) {
                if (($constraint['type'] ?? '') === 'exclusion'
                    && str_contains($this->normalize($constraint['text']), $needle)) {
                    $matches[] = [
                        'kind' => 'exclusion',
                        'learning_objective' => $lo->reference(),
                        'text' => $constraint['text'],
                    ];
                }
            }
        }
        if ($matches !== []) {
            return ['verdict' => 'excluded', 'matches' => $matches];
        }

        // 2. Mapped validated relationships (word or symbolic form).
        $links = LearningObjectiveReference::with(['reference', 'learningObjective'])
            ->whereIn('learning_objective_id', $learningObjectiveIds)
            ->whereNotNull('syllabus_reference_id')
            ->get()
            ->filter(fn ($l) => $l->reference?->status === SyllabusReference::STATUS_VALIDATED);

        $verdict = 'unknown';
        foreach ($links as $link) {
            $reference = $link->reference;
            $forms = array_filter([
                $reference->payload['word_form'] ?? null,
                $reference->payload['symbol_form'] ?? null,
                $reference->payload['statement'] ?? null,
                $reference->title,
            ]);
            foreach ($forms as $form) {
                if ($this->normalize($form) === $needle || str_contains($this->normalize($form), $needle)) {
                    $required = in_array($link->role, self::REQUIRED_ROLES, true);
                    $matches[] = [
                        'kind' => $required ? 'required' : 'mapped',
                        'reference' => $reference->handle(),
                        'role' => $link->role,
                        'learning_objective' => $link->learningObjective->reference(),
                    ];
                    $verdict = $required ? 'required' : ($verdict === 'required' ? 'required' : 'mapped');
                    break;
                }
            }
        }

        return ['verdict' => $verdict, 'matches' => $matches];
    }

    /** Every validated canonical reference a selected-LO artifact must be able to cite, grouped by role. */
    public function enumerateRequired(array $learningObjectiveIds): array
    {
        return LearningObjectiveReference::with('reference')
            ->whereIn('learning_objective_id', $learningObjectiveIds)
            ->whereNotNull('syllabus_reference_id')
            ->get()
            ->filter(fn ($l) => $l->reference?->status === SyllabusReference::STATUS_VALIDATED)
            ->groupBy('role')
            ->map(fn ($links) => $links->pluck('reference')->map(fn ($r) => $r->handle())->unique()->sort()->values()->all())
            ->sortKeys()
            ->all();
    }

    /**
     * Mappings that must block/flag canonical use: dangling reference targets,
     * unvalidated references, malformed dual/empty targets.
     */
    public function unresolvedMappings(array $learningObjectiveIds): array
    {
        $issues = [];
        $links = LearningObjectiveReference::with(['reference', 'learningObjective'])
            ->whereIn('learning_objective_id', $learningObjectiveIds)
            ->get();

        foreach ($links as $link) {
            $lo = $link->learningObjective?->reference() ?? "lo#{$link->learning_objective_id}";
            if (! $link->isCoherent()) {
                $issues[] = ['mapping_id' => $link->id, 'learning_objective' => $lo, 'issue' => 'mapping must target exactly one of reference or policy item'];
            } elseif ($link->syllabus_reference_id !== null) {
                if (! $link->reference) {
                    $issues[] = ['mapping_id' => $link->id, 'learning_objective' => $lo, 'issue' => 'reference row missing'];
                } elseif ($link->reference->status !== SyllabusReference::STATUS_VALIDATED) {
                    $issues[] = ['mapping_id' => $link->id, 'learning_objective' => $lo, 'issue' => "reference {$link->reference->handle()} not validated ({$link->reference->status})"];
                }
            }
        }

        return $issues;
    }

    /** Selected LO numbers must all belong to (source, subtopic) and be validated. */
    public function selectionIssues(SyllabusSource $source, int $subtopicId, array $objectiveNumbers): array
    {
        $rows = LearningObjective::where('syllabus_source_id', $source->id)
            ->where('subtopic_id', $subtopicId)
            ->whereIn('objective_number', $objectiveNumbers)
            ->get()
            ->keyBy('objective_number');

        $issues = [];
        foreach ($objectiveNumbers as $number) {
            $lo = $rows->get($number);
            if (! $lo) {
                $issues[] = "objective #{$number} does not exist in this source/subtopic";
            } elseif ($lo->status !== LearningObjective::STATUS_VALIDATED) {
                $issues[] = "objective #{$number} is not validated ({$lo->status})";
            }
        }

        return $issues;
    }

    /** Lowercase, collapse whitespace, unify delta/superscript variants for form comparison. */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['∆' => 'δ', 'Δ' => 'δ', '²' => '2', '×' => '*']);

        return preg_replace('/\s+/u', '', $text);
    }
}
