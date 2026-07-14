<?php

namespace App\Services\V2;

use App\Models\V2\EvidenceCompatibility;
use App\Models\V2\LearningObjective;
use App\Models\V2\Question;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Support\LearningAssetValidator;

/**
 * Compiles the Stage A (lesson-plan) Fable packet: the deterministic,
 * inspectable, server-only input for planning ONE lesson over selected
 * target Learning Objectives. Strictly smaller and stricter than the broad
 * authoring context:
 *
 *  - target coverage vs supporting academic truth are structurally separate;
 *  - assessment evidence is a small deterministic selection (era-compatible
 *    with the syllabus source, active, answerable, clean-review, capped),
 *    explicitly non-authoritative;
 *  - widgets come from a curated per-leaf shortlist, each guard-checked:
 *    a declared formula that is excluded for the target LOs vetoes the widget;
 *  - draft learning assets never appear, not even as counts.
 *
 * SERVER-ONLY: contains correct answers and canonical wording. Must never be
 * serialized into any student-facing response or persisted into AI
 * interaction logs wholesale.
 */
class LessonPlanPacketCompiler
{
    public const SCHEMA = 'topicaled.stage-a-packet.v2';

    /** Max representative questions included as assessment evidence. */
    public const MAX_EVIDENCE_QUESTIONS = 8;

    private const EVIDENCE_STEM_CAP = 500;

    public function __construct(
        private SyllabusReferenceResolver $resolver,
        private SyllabusReferenceGuard $guard,
        private string $widgetRegistryPath = 'resources/js/widgets/registry.js',
        private ?string $widgetShortlistDir = null, // defaults to the source's references/widgets dir
        private ?string $misconceptionDir = null,   // defaults to the source's references/misconceptions dir
        private ?string $evidenceDir = null,        // defaults to the source's references/evidence dir
    ) {}

    /** @return array{packet: array, warnings: array<int, string>} */
    public function compile(SyllabusSource $source, Subtopic $subtopic, array $objectiveNumbers): array
    {
        $package = $this->resolver->resolve($source, $subtopic, $objectiveNumbers, false)['package'];
        $warnings = $package['validation']['warnings'];

        $selectedLoIds = LearningObjective::where('syllabus_source_id', $source->id)
            ->where('subtopic_id', $subtopic->id)
            ->whereIn('objective_number', array_column($package['scope']['selected_objectives'], 'number'))
            ->pluck('id')
            ->all();

        $packet = [
            'packet_schema' => self::SCHEMA,
            'stage' => 'A - lesson plan proposal only; no student prose',

            'identity' => [
                'subject' => $package['scope']['syllabus']['subject'],
                'syllabus_code' => $package['scope']['syllabus']['syllabus_code'],
                'level' => $package['scope']['syllabus']['level'],
                'syllabus_version' => $package['scope']['syllabus']['version'],
                'source_pdf_sha256' => $package['scope']['syllabus']['source_pdf_sha256'],
                'topic' => $package['scope']['topic'],
                'parent_section' => $package['scope']['parent_section'] ?? null,
                'leaf_section' => $package['scope']['leaf_section'],
            ],

            'target_coverage' => [
                'note' => 'These and ONLY these objectives are what the lesson claims to teach; coverage/completion is evaluated against them.',
                'objectives' => $package['scope']['selected_objectives'],
            ],

            'out_of_scope_curriculum' => [
                'note' => 'Sibling objectives NOT covered by this lesson. Supporting references established by them do not add them to coverage.',
                'objectives' => $package['scope']['out_of_scope_objectives'],
            ],

            'direct_academic_truth' => $package['references'],
            'supporting_academic_truth' => $package['supporting_references'],

            // The exact machine handles a plan may cite - copy verbatim, never
            // derive from group names. Direct/supporting grouping never changes
            // a handle.
            'allowed_reference_handles' => [
                'direct' => $this->bucketHandles($package['references']),
                'supporting' => $this->bucketHandles($package['supporting_references']['references']),
            ],

            'hard_constraints' => $package['hard_constraints'],
            'policy_slices' => $package['policy_slices'],

            'internal_content_evidence' => $this->approvedAssets($subtopic),
            'assessment_evidence' => $this->assessmentEvidence($source, $subtopic, $warnings),
            'pedagogical_evidence' => $this->pedagogicalEvidence($source, $subtopic, $warnings),
            'relevant_widgets' => $this->shortlistedWidgets($source, $subtopic, $selectedLoIds, $package, $warnings),

            'source_precedence' => AuthoringContextBuilder::SOURCE_PRECEDENCE,
            'authoring_rules' => array_merge(AuthoringContextBuilder::AUTHORING_RULES, [
                'Fable plans pedagogy; it does not redefine canonical academic truth.',
                'Out-of-scope sibling objectives must not become lesson objectives.',
                'Every lesson phase must declare which target objective(s) it advances, or that it is supporting/prerequisite review.',
            ]),

            'validation' => [
                'objectives_all_validated' => $package['validation']['objectives_all_validated'],
                'warnings' => $warnings,
            ],
        ];

        return ['packet' => $packet, 'warnings' => $warnings];
    }

