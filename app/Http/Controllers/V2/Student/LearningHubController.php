<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\ExamAnswer;
use App\Models\V2\ExamQuestion;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\StudentMistake;
use App\Services\V2\MistakeBankService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
