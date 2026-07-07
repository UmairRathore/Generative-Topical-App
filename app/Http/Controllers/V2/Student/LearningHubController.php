<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\ExamAnswer;
use App\Models\V2\ExamQuestion;
use App\Models\V2\Question;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use App\Support\SignedImage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Learning Hub - "My Mistakes". Thin controller; all logic lives in
 * MistakeBankService. Students only ever see their own, release-gated mistakes.
 */
class LearningHubController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    public function index(Request $request, MistakeBankService $service)
    {
        $student = $this->student();

        $filters = [
            'filter'         => $request->string('filter')->toString() ?: 'all',
            'subject_id'     => $request->integer('subject_id') ?: null,
            'topic_id'       => $request->integer('topic_id') ?: null,
            'difficulty'     => $request->string('difficulty')->toString() ?: null,
            'hide_completed' => $request->boolean('hide_completed'),
        ];
        $sort = $request->string('sort')->toString() ?: 'grouped';

        return view('v2.student.learning_hub.index', [
            'mistakes'        => $service->list($student, $filters, $sort),
            'analytics'       => $service->analytics($student),
            'subjects'        => $service->subjectOptions($student),
            'topicsBySubject' => $service->topicOptions($student),
            'filters'         => $filters,
            'sort'            => $sort,
        ]);
    }

    /** One mistake: the frozen question review (shared answer_review) + any worked solution. */
    public function review(StudentMistake $mistake, MistakeBankService $service)
    {
        $this->authorizeMistake($mistake);

        $exam = $mistake->latestExam;
        abort_unless($exam && $exam->resultsReleased(), 404);

        // Reuse the shared answer_review partial with the REAL source exam, narrowed
        // to just this question (real ExamQuestion + ExamAnswer - not a fake object).
        $exam->setRelation('examQuestions', ExamQuestion::where('exam_id', $exam->id)
            ->where('question_id', $mistake->question_id)
            ->with(['questionVersion', 'question.options', 'question.images'])
            ->get());
        $exam->renderFrozenQuestions();

        $answers = $mistake->latest_wrong_attempt_id
            ? ExamAnswer::where('attempt_id', $mistake->latest_wrong_attempt_id)
                ->where('question_id', $mistake->question_id)->get()->keyBy('question_id')
            : collect();

        // Opening the review counts as reviewing it.
        $service->markReviewed($mistake);

        return view('v2.student.learning_hub.review', [
            'mistake'  => $mistake->load(['subject:id,name', 'topic:id,title', 'latestExam:id,title']),
            'exam'     => $exam,
            'answers'  => $answers,
            'solution' => $service->visibleAsset($mistake->question_id, 'worked_solution'),
        ]);
    }

    /**
     * The React "Learning Studio" for one mistake (Inertia). The Mistake Bank's
     * "Worked solution" button lands here. It surfaces EVERY release-gated
     * learning asset the question has — worked solution, why-each-option-is-wrong,
     * the interactive simulation, flashcards, memcards and a solution flow —
     * as tabs. Tabs auto-appear as assets are imported; no code change needed.
     */
    public function studio(StudentMistake $mistake, MistakeBankService $service)
    {
        $this->authorizeMistake($mistake);
        abort_unless($mistake->latestExam && $mistake->latestExam->resultsReleased(), 404);

        $mistake->load(['subject:id,name', 'topic:id,title']);
        $qid = $mistake->question_id;

        // Text / structured assets, keyed by type.
        $types = ['worked_solution', 'option_explanation', 'flashcards', 'memcards',
            'mermaid', 'revision_notes', 'common_mistakes'];
        $assets = [];
        foreach ($types as $t) {
            if ($a = $service->visibleAsset($qid, $t)) {
                $assets[$t] = ['title' => $a->title, 'content' => $a->content, 'format' => $a->format, 'payload' => $a->payload_json];
            }
        }

        $widget = $service->visibleAsset($qid, 'interactive_widget');
        $wp = $widget?->payload_json;

        // Opening the studio counts as a review + records what was shown.
        $service->markReviewed($mistake);
        foreach (array_keys($assets) as $t) {
            $service->recordAssetViewed($mistake, $t);
        }
        if ($widget) {
            $service->recordAssetViewed($mistake, 'interactive_widget');
        }

        $q = Question::withoutGlobalScopes()->with('images')->find($qid);

        // The actual exam figure(s) — the question diagram, not option/table crops.
        // Served through the signed, viewer-bound secure-image endpoint.
        $figures = $q ? $q->images
            ->filter(fn ($im) => $im->option_label === null && preg_match('/question|diagram|figure/i', (string) $im->role))
            ->sortBy('sort_order')
            ->map(fn ($im) => [
                'url'     => SignedImage::url($im->image_path, ['question_id' => $qid]),
                'caption' => $im->caption,
            ])->values()->all() : [];

        return Inertia::render('Solution', [
            'mistake' => [
                'id'      => $mistake->id,
                'subject' => $mistake->subject?->name,
                'topic'   => $mistake->topic?->title,
            ],
            'question' => $q ? [
                'stem'        => $q->question_text,
                'correct'     => $q->correct_answer,
                'yourAnswer'  => $mistake->selected_option,
                'sourcePaper' => $q->source_paper,
                'images'      => $figures,
            ] : null,
            'assets' => $assets,
            'widget' => $wp ? [
                'type'   => $wp['widget'] ?? $widget->asset_key,
                'config' => $wp['config'] ?? $wp,
            ] : null,
            'backUrl' => route('v2.student.learning_hub.review', $mistake),
            // "Add to notes" bridge: the picker needs the tree + create endpoints,
            // and every import is anchored to this mistake. suggest = the
            // subject/topic home the picker should preselect (or offer to create).
            'notes' => [
                'mistakeId'  => $mistake->getRouteKey(),
                'tree'       => route('v2.student.notes.tree'),
                'createPage' => route('v2.student.notes.pages.store'),
                'suggest'    => [
                    'subjectId'   => $mistake->subject_id,
                    'topicId'     => $mistake->topic_id,
                    'subjectName' => $mistake->subject?->name,
                    'topicName'   => $mistake->topic?->title,
                ],
            ],
        ]);
    }

    public function updateStatus(StudentMistake $mistake, Request $request, MistakeBankService $service)
    {
        $this->authorizeMistake($mistake);

        $data = $request->validate([
            'action' => ['required', Rule::in(['mastered', 'reset', 'archive'])],
        ]);

        match ($data['action']) {
            'mastered' => $service->markMastered($mistake),
            'reset'    => $service->reopen($mistake),
            'archive'  => $service->archive($mistake),
        };

        return back()->with('success', match ($data['action']) {
            'mastered' => 'Marked as mastered - it stays in your history.',
            'reset'    => 'Moved back into your revision list.',
            'archive'  => 'Archived - hidden from the active list.',
        });
    }

    /** Display-only asset fetch (AJAX). Returns the stored asset or an "unavailable" flag. Never generates. */
    public function asset(StudentMistake $mistake, string $type, MistakeBankService $service)
    {
        $this->authorizeMistake($mistake);
        abort_unless(in_array($type, QuestionLearningAsset::DISPLAYABLE_TYPES, true), 404);
        abort_unless($mistake->latestExam && $mistake->latestExam->resultsReleased(), 404);

        $asset = $service->visibleAsset($mistake->question_id, $type);
        if ($asset) {
            $service->recordAssetViewed($mistake, $type);
        }

        return response()->json([
            'available' => (bool) $asset,
            'title'     => $asset?->title,
            'format'    => $asset?->format ?? 'markdown',
            'content'   => $asset?->content,
        ]);
    }

    /** A student may only ever touch their own mistake (school is already scoped globally). */
    private function authorizeMistake(StudentMistake $mistake): void
    {
        abort_unless($mistake->student_id === $this->student()->id, 403);
    }
}