    /**
     * Pre-authoring safety checks (moved from the retired API experiment
     * command): run against a compiled packet BEFORE any authoring session
     * uses it. Returns human-readable failures; empty = safe to author.
     */
    public static function preAuthoringChecks(array $packet, array $expectedObjectiveNumbers): array
    {
        $failures = [];
        $targets = array_column($packet['target_coverage']['objectives'], 'number');
        sort($targets);
        sort($expectedObjectiveNumbers);

        if ($packet['validation']['warnings'] !== []) {
            $failures[] = 'packet has warnings: '.implode(' | ', $packet['validation']['warnings']);
        }
        if (! $packet['validation']['objectives_all_validated']) {
            $failures[] = 'not all target objectives are validated';
        }
        if (! preg_match('/^[0-9a-f]{64}$/', (string) $packet['identity']['source_pdf_sha256'])) {
            $failures[] = 'source PDF sha256 missing';
        }
        if ($targets !== $expectedObjectiveNumbers) {
            $failures[] = 'target LOs mismatch requested set';
        }
        if (array_intersect($targets, array_column($packet['out_of_scope_curriculum']['objectives'], 'number')) !== []) {
            $failures[] = 'sibling/out-of-scope overlap with targets';
        }
        foreach (['direct', 'supporting'] as $bucket) {
            if (($packet['allowed_reference_handles'][$bucket] ?? null) === null) {
                $failures[] = "allowed_reference_handles.{$bucket} missing";
            }
        }
        foreach ($packet['assessment_evidence']['questions'] as $q) {
            if (($q['needs_review'] ?? true) !== false) {
                $failures[] = "evidence question {$q['evidence_id']} not clean";
            }
        }
        if (($packet['assessment_evidence']['allowed_evidence_ids'] ?? []) === []) {
            $failures[] = 'assessment evidence ids not explicitly enumerated';
        }
        foreach ($packet['relevant_widgets']['widgets'] as $w) {
            if ($w['compatibility_verdict'] !== 'compatible') {
                $failures[] = "widget {$w['key']} is not compatible ({$w['compatibility_verdict']})";
            }
        }
        if (($packet['pedagogical_evidence']['misconceptions'] ?? []) === []) {
            $failures[] = 'no curated misconceptions in pedagogical_evidence';
        }
        if (str_contains(json_encode($packet), '"status":"draft"')) {
            $failures[] = 'draft asset content present in packet';
        }

        return $failures;
    }

    /** Flat sorted handle list of one direct/supporting bucket. */
    private function bucketHandles(array $grouped): array
    {
        $handles = [];
        foreach ($grouped as $rows) {
            foreach ($rows as $row) {
                if (isset($row['handle'])) {
                    $handles[] = $row['handle'];
                }
            }
        }
        sort($handles);

        return array_values(array_unique($handles));
    }

