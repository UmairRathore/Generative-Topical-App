<?php

namespace App\Services\V2;

/**
 * Deterministic structural validator for a Stage A Lesson Plan Proposal
 * (topicaled.stage-a-plan.v2) against the packet it was produced from
 * (topicaled.stage-a-packet.v2). Pure data checks - no NLP, no scientific
 * prose judgement. Fails closed: anything not present in the packet's
 * academic truth is a violation, never implicit approval.
 *
 * Returns ['blocking' => [...], 'warnings' => [...]]:
 *  - blocking: contract violations that make the plan unusable as-is;
 *  - warnings: structurally valid but suspicious behavior (pedagogy the
 *    validator cannot decide - it exposes non-use, never repairs).
 *
 * v2 plan shape (see RunStageAExperiment::PLANNING_INSTRUCTION for the full
 * prompt contract): conceptual_spine, learning_dependencies[{before,after,
 * reason}], phases[{title, purpose, rationale{why_now,assumes,prepares_for},
 * advances_los|supporting_review, concepts, direct_reference_handles,
 * supporting_reference_handles, widget: null|{widget_key, purpose,
 * target_lo_refs, canonical_reference_handles_demonstrated,
 * student_manipulation, expected_observation, intended_inference},
 * misconceptions[{id}|{unverified_proposal}], checks[{command_word, intent,
 * evidence_question_ids, evidence_independence_reason?}],
 * multi_lo_justification?, transition}], completion_evidence,
 * out_of_scope_notes, unresolved_questions.
 */
class StageAPlanValidator
{
    public const PLAN_SCHEMA = 'topicaled.stage-a-plan.v2';

