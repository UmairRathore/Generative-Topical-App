<?php

namespace App\Services\V2;

use App\Models\V2\Question;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Support\LearningAssetValidator;
use InvalidArgumentException;

/**
 * Builds the deterministic, server-side context handed to a lesson-authoring
 * system (e.g. Fable). Same inputs => byte-identical output: no timestamps,
 * stable ordering everywhere.
 *
 * v2: the academic-truth portion resolves through the Canonical Syllabus
 * Reference Library (SyllabusReferenceResolver) - relevance-sliced
 * terminology/quantities/definitions/relationships/constants + policy
 * slices + LO-scoped hard constraints - instead of dumping whole syllabus
 * policies and asking the authoring system to infer relevance.
 *
 * Trust rules enforced here, not left to the caller:
 *  - only VALIDATED learning objectives, references and policies enter
 *    (extracted rows are refused, or explicitly flagged via $includeDraftLos);
 *  - only VISIBLE (approved/edited) learning assets are surfaced; drafts
 *    appear as counts only;
 *  - the source document is identified by version label + sha256.
 *
 * The context contains correct answers and canonical syllabus wording - it is
 * authoring infrastructure and must NEVER be serialized into student-facing
 * responses. Returns ['context' => ..., 'manifest' => audit rows].
 */
class AuthoringContextBuilder
{
    public const SCHEMA = 'topicaled.authoring-context.v3';

    /** Ordered source-of-truth doctrine embedded in every context (shared with the Stage A packet compiler). */
    public const SOURCE_PRECEDENCE = [
        ['rank' => 1, 'source' => 'official_versioned_syllabus', 'authoritative_for' => 'curriculum scope, learning-objective wording, qualification depth, terminology, notation, symbols/units, explicit exclusions'],
        ['rank' => 2, 'source' => 'v2_curriculum_ids', 'authoritative_for' => 'application mapping only (topic/subtopic ids); never curriculum meaning'],
        ['rank' => 3, 'source' => 'approved_internal_learning_assets_and_answers', 'authoritative_for' => "TopicalEd's existing explanations, worked solutions and canonical answer data"],
        ['rank' => 4, 'source' => 'curated_question_corpus', 'authoritative_for' => 'assessment patterns and practice references'],
        ['rank' => 5, 'source' => 'fable_generated_prose', 'authoritative_for' => 'original authored lesson expression ONLY - never curriculum scope or canonical facts'],
    ];

    /** Hard authoring constraints embedded in every context (shared with the Stage A packet compiler). */
    public const AUTHORING_RULES = [
        'Do not copy syllabus paragraphs into student lesson prose; the syllabus grounds authoring, it is not lesson copy.',
        'Do not copy textbook prose; final explanatory prose must be original expression.',
        'Do not broaden a learning objective beyond its explicit syllabus depth; treat depth constraints as hard bounds.',
        'Preserve exclusions ("... is not required") as hard constraints - excluded content must not appear in authored lessons.',
        'Use canonical scientific vocabulary from academic_references in definitions and summaries; informal synonyms are not replacements.',
        'Analogies are allowed but must bridge back to canonical terminology.',
        'Use command words consistently with their official semantics when writing checks or exercises.',
        'Resolve formulas, symbols, units and definitions from academic_references - never reconstruct them from memory or prose.',
        'supporting_references are context for teaching only - they NEVER add their establishing sibling objective to lesson coverage.',
        'A guard verdict of "unknown" is NOT approval: content the canonical library makes no claim about must not be taught as syllabus fact.',
        'Questions are assessment evidence, not curriculum authority; widgets are capabilities, not curriculum authority.',
        'This context is server-side authoring input; never expose it (correct answers included) to students.',
    ];

    private const ASSET_CONTENT_CAP = 4000;

    public function __construct(
        private SyllabusReferenceResolver $resolver,
        private string $widgetRegistryPath = 'resources/js/widgets/registry.js',
    ) {}