    /**
     * Curated pedagogical evidence (misconceptions) - NOT canonical syllabus
     * truth: high-value conceptual traps a planner should target, each with
     * provenance_type and grounding in packet reference handles.
     */
    private function pedagogicalEvidence(SyllabusSource $source, Subtopic $subtopic, array &$warnings): array
    {
        $dir = $this->misconceptionDir
            ?? dirname(base_path($source->source_pdf_path)).'/references/misconceptions';
        $file = rtrim($dir, '/\\')."/{$subtopic->external_id}.json";

        if (! is_file($file)) {
            $warnings[] = "No curated misconception list for {$subtopic->external_id} - pedagogical_evidence is empty.";

            return ['note' => 'Pedagogical evidence, not syllabus truth.', 'misconceptions' => [], 'allowed_misconception_ids' => []];
        }

        $entries = json_decode(file_get_contents($file), true)['misconceptions'] ?? [];

        return [
            'note' => 'Pedagogical evidence, not syllabus truth. Target these by id, or classify any new proposal as unverified_proposal.',
            'misconceptions' => $entries,
            'allowed_misconception_ids' => array_values(array_column($entries, 'id')),
        ];
    }

    /** Approved/edited assets only - draft content and draft counts never enter the packet. */
    private function approvedAssets(Subtopic $subtopic): array
    {
        $questionIds = Question::withoutGlobalScopes()->where('subtopic_id', $subtopic->id)->pluck('id');

        $assets = QuestionLearningAsset::whereIn('question_id', $questionIds)
            ->whereIn('status', QuestionLearningAsset::VISIBLE_STATUSES)
            ->orderBy('id')
            ->get()
            ->map(fn (QuestionLearningAsset $a) => array_filter([
                'asset_id' => $a->id,
                'question_id' => $a->question_id,
                'asset_type' => $a->asset_type,
                'status' => $a->status,
                'title' => $a->title,
                'content' => $a->content !== null ? mb_substr($a->content, 0, 4000) : null,
            ], fn ($v) => $v !== null))
            ->values()
            ->all();

        return [
            'note' => 'Approved/edited internal assets only. Draft AI output is excluded entirely.',
            'assets' => $assets,
        ];
    }

