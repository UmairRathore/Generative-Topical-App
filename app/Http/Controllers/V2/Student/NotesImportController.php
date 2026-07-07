<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\NotesPage;
use App\Models\V2\Question;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use App\Services\V2\NotesBlockMapper;
use App\Services\V2\NotesDocumentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The "add to notes" bridge - platform content becomes note blocks.
 *
 * Every import is anchored to one of the student's own release-gated
 * mistakes (mirrors LearningHubController@asset): assets are only ever
 * reachable through a mistake, so a student can't harvest assets for
 * questions they've never met. Returns the inserted blocks so the client
 * splices them into the live editor without a reload.
 */
class NotesImportController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    public function store(
        NotesPage $page,
        Request $request,
        MistakeBankService $mistakes,
        NotesBlockMapper $mapper,
        NotesDocumentService $service,
    ) {
        $this->authorizePage($page);

        $data = $request->validate([
            'source'          => ['required', Rule::in(['mistake', 'asset', 'widget_state'])],
            'mistake_id'      => ['required', 'string'],
            'asset_type'      => ['required_if:source,asset', 'string', Rule::in(QuestionLearningAsset::ALLOWED_TYPES)],
            'widget'          => ['required_if:source,widget_state', 'string', 'max:64'],
            'config'          => ['sometimes', 'array'],
            // Insert before this top-level block (a page_break id from the
            // outline, or a cursor block id). Absent/unknown -> append at end.
            'before_block_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        // Anchor: the student's own, release-gated mistake.
        $mistake = (new StudentMistake)->resolveRouteBinding($data['mistake_id']);
        abort_unless($mistake && $mistake->student_id === $this->student()->id, 404);
        abort_unless($mistake->latestExam && $mistake->latestExam->resultsReleased(), 404);

        // The question + its diagram images, loaded once (scope-free like the Studio).
        $mistake->load(['subject:id,name', 'topic:id,title']);
        $question = Question::withoutGlobalScopes()->with('images')->find($mistake->question_id);
        $mistake->setRelation('question', $question);

        $blocks = match ($data['source']) {
            'mistake' => $mapper->fromMistake($mistake, $mistakes->visibleAsset($mistake->question_id, 'option_explanation')),
            'asset' => $this->assetBlocks($mistake, $question, $data['asset_type'], $mistakes, $mapper),
            'widget_state' => $this->widgetStateBlocks($mistake, $data, $mistakes, $mapper),
        };

        if ($blocks === []) {
            return response()->json(['error' => 'unavailable'], 404);
        }

        $before = $data['before_block_id'] ?? null;
        // Don't add the same diagram twice within the section it's landing in.
        $blocks = $this->withoutDuplicateFigures($page->document_json ?? [], $blocks, $before);

        $service->apply($page, $this->spliced($page->document_json ?? [], $blocks, $before));

        // Auto-tag the page from the mistake's curriculum when not already set.
        if (! $page->subject_id) {
            $page->forceFill([
                'subject_id'  => $mistake->subject_id,
                'topic_id'    => $page->topic_id ?: $mistake->topic_id,
                'subtopic_id' => $page->subtopic_id ?: $mistake->subtopic_id,
            ])->save();
        }

        return response()->json([
            'ok'             => true,
            'blocks'         => $blocks,
            'contentVersion' => $page->content_version,
            'pageUrl'        => route('v2.student.notes.pages.show', $page),
        ]);
    }

    /**
     * One visible (approved/edited) asset -> blocks. The question-context text
     * assets (the worked solution / per-option explanations) also carry the
     * question diagram for context, prepended so it reads before the prose.
     */
    private function assetBlocks(StudentMistake $mistake, ?Question $question, string $type, MistakeBankService $mistakes, NotesBlockMapper $mapper): array
    {
        $asset = $mistakes->visibleAsset($mistake->question_id, $type);
        if (! $asset) {
            return [];
        }

        $blocks = $mapper->fromAsset($asset);

        if (in_array($type, ['worked_solution', 'option_explanation'], true)) {
            $blocks = array_merge($mapper->questionFigureBlocks($question), $blocks);
        }

        return $blocks;
    }

    /**
     * A widget saved via the live `camb:add-to-note` contract. The config is the
     * student's own adjusted state; the widget TYPE must match the question's
     * visible widget so arbitrary widget/config pairs can't be injected.
     */
    private function widgetStateBlocks(StudentMistake $mistake, array $data, MistakeBankService $mistakes, NotesBlockMapper $mapper): array
    {
        $asset = $mistakes->visibleAsset($mistake->question_id, 'interactive_widget');
        $ownType = (string) (($asset?->payload_json['widget'] ?? null) ?: $asset?->asset_key);
        if (! $asset || $ownType !== $data['widget']) {
            return [];
        }

        return [$mapper->widgetStateBlock($data['widget'], $data['config'] ?? [])];
    }

    /**
     * Drop question_figure blocks whose diagram already appears in the SECTION
     * the import lands in (the page-break region around the insertion point).
     * Keeps one diagram per book-style page without ever duplicating it beside
     * an existing copy. Non-figure blocks pass through untouched.
     */
    private function withoutDuplicateFigures(array $document, array $blocks, ?string $beforeId): array
    {
        $present = $this->sectionFigurePaths($document, $beforeId);
        if ($present === []) {
            return $blocks;
        }

        return array_values(array_filter($blocks, function ($b) use ($present) {
            if (($b['type'] ?? '') !== 'question_figure') {
                return true;
            }

            return ! in_array($b['props']['imagePath'] ?? '', $present, true);
        }));
    }

    /** Image paths of question_figure blocks already in the target section. */
    private function sectionFigurePaths(array $document, ?string $beforeId): array
    {
        $n = count($document);
        $i = $n;
        if ($beforeId !== null) {
            foreach ($document as $idx => $b) {
                if (($b['id'] ?? null) === $beforeId) {
                    $i = $idx;
                    break;
                }
            }
        }

        // Section = between the nearest page_break before the insertion point and the next one after it.
        $start = 0;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (($document[$j]['type'] ?? '') === 'page_break') {
                $start = $j + 1;
                break;
            }
        }
        $end = $n;
        for ($j = $i; $j < $n; $j++) {
            if (($document[$j]['type'] ?? '') === 'page_break') {
                $end = $j;
                break;
            }
        }

        $paths = [];
        for ($j = $start; $j < $end; $j++) {
            if (($document[$j]['type'] ?? '') === 'question_figure') {
                $p = $document[$j]['props']['imagePath'] ?? '';
                if ($p !== '') {
                    $paths[] = $p;
                }
            }
        }

        return $paths;
    }

    /** Insert $blocks before the given top-level block id; unknown/absent id appends. */
    private function spliced(array $document, array $blocks, ?string $beforeId): array
    {
        if ($beforeId !== null) {
            foreach ($document as $i => $block) {
                if (($block['id'] ?? null) === $beforeId) {
                    return array_merge(array_slice($document, 0, $i), $blocks, array_slice($document, $i));
                }
            }
        }

        return array_merge($document, $blocks);
    }

    private function authorizePage(NotesPage $page): void
    {
        abort_unless($page->student_id === $this->student()->id, 403);
    }
}
