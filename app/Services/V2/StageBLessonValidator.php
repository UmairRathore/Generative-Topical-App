<?php

namespace App\Services\V2;

use App\Models\V2\ReviewSignoff;

/**
 * Deterministic structural validator for a Stage B authored lesson
 * (topicaled.stage-b-lesson.v1) against the packet it is grounded in and the
 * APPROVED Stage A plan it realizes. Pure data checks - no NLP, no scientific
 * prose judgement. Fails closed: anything not present in the packet's academic
 * truth is a violation, never implicit approval. Stage A validation is NOT
 * weakened or duplicated here - Stage B validation is additive.
 *
 * Returns ['blocking' => [...], 'warnings' => [...]].
 *
 * Approval binding (foundation fingerprint / plan SHA vs the recorded VALID
 * human approvals) is checked when $context provides the hashes; the DB lookup
 * runs only when $context['verify_approvals'] is true so pure-structure tests
 * can run without records.
 */
class StageBLessonValidator
{
    public const LESSON_SCHEMA = 'topicaled.stage-b-lesson.v1';

    public const BLOCK_TYPES = [
        'explanation', 'concept_summary', 'interactive_widget', 'guided_example',
        'misconception_intervention', 'formative_check', 'transition', 'tutor_checkpoint',
    ];

    public const RESPONSE_TYPES = [
        'single_choice', 'multiple_choice', 'numeric', 'numeric_with_unit', 'short_text', 'sketch_manual',
    ];

    /** Deterministic warning threshold: prose blocks longer than this are flagged for review. */
    public const EXPLANATION_LENGTH_WARNING = 2200;

    public const PHASE_BLOCK_COUNT_WARNING = 7;

