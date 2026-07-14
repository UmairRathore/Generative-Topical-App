<?php

namespace App\Services\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\LearningObjectiveReference;
use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusPolicy;
use App\Models\V2\SyllabusReference;
use App\Models\V2\SyllabusSource;
use InvalidArgumentException;

/**
 * Deterministically resolves the canonical academic-reference package for a
 * selected set of validated Learning Objectives: terminology, quantities,
 * definitions, relationships, constants, sliced policy rules, and the
 * LO-specific hard constraints (exclusions/depth/conditions).
 *
 * Resolution is pure data traversal - no LLM, no keyword guessing beyond one
 * documented derivation (command words are matched word-boundary against the
 * VALIDATED command-word policy inside validated LO wording). Relevance comes
 * from curated LO->reference mappings, so a Motion Graphs selection never
 * receives transformer equations or the full symbols table.
 *
 * Trust rules: only validated LOs are resolvable (explicit draft override is
 * flagged); only validated references and validated policies contribute;
 * unvalidated/missing mapping targets surface as warnings, never silently.
 * One documented transitive hop: relationships/constants pull in the
 * validated quantity references they cite, so "requires a = Δv/Δt" always
 * carries acceleration's symbols/units without a duplicate mapping.
 */
class SyllabusReferenceResolver
{
    /** Per-instance cache of the validated command-word list, keyed by source id. */
    private array $commandWordCache = [];

    /**
     * LO instructional/cognitive actions that are NOT official Cambridge
     * command words but carry authoring intent (e.g. "plot" in 1.2 #7).
     * Fixed lexicon, word-boundary matched - reported separately so official
     * command semantics are never diluted or mislabeled.
     */
    private const INSTRUCTIONAL_ACTIONS = ['interpret', 'know', 'plot', 'recall', 'use'];

    /**
     * @param  array<int, int>  $objectiveNumbers  official numbers within the leaf; empty = all validated
     * @return array{package: array, warnings: array<int, string>}
     */
    public function resolve(
        SyllabusSource $source,
        Subtopic $subtopic,
        array $objectiveNumbers = [],
        bool $includeDraftLos = false,
    ): array {
        $topic = $subtopic->topic;
        if (! $topic || $topic->subject_id !== $source->subject_id) {
            throw new InvalidArgumentException(
                "Subtopic #{$subtopic->id} does not belong to syllabus source {$source->reference()}'s subject."
            );
        }

        $warnings = [];
        $selected = $this->selectObjectives($source, $subtopic, $objectiveNumbers, $includeDraftLos, $warnings);

        $links = LearningObjectiveReference::with('reference')
            ->whereIn('learning_objective_id', $selected->pluck('id'))
            ->orderBy('id')
            ->get();

        [$references, $supporting, $requirementsByLo] = $this->collectReferences($links, $warnings);
        $policySlices = $this->resolvePolicySlices($source, $links, $selected, $warnings);
        $constraints = $this->collectConstraints($selected);

        $package = [
            'scope' => [
                'syllabus' => [
                    'subject' => $source->subject->name,
                    'syllabus_code' => $source->syllabus_code,
                    'level' => $source->subject->level,
                    'version' => $source->version_label,
                    'source_pdf_sha256' => $source->source_pdf_sha256,
                ],
                'topic' => [
                    'number' => (int) $topic->external_id,
                    'title' => $topic->title,
                    'v2_topic_id' => $topic->id,
                ],
                'parent_section' => $this->parentSection($source, $subtopic->external_id),
                'leaf_section' => [
                    'section_code' => $subtopic->external_id,
                    'title' => $subtopic->title,
                    'v2_subtopic_id' => $subtopic->id,
                ],
                'selected_objectives' => $selected->map(fn (LearningObjective $lo) => array_filter([
                    'reference' => $lo->reference(),
                    'number' => $lo->objective_number,
                    'text' => $lo->text,
                    'sub_items' => $lo->sub_items,
                    'equations' => $lo->equations,
                    'constraints' => $lo->constraints,
                    'command_words' => $this->commandWordsIn($source, $lo) ?: null,
                    'instructional_actions' => $this->instructionalActionsIn($source, $lo) ?: null,
                    'requirements' => $requirementsByLo[$lo->id] ?? [],
                    'status' => $lo->status === LearningObjective::STATUS_VALIDATED ? null : $lo->status,
                    'pdf_pages' => $lo->provenance['pdf_pages'] ?? null,
                ], fn ($v) => $v !== null && $v !== []))->values()->all(),
                'out_of_scope_objectives' => $this->outOfScope($source, $subtopic, $selected),
            ],
            'references' => $references,
            // Supporting academic truth: canonical facts needed to TEACH the
            // selected LOs but established by sibling objectives (mapped with
            // role 'prerequisite'). Available as context - NEVER target coverage.
            'supporting_references' => [
                'note' => 'Context for teaching only. These references are established by sibling learning objectives; including them does NOT add those objectives to lesson coverage.',
                'references' => $supporting,
            ],
            'policy_slices' => $policySlices,
            'hard_constraints' => $constraints,
            'validation' => [
                'objectives_all_validated' => $selected->every(
                    fn (LearningObjective $lo) => $lo->status === LearningObjective::STATUS_VALIDATED
                ),
                'warnings' => $warnings,
            ],
        ];

        return ['package' => $package, 'warnings' => $warnings];
    }

