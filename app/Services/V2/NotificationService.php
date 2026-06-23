<?php

namespace App\Services\V2;

use App\Models\V2\Notification;
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
}