    /**
     * @param  array  $context  optional: ['plan_sha256' => .., 'packet_sha256' => ..,
     *                          'verify_approvals' => bool]
     * @return array{blocking: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array $lesson, array $packet, array $plan, array $context = []): array
    {
        $blocking = [];
        $warnings = [];

        // ── Packet lookup sets ─────────────────────────────────────────────
        $targets = array_column($packet['target_coverage']['objectives'], 'reference');
        $leaf = $packet['identity']['leaf_section']['section_code'];
        $outOfScope = array_map(fn ($lo) => "{$leaf} #{$lo['number']}", $packet['out_of_scope_curriculum']['objectives']);
        $directHandles = $packet['allowed_reference_handles']['direct'];
        $supportingHandles = $packet['allowed_reference_handles']['supporting'];
        $allHandles = array_merge($directHandles, $supportingHandles);
        $relationshipHandles = array_values(array_filter($allHandles, fn ($h) => str_starts_with($h, 'relationship:')));
        $exclusions = array_map(fn ($e) => $this->normalize($e['text']), $packet['hard_constraints']['exclusions']);
        $widgetVerdicts = collect($packet['relevant_widgets']['widgets'])->pluck('compatibility_verdict', 'key');
        $evidenceIds = $packet['assessment_evidence']['allowed_evidence_ids'] ?? [];
        $evidenceStems = collect($packet['assessment_evidence']['questions'] ?? [])
            ->map(fn ($q) => $this->normalize((string) ($q['stem'] ?? '')))->filter()->values()->all();
        $commandWords = array_column($packet['policy_slices']['command_words']['items'] ?? [], 'key');
        $misconceptionIds = $packet['pedagogical_evidence']['allowed_misconception_ids'] ?? [];

        // ── 1. Schema + identity binding to the approved Stage A source ───
        if (($lesson['lesson_schema'] ?? null) !== self::LESSON_SCHEMA) {
            $blocking[] = 'lesson_schema missing or unsupported (expected '.self::LESSON_SCHEMA.').';
        }
        $identity = $lesson['identity'] ?? [];
        foreach (['syllabus_code', 'syllabus_version'] as $field) {
            if (($identity[$field] ?? null) !== ($packet['identity'][$field] ?? null)) {
                $blocking[] = "identity.{$field} does not match the approved Stage A packet.";
            }
        }
        if (($identity['source_pdf_sha256'] ?? null) !== ($packet['identity']['source_pdf_sha256'] ?? null)) {
            $blocking[] = 'identity.source_pdf_sha256 does not match the packet source PDF.';
        }

        // ── Bindings: packet SHA / plan SHA / foundation fingerprint ──────
        $bindings = $lesson['bindings'] ?? [];
        $fingerprint = ReviewSignoff::foundationFingerprint($packet);
        if (($bindings['foundation_fingerprint'] ?? null) !== $fingerprint) {
            $blocking[] = 'bindings.foundation_fingerprint does not match the current packet foundation fingerprint - canonical truth changed or the binding is wrong.';
        }
        if (isset($context['packet_sha256']) && ($bindings['packet_sha256'] ?? null) !== $context['packet_sha256']) {
            $blocking[] = 'bindings.packet_sha256 does not match the supplied packet file.';
        }
        if (isset($context['plan_sha256']) && ($bindings['stage_a_plan_sha256'] ?? null) !== $context['plan_sha256']) {
            $blocking[] = 'bindings.stage_a_plan_sha256 does not match the supplied approved Stage A plan file.';
        }

        // ── Approval gates (foundation + Stage A plan must be VALID) ──────
        if (($context['verify_approvals'] ?? false) === true) {
            $foundationScope = ReviewSignoff::scopeFor($packet);
            $latestFoundation = ReviewSignoff::where('scope', $foundationScope)->orderByDesc('signed_at')->first();
            if (! $latestFoundation) {
                $blocking[] = "No academic-foundation sign-off recorded for scope {$foundationScope}.";
            } elseif ($latestFoundation->foundation_fingerprint !== $fingerprint) {
                $blocking[] = 'Academic-foundation sign-off is STALE - canonical truth changed since it was recorded.';
            }

            $planScope = 'stage-a-plan:'.substr($foundationScope, strlen('stage-a-foundation:'));
            $latestPlan = ReviewSignoff::where('scope', $planScope)->orderByDesc('signed_at')->first();
            if (! $latestPlan) {
                $blocking[] = "No Stage A plan approval recorded for scope {$planScope}.";
            } else {
                if (isset($context['plan_sha256']) && $latestPlan->plan_sha256 !== $context['plan_sha256']) {
                    $blocking[] = 'Stage A plan approval is STALE_PLAN - the plan changed since approval.';
                }
                if ($latestPlan->foundation_fingerprint !== $fingerprint) {
                    $blocking[] = 'Stage A plan approval is STALE_FOUNDATION - canonical truth changed since approval.';
                }
            }
        }

        // ── Target LO set: exact match with the approved plan ─────────────
        $lessonTargets = $identity['target_los'] ?? [];
        $planTargets = $plan['target_los'] ?? [];
        if (array_values($lessonTargets) !== array_values($planTargets)) {
            $blocking[] = 'identity.target_los does not exactly match the approved Stage A target set.';
        }
        foreach ($lessonTargets as $ref) {
            if (! in_array($ref, $targets, true)) {
                $blocking[] = in_array($ref, $outOfScope, true)
                    ? "identity.target_los claims out-of-scope sibling '{$ref}' as coverage."
                    : "identity.target_los contains '{$ref}' which is not in the packet target set.";
            }
        }

        // ── Non-canonical status ───────────────────────────────────────────
        $status = $lesson['status'] ?? [];
        if (($status['canonical'] ?? null) !== false || ($status['published'] ?? null) !== false) {
            $blocking[] = 'status must declare canonical=false and published=false - Stage B output is never canonical.';
        }
        if (! in_array($status['review_state'] ?? null, ['pending_human_review', 'needs_revision'], true)) {
            $blocking[] = "status.review_state must be 'pending_human_review' or 'needs_revision' before approval.";
        }

        // ── Phase identity + ordering vs the approved plan ─────────────────
        $planPhaseTitles = array_column($plan['phases'] ?? [], 'title');
        $lessonPhases = $lesson['phases'] ?? [];
        if ($lessonPhases === []) {
            $blocking[] = 'lesson has no phases.';
        }
        $lessonPhaseAnchors = array_map(fn ($p) => $p['stage_a_phase_title'] ?? null, $lessonPhases);
        if ($lessonPhaseAnchors !== $planPhaseTitles) {
            $blocking[] = 'phases must anchor to the approved Stage A phases in order (stage_a_phase_title sequence must equal the plan phase titles).';
        }

        // ── Blocks ──────────────────────────────────────────────────────────
        $coveredByContent = [];
        $coveredByCheck = [];
        $widgetPurposes = [];   // widget_key => [purpose, ...]
        $responseTypes = [];
        $tutorCheckpoints = 0;
        $seenBlockIds = [];

        foreach ($lessonPhases as $pi => $phase) {
            $label = $phase['title'] ?? "phase {$pi}";
            $blocks = $phase['blocks'] ?? [];
            if (count($blocks) > self::PHASE_BLOCK_COUNT_WARNING) {
                $warnings[] = "{$label}: ".count($blocks).' blocks - consider splitting (cognitive load).';
            }

            foreach ($phase['advances_los'] ?? [] as $ref) {
                if (! in_array($ref, $targets, true)) {
                    $blocking[] = "{$label}: advances '{$ref}' which is not a packet target objective.";
                }
            }
            foreach (array_merge($phase['direct_reference_handles'] ?? [], $phase['supporting_reference_handles'] ?? []) as $handle) {
                if (! in_array($handle, $allHandles, true)) {
                    $blocking[] = "{$label}: phase reference '{$handle}' does not exist in the packet.";
                }
            }

            foreach ($blocks as $bi => $block) {
                $type = $block['type'] ?? null;
                $bid = $block['block_id'] ?? "{$label} block {$bi}";
                if (isset($block['block_id'])) {
                    if (in_array($block['block_id'], $seenBlockIds, true)) {
                        $blocking[] = "{$bid}: duplicate block_id.";
                    }
                    $seenBlockIds[] = $block['block_id'];
                } else {
                    $blocking[] = "{$label} block {$bi}: missing block_id.";
                }
                if (! in_array($type, self::BLOCK_TYPES, true)) {
                    $blocking[] = "{$bid}: unsupported block type '{$type}'.";

                    continue;
                }
                if (array_key_exists('learning_asset_id', $block)) {
                    $blocking[] = "{$bid}: references an internal learning asset as grounding - draft assets are not approved grounding.";
                }

                // Reference-handle discipline for every block that declares handles.
                foreach ($block['grounding_reference_handles'] ?? [] as $handle) {
                    if (! in_array($handle, $allHandles, true)) {
                        $blocking[] = "{$bid}: grounding reference '{$handle}' does not exist in the packet (unknown is not approval).";
                    }
                }
                foreach ($block['direct_reference_handles'] ?? [] as $handle) {
                    if (in_array($handle, $supportingHandles, true)) {
                        $blocking[] = "{$bid}: uses supporting reference '{$handle}' as direct - classification must be respected.";
                    } elseif (! in_array($handle, $directHandles, true)) {
                        $blocking[] = "{$bid}: direct reference '{$handle}' does not exist in the packet.";
                    }
                }
                foreach ($block['relationships_used'] ?? [] as $handle) {
                    if (! in_array($handle, $relationshipHandles, true)) {
                        $blocking[] = "{$bid}: relationship '{$handle}' is not a packet relationship - unknown or excluded relationships cannot be taught as canonical.";
                    }
                }
                foreach ($block['target_lo_refs'] ?? [] as $ref) {
                    if (! in_array($ref, $targets, true)) {
                        $blocking[] = "{$bid}: targets '{$ref}' which is not a packet target objective.";
                    } else {
                        $coveredByContent[] = $ref;
                    }
                }

                // Exclusions are hard everywhere prose appears.
                $prose = implode(' ', array_filter([
                    $block['body_md'] ?? '', $block['prompt_md'] ?? '', $block['setup_md'] ?? '',
                    implode(' ', $block['points_md'] ?? []),
                ]));
                foreach ($exclusions as $exclusion) {
                    if ($exclusion !== '' && str_contains($this->normalize($prose), $exclusion)) {
                        $blocking[] = "{$bid}: prose contains excluded content.";
                    }
                }

                match ($type) {
                    'explanation' => $this->validateExplanation($block, $bid, $blocking, $warnings),
                    'concept_summary' => $this->validateGrounded($block, $bid, $blocking),
                    'interactive_widget' => $this->validateWidgetBlock($block, $bid, $widgetVerdicts, $allHandles, $targets, $widgetPurposes, $blocking, $warnings),
                    'guided_example' => $this->validateGuidedExample($block, $bid, $relationshipHandles, $blocking, $warnings),
                    'misconception_intervention' => $this->validateMisconception($block, $bid, $misconceptionIds, $blocking),
                    'formative_check' => $this->validateCheck($block, $bid, $targets, $evidenceIds, $evidenceStems, $commandWords, $coveredByCheck, $responseTypes, $blocking, $warnings),
                    'transition' => null,
                    'tutor_checkpoint' => $this->validateTutorCheckpoint($block, $bid, $targets, $allHandles, $tutorCheckpoints, $blocking),
                };
            }
        }

        // ── Whole-lesson rules ───────────────────────────────────────────────
        foreach ($planTargets as $ref) {
            if (! in_array($ref, $coveredByContent, true)) {
                $blocking[] = "Target objective '{$ref}' receives no authored content coverage (no block targets it).";
            }
            if (! in_array($ref, $coveredByCheck, true)) {
                $warnings[] = "Target objective '{$ref}' has no formative check.";
            }
        }
        foreach ($widgetPurposes as $key => $purposes) {
            if (count($purposes) > 1 && count(array_unique(array_map(fn ($p) => $this->normalize($p), $purposes))) < count($purposes)) {
                $warnings[] = "Widget '{$key}' is repeated without a distinct declared pedagogical purpose.";
            }
        }
        $choiceOnly = ['single_choice', 'multiple_choice'];
        if ($responseTypes !== [] && array_diff(array_unique($responseTypes), $choiceOnly) === []) {
            $warnings[] = 'Every formative check is multiple-choice - production skills (sketch, calculate) are untested.';
        }
        if ($tutorCheckpoints > 2) {
            $warnings[] = "{$tutorCheckpoints} tutor checkpoints - checkpoint overuse dilutes their value.";
        }

        // Supporting truth mapped to a target but never used in phases advancing it.
        foreach ($packet['target_coverage']['objectives'] as $lo) {
            $mapped = collect($lo['requirements'] ?? [])->where('role', 'prerequisite')->pluck('target')->all();
            if ($mapped === []) {
                continue;
            }
            $used = [];
            foreach ($lessonPhases as $phase) {
                if (in_array($lo['reference'], $phase['advances_los'] ?? [], true)) {
                    $used = array_merge($used, $phase['supporting_reference_handles'] ?? []);
                }
            }
            if (array_intersect($mapped, $used) === []) {
                $warnings[] = "Target '{$lo['reference']}' has mapped supporting truth (".implode(', ', $mapped).') but no phase advancing it uses any of it.';
            }
        }

        foreach ($this->canonicalTerminologyWarnings($lessonPhases, $allHandles) as $warning) {
            $warnings[] = $warning;
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    /**
     * Narrow canonical graph-terminology lint (WARNING only, packet-driven,
     * for human inspection - never automatic text replacement). When the
     * packet's canonical truth is about distance-time / speed-time graphs and
     * carries NO position/displacement concept, student-facing prose using
     * those concepts is flagged: "position on a distance-time graph" was a
     * real human-review finding this cheaply surfaces. Deliberately NOT
     * attempted deterministically: unsupported-cause detection and policy
     * absolutism - those stay a human visual-review responsibility.
     */
    private function canonicalTerminologyWarnings(array $lessonPhases, array $allHandles): array
    {
        $isMotionGraphLesson = collect($allHandles)->contains(fn ($h) => str_contains($h, 'distance_time_graph') || str_contains($h, 'speed_time_graph'));
        if (! $isMotionGraphLesson) {
            return [];
        }
        $packetHas = fn (string $concept) => collect($allHandles)->contains(fn ($h) => str_contains($h, $concept));

        $warnings = [];
        foreach ($this->studentFacingProse($lessonPhases) as $where => $text) {
            // Non-canonical graph-type compounds (position-time graph, ...).
            if (preg_match('/(position|displacement|velocity)[\s\-–—]*time\s+graph/iu', $text, $m)
                && ! $packetHas(mb_strtolower($m[1]).'_time')) {
                $warnings[] = "{$where}: uses '{$m[0]}' but the packet has no canonical ".mb_strtolower($m[1]).'-time graph concept (human inspection: terminology drift?).';
            }
            // Bare physics concepts the packet does not supply. 'position' as a
            // plain verb ("position the triangle") is exempted via determiner.
            if (! $packetHas('displacement') && preg_match('/\bdisplacement\b/iu', $text)) {
                $warnings[] = "{$where}: uses 'displacement' but the packet has no canonical displacement concept (human inspection: terminology drift?).";
            }
            if (! $packetHas('position') && preg_match('/\bposition\b(?!\s+(?:the|a|an|your|both|two|each|it)\b)/iu', $text)) {
                $warnings[] = "{$where}: uses 'position' but the packet has no canonical position concept (human inspection: terminology drift?).";
            }
        }

        return $warnings;
    }