    /** Shared selection rules (validated-only unless explicitly overridden). */
    public function selectObjectives(
        SyllabusSource $source,
        Subtopic $subtopic,
        array $objectiveNumbers,
        bool $includeDraftLos,
        array &$warnings,
    ) {
        $all = LearningObjective::where('syllabus_source_id', $source->id)
            ->where('subtopic_id', $subtopic->id)
            ->orderBy('objective_number')
            ->get();

        if ($all->isEmpty()) {
            throw new InvalidArgumentException(
                "No learning objectives for {$subtopic->external_id} in source {$source->reference()}."
            );
        }

        // Rejected rows are extraction artifacts - never selectable, even by
        // explicit number with the draft override.
        $selected = $objectiveNumbers === []
            ? $all->where('status', LearningObjective::STATUS_VALIDATED)->values()
            : $all->where('status', '!=', LearningObjective::STATUS_REJECTED)
                ->whereIn('objective_number', $objectiveNumbers)->values();

        if ($objectiveNumbers !== []) {
            $missing = array_values(array_diff($objectiveNumbers, $selected->pluck('objective_number')->all()));
            if ($missing !== []) {
                throw new InvalidArgumentException(
                    "Objective number(s) not found for {$subtopic->external_id}: ".implode(', ', $missing)
                );
            }
        }

        $unvalidated = $selected->where('status', '!=', LearningObjective::STATUS_VALIDATED);
        if ($unvalidated->isNotEmpty()) {
            $refs = $unvalidated->map(fn ($lo) => $lo->reference())->implode(', ');
            if (! $includeDraftLos) {
                throw new InvalidArgumentException(
                    "Refusing to resolve references for UNVALIDATED objectives: {$refs}. "
                    .'Validate them via v2:apply-lo-review, or pass includeDraftLos explicitly.'
                );
            }
            $warnings[] = "UNVALIDATED objectives included by explicit override: {$refs} - not canonical.";
        }

        if ($selected->isEmpty()) {
            throw new InvalidArgumentException(
                "No validated objectives available for {$subtopic->external_id} in {$source->reference()}."
            );
        }

        return $selected;
    }

    /**
     * Union of mapped canonical references (validated only), deduplicated,
     * grouped by type, plus the per-LO requirement lists.
     *
     * @return array{0: array<string, array>, 1: array<int, array>}
     */
    private function collectReferences($links, array &$warnings): array
    {
        $directByHandle = [];
        $supportingByHandle = [];
        $requirements = [];

        foreach ($links as $link) {
            if ($link->syllabus_reference_id === null) {
                continue; // policy-item targets handled in resolvePolicySlices()
            }

            $reference = $link->reference;
            if (! $reference) {
                $warnings[] = "Mapping #{$link->id} points at a missing reference row - skipped.";

                continue;
            }

            if ($reference->status !== SyllabusReference::STATUS_VALIDATED) {
                $warnings[] = "Reference {$reference->handle()} is mapped but NOT validated - excluded from canonical package.";

                continue;
            }

            if ($link->role === LearningObjectiveReference::ROLE_PREREQUISITE) {
                $supportingByHandle[$reference->handle()] = $reference;
            } else {
                $directByHandle[$reference->handle()] = $reference;
            }
            $requirements[$link->learning_objective_id][] = [
                'role' => $link->role,
                'target' => $reference->handle(),
                'title' => $reference->title,
            ];
        }

        // A reference that is DIRECT for any selected LO never also appears as
        // supporting - direct wins, one entry per handle across the package.
        $supportingByHandle = array_diff_key($supportingByHandle, $directByHandle);

        // One documented transitive hop per bucket: relationships/constants cite
        // quantity keys; pull those validated quantities into the SAME bucket
        // (never duplicated across buckets - direct wins).
        $this->pullCitedQuantities($directByHandle, $directByHandle, $warnings);
        $this->pullCitedQuantities($supportingByHandle, $directByHandle + $supportingByHandle, $warnings);

        $direct = $this->groupReferences($directByHandle);
        $supporting = $this->groupReferences($supportingByHandle, withEstablishedBy: true);

        foreach ($requirements as &$rows) {
            usort($rows, fn ($a, $b) => [$a['role'], $a['target']] <=> [$b['role'], $b['target']]);
        }

        return [$direct, $supporting, $requirements];
    }