    /**
     * @param  array<int, int>  $objectiveNumbers  official objective numbers within the
     *                                             subtopic's leaf section; empty = every validated objective of the leaf.
     * @return array{context: array, manifest: array<int, array>}
     */
    public function build(
        SyllabusSource $source,
        Subtopic $subtopic,
        array $objectiveNumbers = [],
        bool $includeDraftLos = false,
        int $maxQuestions = 200,
    ): array {
        $topic = $subtopic->topic;
        if (! $topic || $topic->subject_id !== $source->subject_id) {
            throw new InvalidArgumentException(
                "Subtopic #{$subtopic->id} does not belong to syllabus source {$source->reference()}'s subject."
            );
        }

        // The academic-truth portion: selection, references, slices, constraints.
        $package = $this->resolver->resolve($source, $subtopic, $objectiveNumbers, $includeDraftLos)['package'];
        $warnings = $package['validation']['warnings'];

        [$assets, $draftCounts] = $this->loadAssets($subtopic);
        $corpus = $this->questionCorpus($subtopic, $maxQuestions, $warnings);
        $widgets = $this->widgetTypes($warnings);

        $subject = $source->subject;

        $context = [
            'context_schema' => self::SCHEMA,
            'source_precedence' => self::SOURCE_PRECEDENCE,
            'authoring_rules' => self::AUTHORING_RULES,

            'qualification' => [
                'subject' => $subject->name,
                'syllabus_code' => $source->syllabus_code,
                'level' => $subject->level,
                'syllabus_version' => $source->version_label,
                'syllabus_title' => $source->title,
                'exam_years' => $source->exam_years,
                'source_pdf_sha256' => $source->source_pdf_sha256,
            ],

            'curriculum' => array_filter([
                'topic' => $package['scope']['topic'],
                'parent_section' => $package['scope']['parent_section'],
                'leaf_section' => $package['scope']['leaf_section'],
            ]),

            'learning_objectives' => $package['scope']['selected_objectives'],
            'out_of_scope_objectives' => $package['scope']['out_of_scope_objectives'],

            // Canonical academic truth, relevance-sliced by the reference library.
            'academic_references' => $package['references'],
            'supporting_references' => $package['supporting_references'],
            'policy_slices' => $package['policy_slices'],
            'hard_constraints' => $package['hard_constraints'],

            'internal_assets' => [
                'approved' => $assets,
                'draft_counts_by_type' => $draftCounts,
            ],

            'question_corpus' => $corpus,

            'widgets' => $widgets,

            'validation' => [
                'objectives_all_validated' => $package['validation']['objectives_all_validated'],
                'warnings' => $warnings,
            ],
        ];

        // Audit manifest: what entered the context, from where.
        $manifest = [
            ['item_type' => 'syllabus_source', 'ref_table' => 'v2_syllabus_sources', 'ref_id' => $source->id, 'meta' => ['sha256' => $source->source_pdf_sha256]],
        ];
        foreach ($package['scope']['selected_objectives'] as $lo) {
            $manifest[] = ['item_type' => 'learning_objective', 'ref_table' => 'v2_learning_objectives', 'ref_id' => null, 'meta' => ['ref' => $lo['reference']]];
        }
        foreach ($package['references'] as $group => $rows) {
            foreach ($rows as $row) {
                $manifest[] = ['item_type' => 'canonical_reference', 'ref_table' => 'v2_syllabus_references', 'ref_id' => null, 'meta' => ['group' => $group, 'key' => $row['key']]];
            }
        }
        foreach (array_keys($package['policy_slices']) as $type) {
            $manifest[] = ['item_type' => 'policy_slice', 'ref_table' => 'v2_syllabus_policies', 'ref_id' => null, 'meta' => ['policy_type' => $type]];
        }
        foreach ($assets as $asset) {
            $manifest[] = ['item_type' => 'learning_asset', 'ref_table' => 'v2_question_learning_assets', 'ref_id' => $asset['asset_id'], 'meta' => ['asset_type' => $asset['asset_type']]];
        }
        $manifest[] = ['item_type' => 'question_corpus', 'ref_table' => 'v2_questions', 'ref_id' => null, 'meta' => ['subtopic_id' => $subtopic->id, 'count' => $corpus['counts']['total']]];

        return ['context' => $context, 'manifest' => $manifest];
    }