    /** Student-facing prose fields (including check feedback), keyed by location. */
    private function studentFacingProse(array $lessonPhases): array
    {
        $prose = [];
        foreach ($lessonPhases as $pi => $phase) {
            foreach ($phase['blocks'] ?? [] as $bi => $block) {
                $bid = $block['block_id'] ?? "phase {$pi} block {$bi}";
                foreach (['heading', 'body_md', 'prompt_md', 'setup_md', 'correction_md', 'trigger_context', 'incorrect_reasoning', 'student_instruction', 'text_md'] as $field) {
                    if (is_string($block[$field] ?? null)) {
                        $prose["{$bid}.{$field}"] = $block[$field];
                    }
                }
                foreach ($block['points_md'] ?? [] as $i => $point) {
                    $prose["{$bid}.points_md[{$i}]"] = (string) $point;
                }
                foreach ($block['steps'] ?? [] as $i => $step) {
                    $prose["{$bid}.steps[{$i}]"] = is_array($step) ? (string) ($step['step_md'] ?? '') : (string) $step;
                }
                foreach ($block['options'] ?? [] as $i => $option) {
                    $prose["{$bid}.options[{$i}]"] = (string) (is_array($option) ? ($option['label_md'] ?? $option['label'] ?? '') : $option);
                }
                foreach (($block['evaluation']['data'] ?? []) as $key => $value) {
                    if (is_string($value)) {
                        $prose["{$bid}.evaluation.data.{$key}"] = $value;
                    } elseif (is_array($value)) {
                        foreach ($value as $vk => $vv) {
                            if (is_string($vv)) {
                                $prose["{$bid}.evaluation.data.{$key}.{$vk}"] = $vv;
                            }
                        }
                    }
                }
            }
        }

        return $prose;
    }