    /**
     * Representative Assessment Evidence Selector - deterministic, small,
     * explicitly non-authoritative. Era-compatible questions only (paper year
     * within/after the source's exam window: older papers were set under a
     * DIFFERENT syllabus and may probe removed content).
     */
    private function assessmentEvidence(SyllabusSource $source, Subtopic $subtopic, array &$warnings): array
    {
        $eraFrom = min($source->exam_years ?: [PHP_INT_MAX]);

        $base = Question::withoutGlobalScopes()
            ->where('subtopic_id', $subtopic->id)
            ->where('status', 'active')
            ->where('needs_review', false)
            ->whereNotNull('correct_answer');

        // Tier 1: questions from the authoring syllabus cycle itself.
        $selected = (clone $base)->where('year', '>=', $eraFrom)
            ->orderByDesc('year')
            ->orderBy('id')
            ->limit(self::MAX_EVIDENCE_QUESTIONS)
            ->get()
            ->each(fn ($q) => $q->evidence_classification = 'authoring_cycle');

        // Tier 2: explicitly compatible prior-cycle evidence - ONLY under an
        // active, non-stale, section-scoped compatibility decision. Prior-cycle
        // items keep their real paper/year identity and are clearly labelled;
        // compatibility never makes them curriculum authority.
        $compat = EvidenceCompatibility::where('new_syllabus_source_id', $source->id)
            ->where('section_code', $subtopic->external_id)
            ->where('status', EvidenceCompatibility::STATUS_ACTIVE)
            ->first();
        $compatFrom = null;
        if ($compat && $compat->isStale()) {
            $warnings[] = "Evidence compatibility {$compat->decisionReference()} is STALE (diff basis changed) - prior-cycle evidence withheld.";
            $compat = null;
        }
        if ($compat) {
            $compatFrom = min($compat->oldSource->exam_years ?: [PHP_INT_MAX]);
            if ($selected->count() < self::MAX_EVIDENCE_QUESTIONS) {
                $priorCycle = (clone $base)
                    ->whereBetween('year', [$compatFrom, $eraFrom - 1])
                    ->orderByDesc('year')
                    ->orderBy('id')
                    ->limit(self::MAX_EVIDENCE_QUESTIONS - $selected->count())
                    ->get()
                    ->each(function ($q) use ($compat, $source) {
                        $q->evidence_classification = 'compatible_prior_cycle';
                        $q->compat_decision = $compat;
                        $q->authoring_version = $source->version_label;
                    });
                $selected = $selected->concat($priorCycle);
            }
        }

        $legacyCleanCount = (clone $base)->where('year', '<', $compatFrom ?? $eraFrom)->count();

        if ($selected->isEmpty()) {
            $warnings[] = "No era-compatible (year >= {$eraFrom}) clean questions found for this leaf - assessment evidence is empty.";
        }

        // Curated includes: deterministic file-driven additions covering target
        // distinctions the newest-first base rule misses (e.g. a d-t gradient
        // item). Curated ids must still pass every base safety filter; each
        // carries its curation reason + file provenance.
        $curated = collect();
        $curationRef = null;
        $file = rtrim($this->evidenceDir
            ?? dirname(base_path($source->source_pdf_path)).'/references/evidence', '/\\')."/{$subtopic->external_id}.json";
        if (is_file($file)) {
            $curationRef = basename($file).'@'.hash_file('sha256', $file);
            foreach (json_decode(file_get_contents($file), true)['include'] ?? [] as $entry) {
                $id = (int) ($entry['evidence_id'] ?? 0);
                if ($selected->contains('id', $id)) {
                    continue; // already in the deterministic base set
                }
                // Curated ids still pass every safety filter AND era rule (same
                // cycle, or the explicit compatibility window when one is active).
                $question = (clone $base)->where('year', '>=', $compatFrom ?? $eraFrom)->where('id', $id)->first();
                if (! $question) {
                    $warnings[] = "Curated evidence {$id} fails the base safety/era filters - skipped.";

                    continue;
                }
                $question->curated_reason = (string) ($entry['reason'] ?? 'curated inclusion');
                if ($question->year < $eraFrom && $compat) {
                    $question->evidence_classification = 'compatible_prior_cycle';
                    $question->compat_decision = $compat;
                    $question->authoring_version = $source->version_label;
                }
                $curated->push($question);
            }
        }

        return [
            'note' => 'NON-AUTHORITATIVE assessment evidence: exam phrasing/pattern examples only. Never curriculum authority; canonical scope always wins. Server-only (contains answers).',
            'selection_rule' => 'active + answerable + needs_review=false + year >= '.$eraFrom
                .' (syllabus-era compatible), newest first, capped at '.self::MAX_EVIDENCE_QUESTIONS,
            'excluded_legacy_pool' => $legacyCleanCount.' clean pre-'.$eraFrom.' questions withheld (set under an earlier syllabus)',
            'allowed_evidence_ids' => $selected->pluck('id')->merge($curated->pluck('id'))->all(),
            'curation' => $curationRef,
            'questions' => $selected->concat($curated)->map(fn (Question $q) => array_filter([
                // evidence_id IS the real v2_questions id - the exact integer a
                // plan's checks must cite (no second identity system).
                'evidence_id' => $q->id,
                'question' => "{$q->source_paper} Q{$q->question_number}",
                'year' => $q->year,
                'layout_type' => $q->layout_type,
                'question_version_id' => $q->current_version_id,
                'needs_review' => false,
                'classification' => $q->evidence_classification ?? 'authoring_cycle',
                // Prior-cycle items keep their true paper identity and carry the
                // compatibility decision - never silently relabelled as current.
                'compatibility' => isset($q->compat_decision) ? [
                    'authoring_syllabus_version' => $q->authoring_version,
                    'evidence_cycle' => $q->compat_decision->oldSource->version_label,
                    'scope' => $q->compat_decision->section_code,
                    'decision' => $q->compat_decision->decisionReference(),
                    'role' => 'non-authoritative assessment evidence',
                ] : null,
                'reason_included' => isset($q->curated_reason)
                    ? 'curated: '.$q->curated_reason
                    : (($q->evidence_classification ?? '') === 'compatible_prior_cycle'
                        ? 'compatible prior-cycle evidence (see compatibility decision), active, answerable, clean review state'
                        : 'era-compatible, active, answerable, clean review state'),
                'stem' => $q->question_text !== null ? mb_substr($q->question_text, 0, self::EVIDENCE_STEM_CAP) : null,
                'correct_answer_server_only' => $q->correct_answer,
            ], fn ($v) => $v !== null))->all(),
        ];
    }