    /**
     * Approved/edited assets for the subtopic's questions (content inlined,
     * capped) + draft counts by type. Draft content is never inlined: it is
     * unreviewed AI output and sits below the approved tier in the precedence.
     *
     * @return array{0: array<int, array>, 1: array<string, int>}
     */
    private function loadAssets(Subtopic $subtopic): array
    {
        $questionIds = Question::withoutGlobalScopes()
            ->where('subtopic_id', $subtopic->id)
            ->pluck('id');

        $approved = QuestionLearningAsset::whereIn('question_id', $questionIds)
            ->whereIn('status', QuestionLearningAsset::VISIBLE_STATUSES)
            ->orderBy('id')
            ->get()
            ->map(fn (QuestionLearningAsset $a) => array_filter([
                'asset_id' => $a->id,
                'question_id' => $a->question_id,
                'asset_type' => $a->asset_type,
                'title' => $a->title,
                'content' => $a->content !== null ? mb_substr($a->content, 0, self::ASSET_CONTENT_CAP) : null,
            ], fn ($v) => $v !== null))
            ->values()
            ->all();

        $draftCounts = QuestionLearningAsset::whereIn('question_id', $questionIds)
            ->whereIn('status', QuestionLearningAsset::PENDING_STATUSES)
            ->selectRaw('asset_type, count(*) as n')
            ->groupBy('asset_type')
            ->orderBy('asset_type')
            ->pluck('n', 'asset_type')
            ->map(fn ($n) => (int) $n)
            ->all();

        return [$approved, $draftCounts];
    }

    /** Compact corpus stats + per-question reference rows (ordered, capped). */
    private function questionCorpus(Subtopic $subtopic, int $maxQuestions, array &$warnings): array
    {
        $base = Question::withoutGlobalScopes()->where('subtopic_id', $subtopic->id);

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->orderBy('id')
            ->limit($maxQuestions)
            ->get(['id', 'question_number', 'source_paper', 'year', 'layout_type', 'correct_answer', 'status', 'needs_review']);

        if ($total > $maxQuestions) {
            $warnings[] = "Question corpus truncated to {$maxQuestions} of {$total} rows.";
        }

        return [
            'counts' => [
                'total' => $total,
                'active' => (clone $base)->where('status', 'active')->count(),
                'answerable' => (clone $base)->whereNotNull('correct_answer')->count(),
                'needs_review' => (clone $base)->where('needs_review', true)->count(),
            ],
            'year_range' => [
                'from' => (clone $base)->min('year'),
                'to' => (clone $base)->max('year'),
            ],
            'layout_mix' => (clone $base)
                ->selectRaw('layout_type, count(*) as n')
                ->groupBy('layout_type')
                ->orderBy('layout_type')
                ->pluck('n', 'layout_type')
                ->map(fn ($n) => (int) $n)
                ->all(),
            'questions' => $rows->map(fn (Question $q) => [
                'id' => $q->id,
                'question' => "{$q->source_paper} Q{$q->question_number}",
                'year' => $q->year,
                'layout_type' => $q->layout_type,
                'correct_answer' => $q->correct_answer,
                'status' => $q->status,
                'needs_review' => (bool) $q->needs_review,
            ])->all(),
        ];
    }

    /**
     * Widget type vocabulary. The JS registry is the source of truth; the
     * hand-maintained PHP constant is only a fallback (known to lag - it was
     * already missing plank_moments_3d at foundation time).
     */
    private function widgetTypes(array &$warnings): array
    {
        $path = is_file($this->widgetRegistryPath)
            ? $this->widgetRegistryPath
            : base_path($this->widgetRegistryPath);

        if (is_file($path)
            && preg_match_all('/^\s*([a-z0-9_]+):\s*\(\)\s*=>\s*import\(/m', file_get_contents($path), $m)) {
            $types = $m[1];
            sort($types);

            return ['source' => $this->widgetRegistryPath, 'types' => $types];
        }

        $warnings[] = 'Widget registry JS not parseable - fell back to LearningAssetValidator::KNOWN_WIDGETS (may lag the registry).';
        $types = LearningAssetValidator::KNOWN_WIDGETS;
        sort($types);

        return ['source' => 'App\\Support\\LearningAssetValidator::KNOWN_WIDGETS (fallback)', 'types' => $types];
    }
}