    private function validateExplanation(array $block, string $bid, array &$blocking, array &$warnings): void
    {
        $this->validateGrounded($block, $bid, $blocking);
        if (mb_strlen((string) ($block['body_md'] ?? '')) > self::EXPLANATION_LENGTH_WARNING) {
            $warnings[] = "{$bid}: explanation exceeds ".self::EXPLANATION_LENGTH_WARNING.' characters - wall-of-prose risk.';
        }
    }

    /** Explanations, summaries and examples that state academic truth must declare grounding. */
    private function validateGrounded(array $block, string $bid, array &$blocking): void
    {
        if (($block['grounding_reference_handles'] ?? []) === []) {
            $blocking[] = "{$bid}: must declare at least one grounding_reference_handle from the packet.";
        }
    }

    private function validateWidgetBlock(array $block, string $bid, $verdicts, array $allHandles, array $targets, array &$widgetPurposes, array &$blocking, array &$warnings): void
    {
        $key = $block['widget_key'] ?? null;
        if (is_string($block['config'] ?? null)) {
            $blocking[] = "{$bid}: widget config must be a JSON object, not a string.";

            return;
        }
        if (! $verdicts->has($key)) {
            $blocking[] = "{$bid}: widget '{$key}' is not in the packet shortlist.";

            return;
        }
        if ($verdicts[$key] !== 'compatible') {
            $blocking[] = "{$bid}: widget '{$key}' has verdict '{$verdicts[$key]}' and cannot be used.";
        }
        foreach ($block['reference_handles_demonstrated'] ?? [] as $handle) {
            if (! in_array($handle, $allHandles, true)) {
                $blocking[] = "{$bid}: widget demonstrates '{$handle}' which is not a packet handle.";
            }
        }
        if (($block['target_lo_refs'] ?? []) === []) {
            $blocking[] = "{$bid}: widget placement must declare target_lo_refs.";
        }
        foreach (['pedagogical_purpose', 'student_instruction', 'expected_observation', 'intended_inference'] as $field) {
            if (trim((string) ($block[$field] ?? '')) === '') {
                $blocking[] = "{$bid}: widget placement missing {$field}.";
            }
        }
        $widgetPurposes[$key][] = (string) ($block['pedagogical_purpose'] ?? '');

        foreach ($this->widgetConfigErrors((string) $key, $block['config'] ?? null) as $error) {
            $blocking[] = "{$bid}: {$error}";
        }
    }

