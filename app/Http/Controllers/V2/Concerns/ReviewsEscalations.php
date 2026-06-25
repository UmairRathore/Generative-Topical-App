<?php

namespace App\Http\Controllers\V2\Concerns;

use App\Models\V2\ExamQuestion;
use App\Models\V2\QuestionFlag;
use App\Models\V2\Teacher;

/*
|--------------------------------------------------------------------------
| Read-only "Reported Questions" audit for Branch / School Admins
|--------------------------------------------------------------------------
| Admins are NOT in the approval chain (Student → Teacher → Support Team / Quality Review).
| This assembles every reported question for visibility/auditing: student reports
| on exams, teacher bank flags (no exam), the teacher's decision, current status
| and a timeline. Branch admins are scoped to their branch; school admins see all
| branches (with a Branch column).
*/
trait ReviewsEscalations
{
    /**
     * @param  ?int  $schoolId  scope to one school (school admin), or null
     * @param  ?int  $branchId  scope to one branch (branch admin), or null
     */
    protected function reportedQuestions(?int $schoolId, ?int $branchId)
    {
        return $this->examReports($schoolId, $branchId)
            ->merge($this->bankReports($schoolId, $branchId))
            ->sortByDesc('sortTime')->values();
    }

    /** Exam-linked reports (student-originated), grouped per (exam, question). */
    private function examReports(?int $schoolId, ?int $branchId)
    {
        $flags = QuestionFlag::whereNotNull('exam_id')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($branchId, fn ($q) => $q->whereHas('exam.schoolClass', fn ($w) => $w->where('branch_id', $branchId)))
            ->with([
                'student:id,name,roll_number',
                'exam:id,title,class_id,subject_id,created_by',
                'exam.subject:id,name',
                'exam.creator:id,name',
                'exam.schoolClass:id,name,branch_id',
                'exam.schoolClass.branch:id,name',
            ])
            ->orderBy('created_at')->get();

        $eqMap = ExamQuestion::whereIn('exam_id', $flags->pluck('exam_id')->unique()->all())
            ->get(['exam_id', 'question_id', 'sort_order', 'is_voided', 'void_reason', 'voided_at', 'voided_by'])
            ->keyBy(fn ($e) => $e->exam_id.':'.$e->question_id);
        $voiders = Teacher::whereIn('id', $eqMap->pluck('voided_by')->filter()->unique())->pluck('name', 'id');

        return $flags->groupBy(fn ($f) => $f->exam_id.':'.$f->question_id)->map(function ($group) use ($eqMap, $voiders) {
            $first       = $group->first();
            $exam        = $first->exam;
            $eq          = $eqMap->get($first->exam_id.':'.$first->question_id);
            $students    = $group->where('level', 'student');
            $teacherFlag = $group->firstWhere('level', 'teacher');
            $isVoided    = (bool) ($eq?->is_voided);

            // Workflow status = the report's lifecycle ONLY (Open / Escalated /
            // Resolved / Dismissed). Voiding is a separate per-exam academic action,
            // surfaced as metadata below — never as a workflow status.
            if ($teacherFlag) {
                [$status, $badge] = match ($teacherFlag->status) {
                    'open'     => ['Under Quality Review', 'badge-review'],
                    'resolved' => ['Resolved', 'badge-pass'],
                    default    => ['Dismissed', 'badge-soft'],
                };
            } elseif ($students->where('status', 'open')->isNotEmpty()) {
                [$status, $badge] = ['Open', 'badge-soft'];
            } elseif ($isVoided) {
                // Teacher resolved the report by voiding the question for this exam.
                [$status, $badge] = ['Resolved', 'badge-pass'];
            } else {
                [$status, $badge] = ['Dismissed', 'badge-soft'];
            }

            $decision = match (true) {
                (bool) $teacherFlag => 'Escalated to Support Team'.($teacherFlag->note ? ' — “'.$teacherFlag->note.'”' : ''),
                $isVoided           => 'Voided for this exam'.($eq?->void_reason ? ' ('.(QuestionFlag::REASONS[$eq->void_reason] ?? $eq->void_reason).')' : ''),
                $students->where('status', 'open')->isEmpty() => 'Reports dismissed — question kept',
                default             => 'Awaiting teacher review',
            };

            $timeline = collect();
            foreach ($students->sortBy('created_at') as $s) {
                $timeline->push(['t' => $s->created_at, 'label' => 'Reported by '.($s->student?->name ?? 'a student').' — '.$s->reasonLabel()]);
            }
            if ($isVoided && $eq?->voided_at) {
                $timeline->push(['t' => $eq->voided_at, 'label' => 'Voided for this exam by '.($voiders[$eq->voided_by] ?? 'a teacher')]);
            }
            if ($teacherFlag) {
                $timeline->push(['t' => $teacherFlag->created_at, 'label' => 'Escalated to Support Team']);
                if ($teacherFlag->resolved_at) {
                    $timeline->push(['t' => $teacherFlag->resolved_at, 'label' => ($teacherFlag->status === 'resolved' ? 'Fixed' : 'Closed').' by the Support Team']);
                }
            }

            return [
                'kind'     => 'exam',
                'exam'     => $exam?->title ?? 'Exam',
                'subject'  => $exam?->subject?->name,
                'qno'      => $eq?->sort_order,
                'source'   => 'Student report',
                'students' => $students->map(fn ($s) => trim(($s->student?->name ?? 'A student').($s->student?->roll_number ? ' (Roll '.$s->student->roll_number.')' : '')))->unique()->values()->all(),
                'count'    => $students->count(),
                'teacher'  => $exam?->creator?->name,
                'branch'   => $exam?->schoolClass?->branch?->name,
                'status'     => $status,
                'badge'      => $badge,
                'voided'     => $isVoided,
                'voidReason' => $isVoided ? (QuestionFlag::REASONS[$eq->void_reason] ?? $eq->void_reason) : null,
                'decision'   => $decision,
                'timeline'   => $timeline->sortBy('t')->values()->all(),
                'sortTime'   => optional($timeline->max('t'))->timestamp ?? $first->created_at?->timestamp,
            ];
        })->values();
    }

