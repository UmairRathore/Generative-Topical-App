<?php

namespace App\Services\V2;

use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Models\V2\ExamQuestion;
use App\Models\V2\QualityPropagation;
use App\Models\V2\QualityPropagationAttempt;
use App\Models\V2\QualityPropagationPivot;
use App\Models\V2\QualityReview;
use App\Models\V2\QuestionVersion;
use App\Models\V2\SchoolClass;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 3 — material-error global propagation
|--------------------------------------------------------------------------
| preview(): read-only blast radius for a set of faulty version ids (no writes).
| run(): the idempotent, resumable engine the queued job calls — voids only the
| pivots that used a confirmed faulty version (never the corrected/future one),
| recomputes each affected exam via the existing ExamService engine, records a
| full per-pivot + per-attempt audit, and reuses the existing student
| score-adjusted notification for released exams whose visible score moved.
*/
class PropagationService
{
    public function __construct(
        private ExamService $exams,
        private NotificationService $notifications,
    ) {}

    /**
     * Read-only blast-radius preview for a candidate faulty-version set. Targets
     * pivots by question_version_id only; the corrected/future version is never
     * included by the caller. Returns the per-version table + aggregates + a sample
     * of before/after rows. Nothing is mutated.
     *
     * @param  array<int>  $versionIds
     */
    public function preview(QualityReview $review, array $versionIds): array
    {
        $questionId = $review->question_id;
        $corrected  = $review->resulting_version_id ? QuestionVersion::find($review->resulting_version_id) : null;
        $selected   = array_values(array_unique(array_map('intval', $versionIds)));

        $versions = $this->versionTable($questionId, $corrected, $selected);

        // Pivots that used a SELECTED faulty version.
        $pivots = ExamQuestion::withoutGlobalScopes()
            ->where('question_id', $questionId)
            ->whereIn('question_version_id', $selected ?: [-1])
            ->get();

        $wouldVoid      = $pivots->where('is_voided', false);
        $alreadyTeacher = $pivots->where('is_voided', true)->where('void_source', '!=', 'quality_review');
        $alreadyQuality = $pivots->where('is_voided', true)->where('void_source', 'quality_review');

        $affectedExamIds = $wouldVoid->pluck('exam_id')->unique()->values();
        $exams = Exam::withoutGlobalScopes()->whereIn('id', $affectedExamIds)->get();

        $branchByClass = SchoolClass::withoutGlobalScopes()
            ->whereIn('id', $exams->pluck('class_id')->unique()->filter()->values())
            ->pluck('branch_id', 'id');

        $attemptsAffected = 0;
        $expectedNotifications = 0;
        $sample = [];

        foreach ($exams as $exam) {
            $rows = $this->exams->simulateRecompute($exam, [$questionId]);
            $attemptsAffected += count($rows);
            foreach ($rows as $r) {
                if ($r['released'] && $r['changed']) {
                    $expectedNotifications++;
                }
                if ($r['changed'] && count($sample) < 25) {
                    $sample[] = $r + ['exam_id' => $exam->id, 'exam_title' => $exam->title];
                }
            }
        }

        return [
            'corrected'        => $corrected,
            'corrected_number' => $corrected?->version_number,
            'versions'         => $versions,
            'selected'         => $selected,
            'sample'           => $sample,
            'counts'           => [
                'schools'                => $exams->pluck('school_id')->filter()->unique()->count(),
                'branches'               => $exams->pluck('class_id')->map(fn ($c) => $branchByClass[$c] ?? null)->filter()->unique()->count(),
                'teachers'               => $exams->pluck('created_by')->filter()->unique()->count(),
                'exams'                  => $affectedExamIds->count(),
                'attempts'               => $attemptsAffected,
                'would_void'             => $wouldVoid->count(),
                'already_teacher'        => $alreadyTeacher->count(),
                'already_quality'        => $alreadyQuality->count(),
                'expected_notifications' => $expectedNotifications,
            ],
        ];
    }

    /** Per-version rows for the selection UI (usage counts + selectability). */
    private function versionTable(int $questionId, ?QuestionVersion $corrected, array $selected): \Illuminate\Support\Collection
    {
        $correctedNo = $corrected?->version_number;

        $examsByVersion = ExamQuestion::withoutGlobalScopes()
            ->where('question_id', $questionId)
            ->selectRaw('question_version_id, count(*) as c')->groupBy('question_version_id')
            ->pluck('c', 'question_version_id');

        $attemptsByVersion = DB::table('v2_exam_questions as eq')
            ->join('v2_exam_attempts as at', 'at.exam_id', '=', 'eq.exam_id')
            ->where('eq.question_id', $questionId)->where('at.status', 'submitted')
            ->selectRaw('eq.question_version_id as v, count(*) as c')->groupBy('eq.question_version_id')
            ->pluck('c', 'v');

        return QuestionVersion::where('question_id', $questionId)->orderBy('version_number')->get()
            ->map(fn ($v) => [
                'id'                => $v->id,
                'version_number'    => $v->version_number,
                'created_at'        => $v->created_at,
                'change_summary'    => $v->change_summary,
                'quality_review_id' => $v->quality_review_id,
                'is_corrected'      => $corrected && $v->id === $corrected->id,
                'selectable'        => $correctedNo === null || $v->version_number < $correctedNo,
                'selected'          => in_array($v->id, $selected, true),
                'exams_count'       => (int) ($examsByVersion[$v->id] ?? 0),
                'attempts_count'    => (int) ($attemptsByVersion[$v->id] ?? 0),
            ]);
    }