    /** Minimal deterministic per-widget config validation for the shortlisted widgets. */
    private function widgetConfigErrors(string $key, mixed $config): array
    {
        if (! is_array($config) || $config === []) {
            return ['widget config missing or empty.'];
        }
        $errors = [];
        if ($key === 'graph_explorer') {
            $views = $config['views'] ?? null;
            if ($views !== null) {
                foreach ((array) $views as $i => $view) {
                    foreach (['id', 'label'] as $f) {
                        if (trim((string) ($view[$f] ?? '')) === '') {
                            $errors[] = "views[{$i}] missing {$f}.";
                        }
                    }
                }
                if (! isset($config['curve']) && collect((array) $views)->contains(fn ($v) => ! isset($v['curve']))) {
                    $errors[] = 'every view needs a curve (or a base curve fallback).';
                }
            } elseif (! isset($config['curve'])) {
                $errors[] = 'graph_explorer config needs a curve.';
            }
            if (! empty($config['gradientTriangle']) && empty($config['gradientUnit'])) {
                $topUsesGradient = in_array('gradient', $config['modes'] ?? [], true) || ($config['read'] ?? null) === 'gradient';
                $viewMissingUnit = collect((array) ($views ?? []))
                    ->contains(fn ($v) => (in_array('gradient', $v['modes'] ?? [], true) || ($v['read'] ?? null) === 'gradient') && empty($v['gradientUnit']));
                if ($topUsesGradient || $viewMissingUnit) {
                    $errors[] = 'gradientTriangle requires an explicit gradientUnit (no derived unit concatenation).';
                }
            }
            if (! empty($config['areaAdjustable']) && empty($config['areaUnit'])) {
                $errors[] = 'areaAdjustable requires an explicit areaUnit.';
            }
        }
        if ($key === 'graph_regions') {
            if (! isset($config['curve']['points']) || ! is_array($config['curve']['points']) || count($config['curve']['points']) < 2) {
                $errors[] = 'graph_regions config needs curve.points with at least two points.';
            }
        }

        return $errors;
    }