    /** Teacher flags raised from the question bank (no exam, no students). */
    private function bankReports(?int $schoolId, ?int $branchId)
    {
        return QuestionFlag::teacherLevel()->whereNull('exam_id')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($branchId, fn ($q) => $q->whereHas('teacher', fn ($w) => $w->where('branch_id', $branchId)))
            ->with(['teacher:id,name,branch_id', 'teacher.branch:id,name', 'question:id,subject_id,source_paper', 'question.subject:id,name'])
            ->orderBy('created_at')->get()
            ->map(function ($f) {
                [$status, $badge] = match ($f->status) {
                    'open'     => ['Under Quality Review', 'badge-review'],
                    'resolved' => ['Resolved', 'badge-pass'],
                    default    => ['Dismissed', 'badge-soft'],
                };

                $timeline = collect([['t' => $f->created_at, 'label' => 'Flagged by '.($f->teacher?->name ?? 'a teacher').' — '.$f->reasonLabel()]]);
                if ($f->resolved_at) {
                    $timeline->push(['t' => $f->resolved_at, 'label' => ($f->status === 'resolved' ? 'Fixed' : 'Closed').' by the Support Team']);
                }

                return [
                    'kind'     => 'bank',
                    'exam'     => 'Question bank'.($f->question?->source_paper ? ' · '.$f->question->source_paper : ''),
                    'subject'  => $f->question?->subject?->name,
                    'qno'      => null,
                    'source'   => 'Teacher (question bank)',
                    'students' => [],
                    'count'    => 0,
                    'teacher'  => $f->teacher?->name,
                    'branch'   => $f->teacher?->branch?->name,
                    'status'     => $status,
                    'badge'      => $badge,
                    'voided'     => false,
                    'voidReason' => null,
                    'decision'   => 'Flagged from the question bank'.($f->note ? ' — “'.$f->note.'”' : ''),
                    'timeline' => $timeline->sortBy('t')->values()->all(),
                    'sortTime' => ($f->resolved_at ?? $f->created_at)?->timestamp,
                ];
            })->values();
    }
}
