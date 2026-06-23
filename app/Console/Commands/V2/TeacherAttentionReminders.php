<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Exam;
use App\Models\V2\Student;
use App\Services\V2\ExamService;
use App\Services\V2\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:teacher-attention-reminders
|--------------------------------------------------------------------------
| Daily generator for teacher notifications. Two parts:
|   1. Attention — for each student with enough recent tests, flag the weakest
|      topic per subject (below TARGET, with enough sample) to that SUBJECT'S
|      teacher (v2_classes.subject_id), preferring the primary. Existing flags are
|      refreshed and auto-resolved on improvement. Idempotent — safe to re-run.
|   2. Updates — operational alerts: exams starting today, results overdue.
|
| Run on the daily scheduler (see routes/console.php). No external API.
*/
class TeacherAttentionReminders extends Command
{
    protected $signature = 'v2:teacher-attention-reminders
        {--days=60 : Recent window (days) used to judge weak topics}
        {--min-tests=4 : Minimum submitted tests in window before a student is evaluated}
        {--min-questions=5 : Minimum questions on a topic before it can be flagged}';

    protected $description = 'Generate teacher notifications: student attention flags + operational updates (no API)';

    public function handle(ExamService $exams, NotificationService $notifications): int
    {
        $since = now()->subDays((int) $this->option('days'));
        $minTests = (int) $this->option('min-tests');
        $minQuestions = (int) $this->option('min-questions');

        $this->info("Attention pass (window since {$since->toDateString()}, min {$minTests} tests)…");
        [$flagged, $resolved] = $this->attentionPass($exams, $notifications, $since, $minTests, $minQuestions);

        $this->info('Updates pass…');
        $updates = $this->updatesPass($notifications);

        $this->newLine();
        $this->table(
            ['Attention flags written', 'Auto-resolved', 'Operational updates'],
            [[$flagged, $resolved, $updates]]
        );

        return self::SUCCESS;
    }

    /** @return array{0:int,1:int} [flagsWritten, resolved] */
    private function attentionPass(ExamService $exams, NotificationService $notifications, $since, int $minTests, int $minQuestions): array
    {
        // Students with enough recent submitted tests to judge.
        $studentIds = DB::table('v2_exam_attempts')
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $since)
            ->groupBy('student_id')
            ->havingRaw('count(*) >= ?', [$minTests])
            ->pluck('student_id');

        $flagged = 0;
        $resolved = 0;

        foreach (Student::whereIn('id', $studentIds)->get() as $student) {
            $stats = $exams->studentStats($student, $since);
            $testsInWindow = $stats['overall']['tests'] ?? 0;

            // One flag per (student, subject) listing ALL its weak topics. The subject's
            // teacher(s) get it; when no topic is weak anymore, the flag resolves.
            foreach ($stats['subjects'] as $s) {
                $subjectId = (int) $s['subject_id'];
                $recipients = $notifications->teachersForStudentSubject($student, $subjectId);
                if ($recipients->isEmpty()) {
                    continue;
                }

                // Every flag-worthy weak topic in this subject, worst-first, capped.
                $weak = collect($s['topics'])
                    ->filter(fn ($t) => (int) $t['total'] >= $minQuestions && (int) $t['percent'] < NotificationService::TARGET)
                    ->sortBy('percent')
                    ->take(5)
                    ->values()
                    ->all();

                foreach ($recipients as $r) {
                    if (! empty($weak)) {
                        if ($notifications->flagAttentionSubject($r->teacher_id, $r->school_id, $student, $subjectId, $s['subject'], $weak, $testsInWindow)) {
                            $flagged++;
                        }
                    } elseif ($notifications->resolveSubjectFlag($r->teacher_id, $student, $subjectId)) {
                        // No weak topic left → the student cleared this subject.
                        $resolved++;
                    }
                }
            }
        }

        return [$flagged, $resolved];
    }

    private function updatesPass(NotificationService $notifications): int
    {
        $count = 0;
        $today = now()->toDateString();

        // 1. Exams scheduled for a future date → announce once ("created for <date>").
        $scheduled = Exam::withoutGlobalScopes()
            ->where('status', 'released')
            ->whereNotNull('available_from')
            ->where('available_from', '>', now())
            ->get();

        foreach ($scheduled as $exam) {
            $when = $exam->available_from?->format('j M Y, H:i');
            foreach ($this->teachersForClass($exam->class_id) as $t) {
                $notifications->pushUpdate(
                    $t->teacher_id,
                    $t->school_id,
                    'exam_scheduled',
                    "update:exam_scheduled:{$exam->id}",
                    [
                        'title' => $exam->title,
                        'body'  => "Scheduled for {$when}",
                        'url'   => null,
                    ]
                );
                $count++;
            }
        }

        // 2. Exams whose window opens today → tell the class's teachers.
        $startingToday = Exam::withoutGlobalScopes()
            ->where('status', 'released')
            ->whereDate('available_from', $today)
            ->get();

        foreach ($startingToday as $exam) {
            $time = $exam->available_from?->format('H:i');
            foreach ($this->teachersForClass($exam->class_id) as $t) {
                $notifications->pushUpdate(
                    $t->teacher_id,
                    $t->school_id,
                    'exam_today',
                    "update:exam_today:{$exam->id}:{$today}",
                    [
                        'title' => $exam->title,
                        'body'  => "Exam window opens today".($time ? " at {$time}" : ''),
                        'url'   => null,
                    ]
                );
                $count++;
            }
        }

        // 2. Exams whose window has closed but results are not released → remind the creator.
        $resultsDue = Exam::withoutGlobalScopes()
            ->where('status', 'released')
            ->whereNotNull('created_by')
            ->whereNotNull('available_until')
            ->where('available_until', '<', now())
            ->whereNull('results_released_at')
            ->get();

        foreach ($resultsDue as $exam) {
            $notifications->pushUpdate(
                $exam->created_by,
                $exam->school_id,
                'results_due',
                "update:results_due:{$exam->id}",
                [
                    'title' => $exam->title,
                    'body'  => 'Results are ready to release to students',
                    'url'   => null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /** Class → its teachers, as {teacher_id, school_id} rows. */
    private function teachersForClass(?int $classId)
    {
        if (! $classId) {
            return collect();
        }

        return DB::table('v2_class_teachers')
            ->where('class_id', $classId)
            ->select('teacher_id', 'school_id')
            ->get();
    }
}