    /**
     * Execute a propagation run. Idempotent + resumable: the faulty set is read
     * ONLY from $prop->version_ids; each exam is processed in its own transaction,
     * and a pivot already recorded for this run is skipped — so a completed exam
     * (pivots voided + recomputed + audited atomically) is never reprocessed.
     */
    public function run(QualityPropagation $prop): void
    {
        if ($prop->status === 'completed') {
            return;
        }

        $prop->update(['status' => 'running', 'started_at' => $prop->started_at ?? now(), 'error' => null]);

        try {
            $versionIds = array_map('intval', $prop->version_ids ?? []);

            $byExam = ExamQuestion::withoutGlobalScopes()
                ->where('question_id', $prop->question_id)
                ->whereIn('question_version_id', $versionIds ?: [-1])
                ->get()
                ->groupBy('exam_id');

            foreach ($byExam->keys()->chunk(50) as $chunk) {
                $exams = Exam::withoutGlobalScopes()->whereIn('id', $chunk)->with('schoolClass:id,branch_id')->get()->keyBy('id');
                foreach ($chunk as $examId) {
                    if ($exam = $exams->get($examId)) {
                        $this->runExam($prop, $exam, $byExam[$examId]);
                    }
                }
            }

            $prop->refresh();
            $prop->update(['status' => 'completed', 'completed_at' => now()]);
            $prop->review->update(['propagation_status' => 'propagated']);

            AuditLogger::record('quality_review.propagated', $prop->review->question, [
                'review_id'      => $prop->quality_review_id,
                'propagation_id' => $prop->id,
                'exams'          => $byExam->count(),
            ]);
        } catch (\Throwable $e) {
            $prop->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }

    /** One exam, atomically: void its faulty-version pivots, recompute, audit, notify. */
    private function runExam(QualityPropagation $prop, Exam $exam, \Illuminate\Support\Collection $faultyPivots): void
    {
        DB::transaction(function () use ($prop, $exam, $faultyPivots) {
            $newlyVoided = [];

            foreach ($faultyPivots as $eq) {
                // Per-exam tx is atomic, so a recorded pivot means this exam is fully done.
                if (QualityPropagationPivot::where('propagation_id', $prop->id)->where('exam_question_id', $eq->id)->exists()) {
                    continue;
                }

                if ($eq->is_voided) {
                    $action = $eq->void_source === 'quality_review' ? 'already_voided_quality' : 'already_voided_teacher_local';
                } else {
                    $eq->update([
                        'is_voided'              => true,
                        'void_reason'            => 'material_error',
                        'voided_by'              => null,
                        'voided_at'              => now(),
                        'void_source'            => 'quality_review',
                        'void_quality_review_id' => $prop->quality_review_id,
                    ]);
                    $newlyVoided[] = (int) $eq->question_id;
                    $action = 'voided';
                }

                QualityPropagationPivot::insertOrIgnore([
                    'propagation_id'      => $prop->id,
                    'exam_question_id'    => $eq->id,
                    'exam_id'             => $exam->id,
                    'school_id'           => $exam->school_id,
                    'branch_id'           => $exam->schoolClass?->branch_id,
                    'question_version_id' => $eq->question_version_id,
                    'action'              => $action,
                    'processed_at'        => now(),
                ]);
            }

            // Nothing newly excluded → marks unchanged, no recompute/notify.
            if (empty($newlyVoided)) {
                return;
            }

            $before = ExamAttempt::withoutGlobalScopes()
                ->where('exam_id', $exam->id)->where('status', 'submitted')
                ->get(['id', 'student_id', 'score', 'total_questions'])->keyBy('id');

            $changed   = $this->exams->recomputeExamScores($exam);
            $released  = $exam->resultsReleased();
            $changedSet = array_flip(array_map('intval', $changed));

            $after = ExamAttempt::withoutGlobalScopes()
                ->where('exam_id', $exam->id)->where('status', 'submitted')
                ->get(['id', 'student_id', 'score', 'total_questions']);

            $rows = $after->map(fn ($a) => [
                'propagation_id' => $prop->id,
                'exam_id'        => $exam->id,
                'attempt_id'     => $a->id,
                'student_id'     => $a->student_id,
                'score_before'   => $before[$a->id]->score ?? null,
                'total_before'   => $before[$a->id]->total_questions ?? null,
                'score_after'    => (int) $a->score,
                'total_after'    => (int) $a->total_questions,
                'released'       => $released,
                'notified'       => $released && isset($changedSet[(int) $a->student_id]) ? 1 : 0,
            ])->all();

            if ($rows) {
                QualityPropagationAttempt::insertOrIgnore($rows);
            }

            if ($released && $changed) {
                $this->notifications->announceScoreAdjusted($exam, $changed);
                $prop->increment('notifications_sent', count(array_unique($changed)));
            }
        });
    }
}