    /** @return array{blocking: array<int, string>, warnings: array<int, string>} */
    public function validate(array $plan, array $packet): array
    {
        $blocking = [];
        $warnings = [];

        // ── Packet lookup sets (v2: explicit handle allowlists) ───────────
        $targets = array_column($packet['target_coverage']['objectives'], 'reference');
        $leaf = $packet['identity']['leaf_section']['section_code'];
        $outOfScope = array_map(fn ($lo) => "{$leaf} #{$lo['number']}", $packet['out_of_scope_curriculum']['objectives']);
        $directHandles = $packet['allowed_reference_handles']['direct'];
        $supportingHandles = $packet['allowed_reference_handles']['supporting'];
        $allHandles = array_merge($directHandles, $supportingHandles);
        $exclusions = array_map(fn ($e) => $this->normalize($e['text']), $packet['hard_constraints']['exclusions']);
        $widgetVerdicts = collect($packet['relevant_widgets']['widgets'])->pluck('compatibility_verdict', 'key');
        $evidenceIds = $packet['assessment_evidence']['allowed_evidence_ids'] ?? [];
        $commandWords = array_column($packet['policy_slices']['command_words']['items'] ?? [], 'key');
        $misconceptionIds = $packet['pedagogical_evidence']['allowed_misconception_ids'] ?? [];
        $highPriorityIds = collect($packet['pedagogical_evidence']['misconceptions'] ?? [])
            ->where('priority', 'high')->pluck('id')->all();

        if (($plan['plan_schema'] ?? null) !== self::PLAN_SCHEMA) {
            $blocking[] = 'plan_schema missing or unsupported (expected '.self::PLAN_SCHEMA.').';
        }

        // ── Conceptual spine + learning dependencies ──────────────────────
        $spine = trim((string) ($plan['conceptual_spine'] ?? ''));
        if ($spine === '') {
            $blocking[] = 'conceptual_spine is missing - the plan must declare its central conceptual structure.';
        } elseif (mb_strtolower($spine) === mb_strtolower($packet['identity']['leaf_section']['title'])
            || preg_match('/^cover\b/i', $spine)) {
            $warnings[] = "conceptual_spine ('{$spine}') is just the subtopic title / an objective list, not a conceptual structure.";
        }

        $dependencies = $plan['learning_dependencies'] ?? [];
        if ($dependencies === []) {
            $blocking[] = 'learning_dependencies missing - declare the conceptual ordering before phases.';
        }
        foreach ($dependencies as $i => $dep) {
            foreach (['before', 'after'] as $side) {
                $node = (string) ($dep[$side] ?? '');
                if (! in_array($node, $allHandles, true) && ! in_array($node, $targets, true)) {
                    $blocking[] = "learning_dependencies[{$i}].{$side} '{$node}' is not a packet handle or target objective."
                        .$this->nearestHandleHint($node, $allHandles);
                }
            }
        }

        // ── Target set ─────────────────────────────────────────────────────
        foreach ($plan['target_los'] ?? [] as $ref) {
            if (! in_array($ref, $targets, true)) {
                $blocking[] = in_array($ref, $outOfScope, true)
                    ? "target_los claims out-of-scope sibling '{$ref}' as coverage."
                    : "target_los contains '{$ref}' which is not in the packet target set.";
            }
        }
        foreach ($plan['supporting_assumptions'] ?? [] as $handle) {
            if (! in_array($handle, $supportingHandles, true)) {
                $blocking[] = "supporting_assumptions contains '{$handle}' which is not packet supporting truth."
                    .$this->nearestHandleHint($handle, $supportingHandles);
            }
        }

        // ── Phases ─────────────────────────────────────────────────────────
        $advancedTargets = [];
        $supportingUsedByTarget = []; // target ref => supporting handles seen in phases advancing it
        $anyEvidenceCited = false;
        $anyChecks = false;
        $targetedMisconceptions = [];

        foreach ($plan['phases'] ?? [] as $i => $phase) {
            $label = $phase['title'] ?? "phase {$i}";
            $advances = $phase['advances_los'] ?? [];
            $isSupportingReview = ($phase['supporting_review'] ?? false) === true;
            $phaseSupporting = $phase['supporting_reference_handles'] ?? [];

            if ($advances === [] && ! $isSupportingReview) {
                $blocking[] = "{$label}: declares neither advances_los nor supporting_review.";
            }
            if ($isSupportingReview && $phaseSupporting === []) {
                $blocking[] = "{$label}: supporting_review=true but cites zero supporting_reference_handles.";
            }
            foreach ($advances as $ref) {
                if (! in_array($ref, $targets, true)) {
                    $blocking[] = "{$label}: advances '{$ref}' which is not a packet target objective.";
                } else {
                    $advancedTargets[] = $ref;
                    foreach ($phaseSupporting as $handle) {
                        $supportingUsedByTarget[$ref][] = $handle;
                    }
                }
            }
            if (count($advances) > 2 && trim((string) ($phase['multi_lo_justification'] ?? '')) === '') {
                $warnings[] = "{$label}: advances ".count($advances).' target objectives without a multi_lo_justification.';
            }
            if (! isset($phase['rationale']['why_now'])) {
                $warnings[] = "{$label}: missing rationale.why_now (sequence justification).";
            }

            // Reference handle discipline: exact packet handles, right class.
            foreach ($phase['direct_reference_handles'] ?? [] as $handle) {
                if (in_array($handle, $supportingHandles, true)) {
                    $blocking[] = "{$label}: uses supporting reference '{$handle}' as direct - classification must be respected.";
                } elseif (! in_array($handle, $directHandles, true)) {
                    $blocking[] = "{$label}: direct reference '{$handle}' does not exist in the packet (unknown is not approval)."
                        .$this->nearestHandleHint($handle, $directHandles);
                }
            }
            foreach ($phaseSupporting as $handle) {
                if (in_array($handle, $directHandles, true)) {
                    $warnings[] = "{$label}: cites direct reference '{$handle}' as supporting.";
                } elseif (! in_array($handle, $supportingHandles, true)) {
                    $blocking[] = "{$label}: supporting reference '{$handle}' does not exist in the packet."
                        .$this->nearestHandleHint($handle, $supportingHandles);
                }
            }

            // Exclusions are hard everywhere in phase content.
            foreach (array_merge($phase['concepts'] ?? [], $phase['direct_reference_handles'] ?? [], $phaseSupporting) as $item) {
                $needle = $this->normalize((string) $item);
                foreach ($exclusions as $exclusion) {
                    if ($needle !== '' && str_contains($exclusion, $needle)) {
                        $blocking[] = "{$label}: references excluded content ('{$item}').";
                    }
                }
            }

            $this->validateWidget($phase['widget'] ?? null, $label, $widgetVerdicts, $allHandles, $targets, $blocking, $warnings);

            // Misconceptions: known id or classified unverified proposal.
            foreach ($phase['misconceptions'] ?? [] as $entry) {
                if (isset($entry['id'])) {
                    if (! in_array($entry['id'], $misconceptionIds, true)) {
                        $blocking[] = "{$label}: misconception id '{$entry['id']}' does not exist in the packet.";
                    } else {
                        $targetedMisconceptions[] = $entry['id'];
                    }
                } elseif (! isset($entry['unverified_proposal']) || trim((string) $entry['unverified_proposal']) === '') {
                    $blocking[] = "{$label}: misconception entry must carry a packet id or a classified unverified_proposal.";
                }
            }

            // Checks: explicit evidence use or explicit independence.
            foreach ($phase['checks'] ?? [] as $check) {
                $anyChecks = true;
                $ids = $check['evidence_question_ids'] ?? null;
                if (! is_array($ids)) {
                    $blocking[] = "{$label}: check must declare evidence_question_ids as an array ([] with evidence_independence_reason when independent).";
                } elseif ($ids === []) {
                    if (trim((string) ($check['evidence_independence_reason'] ?? '')) === '') {
                        $blocking[] = "{$label}: check cites no evidence and gives no evidence_independence_reason.";
                    }
                } else {
                    foreach ($ids as $id) {
                        if (! in_array($id, $evidenceIds, true)) {
                            $blocking[] = "{$label}: check cites evidence id {$id} which is not packet evidence.";
                        } else {
                            $anyEvidenceCited = true;
                        }
                    }
                }
                if (! empty($check['command_word']) && ! in_array($check['command_word'], $commandWords, true)) {
                    $blocking[] = "{$label}: check command word '{$check['command_word']}' is not in the packet policy slice.";
                }
                if (array_key_exists('answer', $check) || array_key_exists('correct_answer', $check)) {
                    $blocking[] = "{$label}: checks must not carry answer keys intended for student display.";
                }
            }
        }

        // ── Whole-plan rules ───────────────────────────────────────────────
        foreach ($targets as $ref) {
            if (! in_array($ref, $advancedTargets, true)) {
                $blocking[] = "Target objective '{$ref}' is never advanced by any phase.";
            }
        }

        // Suspicious non-use of mapped supporting truth (per-target, from the
        // packet's own prerequisite requirements - no hardcoded LO numbers).
        foreach ($packet['target_coverage']['objectives'] as $lo) {
            $mapped = collect($lo['requirements'] ?? [])
                ->where('role', 'prerequisite')->pluck('target')->all();
            if ($mapped === []) {
                continue;
            }
            $used = $supportingUsedByTarget[$lo['reference']] ?? [];
            if (array_intersect($mapped, $used) === []) {
                $warnings[] = "Target '{$lo['reference']}' has mapped supporting truth (".implode(', ', $mapped).') but no phase advancing it uses any of it.';
            }
        }

        if ($anyChecks && ! $anyEvidenceCited && $evidenceIds !== []) {
            $warnings[] = 'Every formative check ignores all supplied assessment evidence (experiment-quality signal).';
        }
        if ($highPriorityIds !== [] && array_intersect($highPriorityIds, $targetedMisconceptions) === []) {
            $warnings[] = 'No high-priority packet misconception is addressed ('.implode(', ', $highPriorityIds).').';
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    /** Widget placement: JSON null, or a structured placement citing packet facts. */
    private function validateWidget($widget, string $label, $verdicts, array $allHandles, array $targets, array &$blocking, array &$warnings): void
    {
        if ($widget === null) {
            return;
        }
        if (is_string($widget)) {
            $blocking[] = $widget === 'null' || $widget === ''
                ? "{$label}: widget must be JSON null or a placement object - the string '{$widget}' is not a null."
                : "{$label}: widget must be a placement object with widget_key, not a bare string.";

            return;
        }

        $key = $widget['widget_key'] ?? null;
        if (! $verdicts->has($key)) {
            $blocking[] = "{$label}: widget '{$key}' is not in the packet shortlist.";
        } elseif ($verdicts[$key] !== 'compatible') {
            $blocking[] = "{$label}: widget '{$key}' has verdict '{$verdicts[$key]}' and cannot be used.";
        }
        foreach ($widget['canonical_reference_handles_demonstrated'] ?? [] as $handle) {
            if (! in_array($handle, $allHandles, true)) {
                $blocking[] = "{$label}: widget demonstrates '{$handle}' which is not a packet handle."
                    .$this->nearestHandleHint($handle, $allHandles);
            }
        }
        foreach ($widget['target_lo_refs'] ?? [] as $ref) {
            if (! in_array($ref, $targets, true)) {
                $blocking[] = "{$label}: widget targets '{$ref}' which is not a packet target objective.";
            }
        }
        foreach (['student_manipulation', 'expected_observation', 'intended_inference'] as $field) {
            if (trim((string) ($widget[$field] ?? '')) === '') {
                $warnings[] = "{$label}: widget placement missing {$field}.";
            }
        }
    }

    /** Deterministic hint: exact suffix (after-colon) match only - never auto-repair. */
    private function nearestHandleHint(string $supplied, array $available): string
    {
        $suffix = str_contains($supplied, ':') ? substr($supplied, strpos($supplied, ':') + 1) : $supplied;
        $matches = array_values(array_filter(
            $available,
            fn ($h) => substr($h, strpos($h, ':') + 1) === $suffix
        ));

        return $matches === [] ? '' : ' Did you mean: '.implode(' | ', $matches).'?';
    }

    /** Same normalization family as SyllabusReferenceGuard (delta/superscript/space). */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['∆' => 'δ', 'Δ' => 'δ', '²' => '2', '×' => '*']);

        return preg_replace('/\s+/u', '', $text);
    }
}