    private function validateGuidedExample(array $block, string $bid, array $relationshipHandles, array &$blocking, array &$warnings): void
    {
        $this->validateGrounded($block, $bid, $blocking);
        if (($block['steps'] ?? []) === []) {
            $blocking[] = "{$bid}: guided example must have structured reasoning steps.";
        }
        $final = $block['final_answer'] ?? null;
        if ($final !== null && trim((string) ($final['value'] ?? '')) !== '' && trim((string) ($final['unit'] ?? '')) === '') {
            $blocking[] = "{$bid}: a numeric final_answer must state its unit.";
        }
        if (! array_key_exists('relationships_used', $block)) {
            $blocking[] = "{$bid}: guided example must declare relationships_used ([] when none apply).";
        }
    }

    private function validateMisconception(array $block, string $bid, array $misconceptionIds, array &$blocking): void
    {
        if (isset($block['misconception_id'])) {
            if (! in_array($block['misconception_id'], $misconceptionIds, true)) {
                $blocking[] = "{$bid}: misconception id '{$block['misconception_id']}' does not exist in the packet.";
            }
        } elseif (trim((string) ($block['unverified_proposal'] ?? '')) === '') {
            $blocking[] = "{$bid}: misconception intervention must carry a packet id or a classified unverified_proposal.";
        }
        foreach (['trigger_context', 'incorrect_reasoning', 'correction_md'] as $field) {
            if (trim((string) ($block[$field] ?? '')) === '') {
                $blocking[] = "{$bid}: misconception intervention missing {$field}.";
            }
        }
    }

