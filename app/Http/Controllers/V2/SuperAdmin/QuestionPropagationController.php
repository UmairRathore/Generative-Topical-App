<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\V2\PropagateMaterialCorrection;
use App\Models\V2\QualityPropagation;
use App\Models\V2\QualityReview;
use App\Models\V2\QuestionVersion;
use App\Services\V2\PropagationService;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Super Admin: material-error propagation (Phase 3)
|--------------------------------------------------------------------------
| A decided material review with propagation_status = propagation_pending can be
| propagated across history. preview() shows the version-targeted blast radius;
| confirm() captures the chosen faulty version_ids on a v2_quality_propagations
| row (the job's single source of truth) and dispatches the idempotent job.
| The corrected/future version can never be selected.
*/
class QuestionPropagationController extends Controller
{
    public function preview(Request $request, QualityReview $review, PropagationService $service)
    {
        $this->guard($review);

        $corrected = $review->resulting_version_id ? QuestionVersion::find($review->resulting_version_id) : null;
        $selected  = $request->has('versions')
            ? $this->selectable((array) $request->input('versions'), $review, $corrected)
            : $this->defaultFaulty($review, $corrected);

        return view('v2.super_admin.question_propagation.preview', [
            'review'  => $review->load('question.subject'),
            'preview' => $service->preview($review, $selected),
        ]);
    }

    public function confirm(Request $request, QualityReview $review, PropagationService $service)
    {
        $this->guard($review);

        $corrected = $review->resulting_version_id ? QuestionVersion::find($review->resulting_version_id) : null;
        $selected  = $this->selectable((array) $request->input('versions', []), $review, $corrected);
        abort_if(empty($selected), 422, 'Select at least one faulty version to propagate.');

        $counts = $service->preview($review, $selected)['counts'];

        // One run per review (unique idempotency_key). Re-confirm only resumes a
        // previously failed run; a pending/running/completed run is left alone.
        $prop = QualityPropagation::firstOrNew(['idempotency_key' => 'qr:'.$review->id]);
        if ($prop->exists && in_array($prop->status, ['pending', 'running', 'completed'], true)) {
            return redirect()->route('v2.super_admin.question_flags.index', ['status' => 'decided'])
                ->with('success', "Propagation already {$prop->status}.");
        }

        $prop->fill([
            'quality_review_id'      => $review->id,
            'question_id'            => $review->question_id,
            'version_ids'            => $selected,
            'confirmed_by'           => auth('v2_super_admin')->id(),
            'confirmed_at'           => now(),
            'status'                 => 'pending',
            'error'                  => null,
            'schools_count'          => $counts['schools'],
            'branches_count'         => $counts['branches'],
            'teachers_count'         => $counts['teachers'],
            'exams_count'            => $counts['exams'],
            'attempts_count'         => $counts['attempts'],
            'notifications_expected' => $counts['expected_notifications'],
        ])->save();

        PropagateMaterialCorrection::dispatch($prop->id);

        return redirect()->route('v2.super_admin.question_flags.index', ['status' => 'decided'])
            ->with('success', "Propagation queued — {$counts['would_void']} exam-question(s) across {$counts['exams']} exam(s) will be updated.");
    }

    private function guard(QualityReview $review): void
    {
        abort_unless($review->outcome === 'material' && $review->propagation_status === 'propagation_pending', 404);
    }

    /** All versions earlier than the corrected one (default faulty selection). */
    private function defaultFaulty(QualityReview $review, ?QuestionVersion $corrected): array
    {
        return QuestionVersion::where('question_id', $review->question_id)
            ->when($corrected, fn ($q) => $q->where('version_number', '<', $corrected->version_number))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    /**
     * Keep only ids that are real prior versions of THIS question — the corrected
     * version and anything at/after it are rejected (safeguard: the corrected/future
     * version can never be propagated).
     */
    private function selectable(array $ids, QualityReview $review, ?QuestionVersion $corrected): array
    {
        $valid = $this->defaultFaulty($review, $corrected);

        return array_values(array_intersect(array_map('intval', $ids), $valid));
    }
}