    /** Transitive hop: add validated quantities cited by relationships/constants in $bucket. */
    private function pullCitedQuantities(array &$bucket, array $alreadyPresent, array &$warnings): void
    {
        foreach ($bucket as $reference) {
            foreach ($reference->payload['quantities'] ?? [] as $quantityKey) {
                $handle = SyllabusReference::TYPE_QUANTITY.":{$quantityKey}";
                if (isset($alreadyPresent[$handle]) || isset($bucket[$handle])) {
                    continue;
                }
                $quantity = SyllabusReference::where('syllabus_source_id', $reference->syllabus_source_id)
                    ->where('reference_type', SyllabusReference::TYPE_QUANTITY)
                    ->where('key', $quantityKey)
                    ->first();
                if (! $quantity || $quantity->status !== SyllabusReference::STATUS_VALIDATED) {
                    $warnings[] = "{$reference->handle()} cites quantity '{$quantityKey}' which is missing or unvalidated.";

                    continue;
                }
                $bucket[$quantity->handle()] = $quantity;
            }
        }
    }

    /** Group a handle-keyed bucket by category, stable-sorted. */
    private function groupReferences(array $byHandle, bool $withEstablishedBy = false): array
    {
        $grouped = [
            'terminology' => [],
            'quantities' => [],
            'definitions' => [],
            'relationships' => [],
            'constants' => [],
        ];
        $groupOf = [
            SyllabusReference::TYPE_TERM => 'terminology',
            SyllabusReference::TYPE_QUANTITY => 'quantities',
            SyllabusReference::TYPE_DEFINITION => 'definitions',
            SyllabusReference::TYPE_RELATIONSHIP => 'relationships',
            SyllabusReference::TYPE_CONSTANT => 'constants',
        ];

        ksort($byHandle);
        foreach ($byHandle as $reference) {
            $entry = array_filter([
                // The exact machine identity consumers must cite - never derived
                // from JSON group names (call-01 lesson: models WILL guess).
                'handle' => $reference->handle(),
                'key' => $reference->key,
                'title' => $reference->title,
                ...$reference->payload,
                'provenance' => $reference->provenance,
            ], fn ($v) => $v !== null && $v !== []);

            if ($withEstablishedBy) {
                // Which sibling LO owns this truth - explicit, so a consumer can
                // never mistake supporting context for target coverage.
                $entry['established_by'] = $reference->provenance['learning_objective']
                    ?? $reference->provenance['policy']
                    ?? 'syllabus policy';
            }

            $grouped[$groupOf[$reference->reference_type]][] = $entry;
        }

        return $grouped;
    }

    /**
     * Policy slices: curated policy-item mappings (graph/data conventions,
     * measurement language, nomenclature) + the derived command-word subset.
     * Only VALIDATED policies contribute; only mapped items are included.
     */
    private function resolvePolicySlices(SyllabusSource $source, $links, $selected, array &$warnings): array
    {
        $slices = [];

        $policyLinks = $links->filter(fn ($l) => $l->policy_type !== null)->groupBy('policy_type');
        foreach ($policyLinks as $type => $rows) {
            $policy = SyllabusPolicy::where('syllabus_source_id', $source->id)
                ->where('policy_type', $type)
                ->first();

            if (! $policy || $policy->status !== SyllabusPolicy::STATUS_VALIDATED) {
                $warnings[] = "Policy '{$type}' is mapped by LOs but missing or unvalidated - slice omitted.";

                continue;
            }

            $items = [];
            $index = $this->indexPolicyItems($policy->content);
            foreach ($rows->pluck('policy_item_key')->unique()->sort()->values() as $key) {
                if (! isset($index[$key])) {
                    $warnings[] = "Policy '{$type}' has no item keyed '{$key}' - mapping is stale.";

                    continue;
                }
                $items[] = ['key' => $key, 'text' => $index[$key]];
            }

            if ($items !== []) {
                $slices[$type] = [
                    'title' => $policy->title,
                    'items' => $items,
                    'provenance' => $policy->provenance,
                ];
            }
        }

        // Derived slice: official command words appearing in the selected LOs'
        // validated wording (word-boundary match against the validated policy).
        $commandWords = collect($selected)
            ->flatMap(fn (LearningObjective $lo) => $this->commandWordsIn($source, $lo))
            ->unique()
            ->sort()
            ->values();

        if ($commandWords->isNotEmpty()) {
            $policy = SyllabusPolicy::where('syllabus_source_id', $source->id)
                ->where('policy_type', SyllabusPolicy::TYPE_COMMAND_WORDS)
                ->where('status', SyllabusPolicy::STATUS_VALIDATED)
                ->first();
            if ($policy) {
                $meanings = collect($policy->content['words'] ?? [])->keyBy('word');
                $slices[SyllabusPolicy::TYPE_COMMAND_WORDS] = [
                    'title' => $policy->title,
                    'derivation' => 'word-boundary scan of validated LO wording against the validated command-word policy',
                    'items' => $commandWords
                        ->map(fn ($word) => ['key' => $word, 'text' => $meanings[$word]['meaning'] ?? ''])
                        ->filter(fn ($item) => $item['text'] !== '')
                        ->values()
                        ->all(),
                    'provenance' => $policy->provenance,
                ];
            }
        }

        ksort($slices);

        return $slices;
    }