    private function validateCheck(array $block, string $bid, array $targets, array $evidenceIds, array $evidenceStems, array $commandWords, array &$coveredByCheck, array &$responseTypes, array &$blocking, array &$warnings): void
    {
        $refs = $block['target_lo_refs'] ?? [];
        if ($refs === []) {
            $blocking[] = "{$bid}: formative check must target at least one approved LO.";
        }
        foreach ($refs as $ref) {
            if (in_array($ref, $targets, true)) {
                $coveredByCheck[] = $ref;
            }
        }
        $rt = $block['response_type'] ?? null;
        if (! in_array($rt, self::RESPONSE_TYPES, true)) {
            $blocking[] = "{$bid}: unsupported response_type '{$rt}'.";
        } else {
            $responseTypes[] = $rt;
        }
        if (! empty($block['command_word']) && ! in_array($block['command_word'], $commandWords, true)) {
            $blocking[] = "{$bid}: command word '{$block['command_word']}' is not in the packet policy slice.";
        }

        $ids = $block['evidence_question_ids'] ?? null;
        if (! is_array($ids)) {
            $blocking[] = "{$bid}: check must declare evidence_question_ids as an array ([] with evidence_independence_reason when independent).";
        } elseif ($ids === [] && trim((string) ($block['evidence_independence_reason'] ?? '')) === '') {
            $blocking[] = "{$bid}: check cites no evidence and gives no evidence_independence_reason.";
        } else {
            foreach ($ids as $id) {
                if (! in_array($id, $evidenceIds, true)) {
                    $blocking[] = "{$bid}: check cites evidence id {$id} which is not packet evidence.";
                }
            }
        }

        // Evidence is a pattern, never copy: the check prompt must not contain a full evidence stem.
        $prompt = $this->normalize((string) ($block['prompt_md'] ?? ''));
        foreach ($evidenceStems as $stem) {
            if ($stem !== '' && mb_strlen($stem) > 40 && str_contains($prompt, $stem)) {
                $blocking[] = "{$bid}: check prompt contains a full assessment-evidence stem - evidence must not be copied.";
            }
        }

        // Server-only evaluation isolation.
        $evaluation = $block['evaluation'] ?? null;
        if (! is_array($evaluation) || ($evaluation['server_only'] ?? null) !== true) {
            $blocking[] = "{$bid}: evaluation must exist and be marked server_only=true.";
        } else {
            $mode = $evaluation['mode'] ?? null;
            if (! in_array($mode, ['auto', 'manual_review'], true)) {
                $blocking[] = "{$bid}: evaluation.mode must be 'auto' or 'manual_review' (never fake deterministic marking).";
            }
            if ($mode === 'auto' && ($evaluation['data'] ?? []) === []) {
                $blocking[] = "{$bid}: auto-evaluable check must carry evaluation.data (server-side).";
            }
        }
        // Answer keys must never sit outside the evaluation envelope.
        foreach (['answer', 'correct_answer', 'correct_option'] as $leak) {
            if (array_key_exists($leak, $block)) {
                $blocking[] = "{$bid}: '{$leak}' outside evaluation.data - answer keys are server-only.";
            }
        }
        foreach ($block['options'] ?? [] as $oi => $option) {
            foreach (['correct', 'is_correct', 'answer'] as $leak) {
                if (is_array($option) && array_key_exists($leak, $option)) {
                    $blocking[] = "{$bid}: options[{$oi}] carries '{$leak}' - correctness lives only in evaluation.data.";
                }
            }
        }
    }

    private function validateTutorCheckpoint(array $block, string $bid, array $targets, array $allHandles, int &$count, array &$blocking): void
    {
        $count++;
        foreach (['current_concept', 'permitted_scope'] as $field) {
            if (trim((string) ($block[$field] ?? '')) === '') {
                $blocking[] = "{$bid}: tutor checkpoint missing {$field}.";
            }
        }
        if (($block['target_lo_refs'] ?? []) === []) {
            $blocking[] = "{$bid}: tutor checkpoint must declare target_lo_refs.";
        }
        if (($block['useful_question_types'] ?? []) === []) {
            $blocking[] = "{$bid}: tutor checkpoint must declare useful_question_types.";
        }
        foreach ($block['grounding_reference_handles'] ?? [] as $handle) {
            if (! in_array($handle, $allHandles, true)) {
                $blocking[] = "{$bid}: tutor checkpoint grounds on '{$handle}' which is not a packet handle.";
            }
        }
    }

    /** Same normalization family as SyllabusReferenceGuard (delta/superscript/space). */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['∆' => 'δ', 'Δ' => 'δ', '²' => '2', '×' => '*']);

        return preg_replace('/\s+/u', '', $text);
    }
}
