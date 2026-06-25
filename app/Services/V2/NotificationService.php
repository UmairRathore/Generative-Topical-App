<?php

namespace App\Services\V2;

use App\Models\V2\Exam;
use App\Models\V2\Notification;
use App\Models\V2\QuestionFlag;
use App\Models\V2\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Notification write-side
|--------------------------------------------------------------------------
| Helpers used by the daily generator command to create/refresh notifications.
| Reads are done directly off the Notification model in the Livewire components.
*/
class NotificationService
{
    /** A weak topic is "resolved" once the student reaches this accuracy. */
    public const TARGET = 50;

    private const TEACHER = \App\Models\V2\Teacher::class;

    private const STUDENT = Student::class;

    /**
     * Teachers who should hear about a student's weakness in ONE subject: the
     * teacher(s) of the student's class FOR THAT SUBJECT (v2_classes.subject_id),
     * preferring the primary teacher and falling back to all when none is primary.
     * Subject-scoped so e.g. the chemistry teacher never gets physics flags.
     * Returns rows of {teacher_id, school_id}.
     */
    public function teachersForStudentSubject(Student $student, int $subjectId): Collection
    {
        $rows = DB::table('v2_class_teachers as ct')
            ->join('v2_student_enrollments as se', 'se.class_id', '=', 'ct.class_id')
            ->join('v2_classes as c', 'c.id', '=', 'ct.class_id')
            ->where('se.student_id', $student->id)
            ->where('c.subject_id', $subjectId)
            ->select('ct.teacher_id', 'ct.school_id', 'ct.is_primary')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        // Prefer primary teachers; only fall back to all when none is primary.
        $primary = $rows->where('is_primary', true);
        $chosen = $primary->isNotEmpty() ? $primary : $rows;

        return $chosen->unique('teacher_id')->values();
    }

    /** Stable dedupe key for one student / subject attention case (covers all its weak topics). */
    public function attentionKey(int $studentId, int $subjectId): string
    {
        return "attention:{$studentId}:{$subjectId}";
    }

    /**
     * Create or refresh ONE attention flag per (student, subject) listing every
     * currently-weak topic and its weak subtopics. Per-topic "flagged" baselines
     * are preserved across runs; read_at/resolved_at are never touched here (no
     * re-nagging, never re-opens a handled flag). Returns true only on first create.
     *
     * @param  array  $weakTopics  pre-filtered, worst-first, each ['topic','percent','subtopics'=>[...]]
     */
    public function flagAttentionSubject(
        int $teacherId,
        int $schoolId,
        Student $student,
        int $subjectId,
        string $subjectName,
        array $weakTopics,
        int $testsInWindow
    ): bool {
        $key = $this->attentionKey($student->id, $subjectId);

        $existing = Notification::where('notifiable_type', self::TEACHER)
            ->where('notifiable_id', $teacherId)
            ->where('dedupe_key', $key)
            ->first();

        // A teacher already handled this subject — leave it resolved, don't re-open.
        if ($existing && $existing->resolved_at) {
            return false;
        }

        $topics = array_map(fn ($t) => [
            'topic'     => $t['topic'],
            'current'   => (int) $t['percent'],
            'subtopics' => collect($t['subtopics'] ?? [])
                ->filter(fn ($s) => ($s['total'] ?? 0) > 0)
                ->sortBy('percent')->take(3)
                ->map(fn ($s) => ['subtopic' => $s['subtopic'], 'percent' => (int) $s['percent']])
                ->values()->all(),
        ], $weakTopics);

        if ($existing) {
            // Preserve each topic's original "flagged" baseline; refresh current.
            $priorFlagged = collect($existing->data['topics'] ?? [])
                ->mapWithKeys(fn ($p) => [$p['topic'] => $p['flagged'] ?? $p['current'] ?? 0]);
            foreach ($topics as &$t) {
                $t['flagged'] = (int) ($priorFlagged[$t['topic']] ?? $t['current']);
            }
            unset($t);

            $data = $existing->data;
            $data['topics'] = $topics;
            $data['current_avg'] = $this->avgCurrent($topics);
            $existing->update(['data' => $data]);

            return false;
        }

        foreach ($topics as &$t) {
            $t['flagged'] = $t['current'];
        }
        unset($t);

        Notification::create([
            'school_id'       => $schoolId,
            'notifiable_type' => self::TEACHER,
            'notifiable_id'   => $teacherId,
            'category'        => 'attention',
            'type'            => 'student_attention',
            'student_id'      => $student->id,
            'subject_id'      => $subjectId,
            'dedupe_key'      => $key,
            'data'            => [
                'title'         => "{$student->name} needs attention",
                'subject'       => $subjectName,
                'topics'        => $topics,
                'flagged_avg'   => $this->avgCurrent($topics),
                'current_avg'   => $this->avgCurrent($topics),
                'target'        => self::TARGET,
                'tests_at_flag' => $testsInWindow,
            ],
        ]);

        return true;
    }