    /** Official command words present in one LO's validated wording (+ sub-items). */
    private function commandWordsIn(SyllabusSource $source, LearningObjective $lo): array
    {
        $words = $this->commandWordCache[$source->id] ??= SyllabusPolicy::where('syllabus_source_id', $source->id)
            ->where('policy_type', SyllabusPolicy::TYPE_COMMAND_WORDS)
            ->where('status', SyllabusPolicy::STATUS_VALIDATED)
            ->first()
            ?->content['words'] ?? [];

        $haystack = $lo->text.' '.collect($lo->sub_items ?? [])->pluck('text')->implode(' ');

        $found = [];
        foreach ($words as $entry) {
            if (preg_match('/\b'.preg_quote($entry['word'], '/').'\b/iu', $haystack)) {
                $found[] = $entry['word'];
            }
        }
        sort($found);

        return $found;
    }

    /** Non-official instructional/cognitive verbs in one LO's wording (fixed lexicon, word-boundary). */
    private function instructionalActionsIn(SyllabusSource $source, LearningObjective $lo): array
    {
        $haystack = $lo->text.' '.collect($lo->sub_items ?? [])->pluck('text')->implode(' ');
        $official = array_map(
            fn ($w) => mb_strtolower($w),
            array_column($this->commandWordCache[$source->id] ?? [], 'word')
        );

        $found = [];
        foreach (self::INSTRUCTIONAL_ACTIONS as $action) {
            if (in_array($action, $official, true)) {
                continue; // never shadow an official command word
            }
            if (preg_match('/\b'.preg_quote($action, '/').'\b/iu', $haystack)) {
                $found[] = $action;
            }
        }
        sort($found);

        return $found;
    }

    /** Flatten a policy's content into key => text for slice lookup. */
    private function indexPolicyItems(array $content): array
    {
        $index = [];
        $walk = function ($node) use (&$walk, &$index) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['key'], $node['text'])) {
                $index[$node['key']] = $node['text'];

                return;
            }
            // Natural keys: command words ('word') and measurement terms ('term').
            if (isset($node['word'], $node['meaning'])) {
                $index[$node['word']] = $node['meaning'];

                return;
            }
            if (isset($node['term'], $node['definition'])) {
                $index[$node['term']] = $node['definition'];

                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($content);

        return $index;
    }

    /** LO-scoped hard bounds, each tagged with its owning objective. */
    private function collectConstraints($selected): array
    {
        $grouped = ['exclusions' => [], 'depth' => [], 'conditions' => []];
        $bucket = ['exclusion' => 'exclusions', 'depth' => 'depth', 'condition' => 'conditions'];

        foreach ($selected as $lo) {
            foreach ($lo->constraints ?? [] as $constraint) {
                $key = $bucket[$constraint['type'] ?? ''] ?? null;
                if ($key !== null) {
                    $grouped[$key][] = [
                        'learning_objective' => $lo->reference(),
                        'text' => $constraint['text'],
                    ];
                }
            }
        }

        return $grouped;
    }

    private function outOfScope(SyllabusSource $source, Subtopic $subtopic, $selected): array
    {
        return LearningObjective::where('syllabus_source_id', $source->id)
            ->where('subtopic_id', $subtopic->id)
            ->where('status', '!=', LearningObjective::STATUS_REJECTED)
            ->whereNotIn('objective_number', $selected->pluck('objective_number'))
            ->orderBy('objective_number')
            ->get(['objective_number', 'text', 'status'])
            ->map(fn ($lo) => [
                'number' => $lo->objective_number,
                'text' => $lo->text,
                'status' => $lo->status,
            ])
            ->values()
            ->all();
    }

    private function parentSection(SyllabusSource $source, string $leafCode): ?array
    {
        $segments = explode('.', $leafCode);
        if (count($segments) < 3) {
            return null;
        }

        $parentCode = "{$segments[0]}.{$segments[1]}";
        $title = $source->sectionTitle($parentCode);

        return $title === null ? null : ['section_code' => $parentCode, 'title' => $title];
    }
}
