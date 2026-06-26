<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Services\V2\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:student-exam-reminders
|--------------------------------------------------------------------------
| Daily generator for student notifications (the time-based ones the release
| hook can't fire on its own):
|   1. Opens today  - a scheduled exam whose window opens today is now live.
|   2. Due today    - a live exam whose window closes today, still unattempted.
|   3. Missed       - a released exam whose window has closed, never attempted.
| New/scheduled-exam and results-released alerts are pushed in real time from the
| teacher release actions; this fills in the date-driven reminders. Idempotent.
*/
class StudentExamReminders extends Command
{
    protected $signature = 'v2:student-exam-reminders';

    protected $description = 'Generate student notifications: exams opening/closing today + missed exams (no API)';

    public function handle(NotificationService $notifications): int
    {
        $today = now()->toDateString();
        $opens = $due = $missed = 0;

        // 1. Scheduled exams whose window opens today → "now open".
        $opening = Exam::withoutGlobalScopes()
            ->where('status', 'released')
            ->whereNotNull('available_from')
            ->whereDate('available_from', $today)
            ->get();

        foreach ($opening as $exam) {
            $opens += $notifications->notifyExamToStudents(
                $exam, 'exam_released', "exam_open:{$exam->id}:{$today}",
                'Your test is now open - take it before it closes',
                $this->unattempted($exam), route('v2.student.exams.index'),
            );
        }

        // 2. Live exams whose window closes today → "due today" (unattempted only).
        $dueToday = Exam::withoutGlobalScopes()
            ->where('status', 'released')
            ->whereNotNull('available_until')
            ->whereDate('available_until', $today)
            ->where('available_until', '>=', now())
            ->get();

        foreach ($dueToday as $exam) {
            $time = $exam->available_until?->format('g:i A');
            $due += $notifications->notifyExamToStudents(
                $exam, 'exam_due_today', "exam_due:{$exam->id}:{$today}",
                'Due today'.($time ? " - closes at {$time}" : ''),
                $this->unattempted($exam), route('v2.student.exams.index'),
            );
        }

        // 3. Released exams past their window with no attempt → "missed".
        $expired = Exam::withoutGlobalScopes()
            ->where('status', 'released')
            ->whereNotNull('available_until')
            ->where('available_until', '<', now())
            ->get();

        foreach ($expired as $exam) {
            $missed += $notifications->notifyExamToStudents(
                $exam, 'exam_missed', "exam_missed:{$exam->id}",
                'You missed this test - the window has closed',
                $this->unattempted($exam), null,
            );
        }

        $this->table(
            ['Opens-today', 'Due-today', 'Missed'],
            [[$opens, $due, $missed]],
        );

        return self::SUCCESS;
    }

    /** Student ids enrolled in the exam's class who have NOT submitted an attempt. */
    private function unattempted(Exam $exam): array
    {
        $submitted = ExamAttempt::withoutGlobalScopes()
            ->where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->pluck('student_id')
            ->all();

        return DB::table('v2_student_enrollments')
            ->where('class_id', $exam->class_id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->reject(fn ($id) => in_array($id, $submitted, true))
            ->values()
            ->all();
    }
}