    /**
     * Resolve a (student, subject) flag when the subject no longer has any weak
     * topic — i.e. the student is now ≥ TARGET on all tested topics. Returns true
     * if a flag was resolved.
     */
    public function resolveSubjectFlag(int $teacherId, Student $student, int $subjectId): bool
    {
        $flag = Notification::where('notifiable_type', self::TEACHER)
            ->where('notifiable_id', $teacherId)
            ->where('dedupe_key', $this->attentionKey($student->id, $subjectId))
            ->whereNull('resolved_at')
            ->first();

        if (! $flag) {
            return false;
        }

        $flag->update(['resolved_at' => now()]);

        return true;
    }

    private function avgCurrent(array $topics): int
    {
        if (empty($topics)) {
            return 0;
        }

        return (int) round(collect($topics)->avg('current'));
    }

    /**
     * Create or refresh an operational "update" notification (idempotent per the
     * caller-supplied dedupe_key, e.g. one per exam per day).
     */
    public function pushUpdate(int $teacherId, int $schoolId, string $type, string $dedupeKey, array $data): void
    {
        $existing = Notification::where('notifiable_type', self::TEACHER)
            ->where('notifiable_id', $teacherId)
            ->where('dedupe_key', $dedupeKey)
            ->first();

        if ($existing) {
            $existing->update(['data' => $data]);

            return;
        }

        Notification::create([
            'school_id'       => $schoolId,
            'notifiable_type' => self::TEACHER,
            'notifiable_id'   => $teacherId,
            'category'        => 'update',
            'type'            => $type,
            'dedupe_key'      => $dedupeKey,
            'data'            => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Student notifications (all "update" category — exam lifecycle alerts)
    |--------------------------------------------------------------------------
    | Students get operational updates only: a new/scheduled exam, an exam due
    | today, results released, or a missed exam. Same idempotent dedupe model as
    | the teacher updates above, addressed to the Student notifiable.
    */

    /** Active-enrolled students of a class, as {student_id, school_id} rows. */
    public function studentsForClass(int $classId): Collection
    {
        return DB::table('v2_student_enrollments')
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->select('student_id', 'school_id')
            ->get();
    }

    /** Create or refresh one student "update" notification (idempotent per dedupe key). */
    public function notifyStudent(int $studentId, int $schoolId, string $type, string $dedupeKey, array $data, ?int $subjectId = null): void
    {
        $existing = Notification::where('notifiable_type', self::STUDENT)
            ->where('notifiable_id', $studentId)
            ->where('dedupe_key', $dedupeKey)
            ->first();

        if ($existing) {
            $existing->update(['data' => $data]);

            return;
        }

        Notification::create([
            'school_id'       => $schoolId,
            'notifiable_type' => self::STUDENT,
            'notifiable_id'   => $studentId,
            'category'        => 'update',
            'type'            => $type,
            'subject_id'      => $subjectId,
            'dedupe_key'      => $dedupeKey,
            'data'            => $data,
        ]);
    }

    /**
     * Fan one exam update out to its enrolled students (optionally only a subset,
     * e.g. those who haven't submitted). Returns how many students were notified.
     *
     * @param  array<int>|null  $onlyStudentIds
     */
    public function notifyExamToStudents(Exam $exam, string $type, string $dedupeKey, string $body, ?array $onlyStudentIds = null, ?string $url = null): int
    {
        $exam->loadMissing(['creator', 'subject']);

        $rows = $this->studentsForClass($exam->class_id);
        if ($onlyStudentIds !== null) {
            $set = array_map('intval', $onlyStudentIds);
            $rows = $rows->whereIn('student_id', $set);
        }

        $data = [
            'title'   => $exam->title,
            'body'    => $body,
            'teacher' => $exam->creator?->name,
            'subject' => $exam->subject?->name,
            'exam'    => $exam->title,
            'url'     => $url,
        ];

        $n = 0;
        foreach ($rows as $row) {
            $this->notifyStudent($row->student_id, $row->school_id, $type, $dedupeKey, $data, $exam->subject_id);
            $n++;
        }

        return $n;
    }

    /** Real-time: a teacher just released (now) or scheduled (future) an exam. */
    public function announceExamRelease(Exam $exam): void
    {
        $url = route('v2.student.exams.index');

        if ($exam->isScheduled()) {
            $when = $exam->available_from?->format('j M Y, g:i A');
            $this->notifyExamToStudents($exam, 'exam_scheduled', "exam_scheduled:{$exam->id}", "Scheduled to open {$when}", null, $url);
        } else {
            $due = $exam->available_until?->format('j M Y, g:i A');
            $this->notifyExamToStudents($exam, 'exam_released', "exam_released:{$exam->id}", 'A new test is available now'.($due ? " · due {$due}" : ''), null, $url);
        }
    }

    /** Real-time: a teacher released results — students can now see their score. */
    public function announceResults(Exam $exam): void
    {
        $this->notifyExamToStudents(
            $exam,
            'results_released',
            "results_released:{$exam->id}",
            'Results have been released — view your score',
            null,
            route('v2.student.exams.result', hid($exam->id)),
        );
    }

    /** A void changed students' visible marksheet — tell each affected student. */
    public function announceScoreAdjusted(Exam $exam, array $studentIds): void
    {
        $this->notifyExamToStudents(
            $exam,
            'results_released',
            "score_adjusted:{$exam->id}",
            'A question was removed after review — your result has been updated',
            array_values(array_unique(array_map('intval', $studentIds))),
            route('v2.student.exams.result', hid($exam->id)),
        );
    }

    /**
     * A student flagged a question on an exam — tell that exam's teacher(s). One
     * notification per (exam, question): it names the latest reporter (name + roll)
     * and reason, and carries the running count so repeat reports bump, not spam.
     */
    public function notifyTeachersOfStudentFlag(Exam $exam, int $questionId, Student $student, string $reason, ?string $note = null): void
    {
        $exam->loadMissing('subject');

        $pos = DB::table('v2_exam_questions')
            ->where('exam_id', $exam->id)->where('question_id', $questionId)
            ->value('sort_order');

        $count = (int) QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)->where('question_id', $questionId)
            ->where('status', 'open')
            ->distinct()->count('flagged_by_student_id');

        $label       = $pos ? "Q{$pos}" : 'a question';
        $reasonLabel = QuestionFlag::REASONS[$reason] ?? ucfirst(str_replace('_', ' ', $reason));
        $who         = $student->name.($student->roll_number ? " (Roll {$student->roll_number})" : '');
        $others      = $count > 1 ? ' · +'.($count - 1).' other '.\Illuminate\Support\Str::plural('report', $count - 1) : '';
        $noteSnippet = $note ? ' — “'.\Illuminate\Support\Str::limit($note, 80).'”' : '';

        $data = [
            'title'    => "Question reported in “{$exam->title}”",
            'body'     => "{$who} reported {$label}: {$reasonLabel}{$others}{$noteSnippet}",
            'student'  => $student->name,
            'roll'     => $student->roll_number,
            'reason'   => $reasonLabel,
            'question' => $pos,
            'subject'  => $exam->subject?->name,
            'exam'     => $exam->title,
            // Deep-link straight to the flagged question's row in the manage page.
            'url'      => route('v2.teacher.exams.show', hid($exam->id)).($pos ? "#flag-q{$pos}" : ''),
        ];

        foreach (DB::table('v2_class_teachers')->where('class_id', $exam->class_id)->select('teacher_id', 'school_id')->get() as $t) {
            $this->pushUpdate($t->teacher_id, $t->school_id, 'question_flag', "student_flag:{$exam->id}:{$questionId}", $data);
        }
    }
}