    /**
     * Curated per-leaf widget shortlist, each entry guard-checked: declared
     * formulas that are EXCLUDED for the target LO context veto the widget.
     * Registry membership and demonstrated-reference existence are verified.
     */
    private function shortlistedWidgets(SyllabusSource $source, Subtopic $subtopic, array $selectedLoIds, array $package, array &$warnings): array
    {
        $dir = $this->widgetShortlistDir
            ?? dirname(base_path($source->source_pdf_path)).'/references/widgets';
        $file = rtrim($dir, '/\\')."/{$subtopic->external_id}.json";

        if (! is_file($file)) {
            $warnings[] = "No curated widget shortlist for {$subtopic->external_id} - packet contains no widgets.";

            return ['note' => 'Widgets are capabilities, not curriculum authority.', 'widgets' => []];
        }

        $registryKeys = $this->registryKeys();
        $knownHandles = $this->packageHandles($package);
        $shortlist = json_decode(file_get_contents($file), true)['widgets'] ?? [];

        $widgets = [];
        foreach ($shortlist as $entry) {
            $verdict = 'compatible';
            $academicWarnings = [];

            if (! in_array($entry['key'] ?? '', $registryKeys, true)) {
                $warnings[] = "Shortlisted widget '{$entry['key']}' is not in the registry - dropped.";

                continue;
            }

            foreach ($entry['demonstrates'] ?? [] as $handle) {
                if (! in_array($handle, $knownHandles, true)) {
                    $academicWarnings[] = "demonstrates '{$handle}' which is not in this packet's academic truth";
                }
            }

            // The academic truth layer vetoes widgets carrying excluded formulas.
            foreach ($entry['formulas'] ?? [] as $formula) {
                $check = $this->guard->expressionVerdict($selectedLoIds, $formula);
                if ($check['verdict'] === 'excluded') {
                    $verdict = 'incompatible_excluded_content';
                    $academicWarnings[] = "renders '{$formula}' which is EXPLICITLY EXCLUDED for the selected objectives";
                } elseif ($check['verdict'] === 'unknown') {
                    $academicWarnings[] = "renders '{$formula}' which the canonical library makes no claim about (unknown is not approval)";
                }
            }

            $widgets[] = [
                'key' => $entry['key'],
                'capability' => $entry['capability'] ?? '',
                'relevant_to_objectives' => $entry['relevant_to_objectives'] ?? [],
                'demonstrates' => $entry['demonstrates'] ?? [],
                'compatibility_verdict' => $verdict,
                'academic_warnings' => $academicWarnings,
            ];
        }

        return [
            'note' => 'Curated capability shortlist - never curriculum authority. Widgets with an incompatible verdict MUST NOT be placed in the plan.',
            'widgets' => $widgets,
        ];
    }

    private function registryKeys(): array
    {
        $path = is_file($this->widgetRegistryPath)
            ? $this->widgetRegistryPath
            : base_path($this->widgetRegistryPath);

        if (is_file($path)
            && preg_match_all('/^\s*([a-z0-9_]+):\s*\(\)\s*=>\s*import\(/m', file_get_contents($path), $m)) {
            return $m[1];
        }

        return LearningAssetValidator::KNOWN_WIDGETS;
    }

    /** Every reference handle present in the packet (direct + supporting). */
    private function packageHandles(array $package): array
    {
        $prefix = [
            'terminology' => 'term', 'quantities' => 'quantity', 'definitions' => 'definition',
            'relationships' => 'relationship', 'constants' => 'constant',
        ];

        $handles = [];
        foreach (['references' => $package['references'], 'supporting' => $package['supporting_references']['references']] as $bucket) {
            foreach ($bucket as $group => $rows) {
                foreach ($rows as $row) {
                    $handles[] = $prefix[$group].':'.$row['key'];
                }
            }
        }

        return $handles;
    }
}
