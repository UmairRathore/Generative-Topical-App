<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\QualityReview;
use App\Models\V2\QuestionFlag;
use App\Services\V2\AuditLogger;
use App\Services\V2\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Super Admin: Quality Review queue
|--------------------------------------------------------------------------
| Every question under review has exactly one OPEN v2_quality_reviews row; the
| teacher/student question flags are its reports. Support decides an outcome:
|   • Correct  — our digital copy already matches the official Cambridge source.
|                Restore the question to active, close the review + its reports,
|                notify the reporting teacher. No version, no propagation.
|                (Handled here, inline — see markCorrect.)
|   • Cosmetic / Material — the correction needs a new content version, so
|                "Correct question" opens the Question Bank editor
|                (?quality_review_id). The outcome is recorded on save in
|                QuestionBankController::update. Material is additionally marked
|                propagation_pending for the Phase 3 historical-propagation job.
*/
class QuestionFlagController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'open';
        $status = in_array($status, ['open', 'decided'], true) ? $status : 'open';

        $reviews = QualityReview::where('status', $status)
            ->with([
                'question' => fn ($q) => $q->withTrashed()->with([
                    'subject:id,name,code,level',
                    'topic:id,external_id,title',
                    'options',
                    'images',
                ]),
                'reports' => fn ($r) => $r->with([
                    'teacher:id,name',
                    'student:id,name,roll_number',
                    'school:id,name',
                ])->latest(),
                'resultingVersion:id,version_number,question_id',
            ])
            ->latest()
            ->get()
            ->filter(fn ($r) => $r->question !== null)
            ->values();

        return view('v2.super_admin.question_flags.index', [
            'reviews' => $reviews,
            'status'  => $status,
            'counts'  => [
                'open'    => QualityReview::where('status', 'open')->count(),
                'decided' => QualityReview::where('status', 'decided')->count(),
            ],
        ]);
    }

    /**
     * Outcome "Correct": our digital representation already matches the official
     * Cambridge source — restore the question to the active pool, close the review
     * and its reports, and notify the reporting teacher(s). No version, no
     * propagation. (Cosmetic/Material are decided from the editor on save.)
     */
    public function markCorrect(Request $request, QualityReview $review, NotificationService $notifications)
    {
        abort_if($review->status === 'decided', 409);

        DB::transaction(function () use ($review) {
            $adminId = auth('v2_super_admin')->id();

            $review->update([
                'outcome'     => 'correct',
                'status'      => 'decided',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            if ($review->question && $review->question->status === 'under_review') {
                $review->question->update(['status' => 'active']);
            }

            QuestionFlag::where('quality_review_id', $review->id)
                ->whereIn('status', ['open', 'escalated'])
                ->update(['status' => 'resolved', 'resolved_by' => $adminId, 'resolved_at' => now()]);
        });

        $notifications->notifyReviewDecision($review->fresh(['question.subject', 'reports']), 'correct');
        AuditLogger::record('quality_review.correct', $review->question, ['review_id' => $review->id]);

        return back()->with('success', 'Marked correct — restored to the active pool and the reporting teacher was notified.');
    }
}
