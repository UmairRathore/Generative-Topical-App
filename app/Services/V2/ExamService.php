<?php

namespace App\Services\V2;

use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Models\V2\Question;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Teacher;
use Illuminate\Support\Facades\DB;

class ExamService
{
    /**
     * Generate an exam: randomly pick questions from the bank scoped to the
     * class's subject and the chosen topic, then freeze them into the exam.
     *
     * Answerable questions (those with a known correct_answer) are preferred so
     * auto-marking works; if not enough are answerable yet, the rest are filled
     * from the topic so the exam is still complete.
     *
     * @param array{class_id:int,topic_id:?int,question_count:int,title:string,duration_minutes:?int,year_from:?int,year_to:?int} $data
     */
    public function generate(Teacher $teacher, array $data): Exam
    {
        /** @var SchoolClass $class */
        $class = SchoolClass::findOrFail($data['class_id']);
        $count = max(1, min(40, (int) $data['question_count']));

        $base = Question::query()
            ->where('subject_id', $class->subject_id)
            ->when($data['topic_id'] ?? null, fn ($q, $t) => $q->where('topic_id', $t))
            ->when($data['year_from'] ?? null, fn ($q, $y) => $q->where('year', '>=', $y))
            ->when($data['year_to'] ?? null, fn ($q, $y) => $q->where('year', '<=', $y));

        // Prefer answerable questions; fill the remainder if there aren't enough.
        $ids = (clone $base)->whereNotNull('correct_answer')
            ->inRandomOrder()->limit($count)->pluck('id')->all();

        if (count($ids) < $count) {
            $fill = (clone $base)->whereNull('correct_answer')
                ->whereNotIn('id', $ids)
                ->inRandomOrder()->limit($count - count($ids))->pluck('id')->all();
            $ids = array_merge($ids, $fill);
        }

        shuffle($ids);

        return DB::transaction(function () use ($teacher, $class, $data, $ids, $count) {
            $exam = Exam::create([
                'school_id'        => $teacher->school_id,
                'class_id'         => $class->id,
                'subject_id'       => $class->subject_id,
                'topic_id'         => $data['topic_id'] ?? null,
                'created_by'       => $teacher->id,
                'title'            => $data['title'],
                'question_count'   => count($ids),
                'total_marks'      => count($ids),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'year_from'        => $data['year_from'] ?? null,
                'year_to'          => $data['year_to'] ?? null,
                'status'           => 'published',
                'published_at'     => now(),
            ]);

            $rows = [];
            foreach ($ids as $i => $qid) {
                $rows[] = [
                    'exam_id'     => $exam->id,
                    'question_id' => $qid,
                    'sort_order'  => $i + 1,
                    'marks'       => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
            if ($rows) {
                DB::table('v2_exam_questions')->insert($rows);
            }

            return $exam;
        });
    }

    /** Get (or open) a student's attempt for an exam. */
    public function startAttempt(Exam $exam, Student $student): ExamAttempt
    {
        return ExamAttempt::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            [
                'school_id'       => $student->school_id,
                'status'          => 'in_progress',
                'total_questions' => $exam->question_count,
                'started_at'      => now(),
            ]
        );
    }

    /**
     * Auto-mark a submitted attempt. $responses maps question_id => 'A'|'B'|'C'|'D'.
     */
    public function submit(ExamAttempt $attempt, array $responses): ExamAttempt
    {
        $exam = $attempt->exam()->with('examQuestions.question:id,correct_answer')->firstOrFail();

        return DB::transaction(function () use ($attempt, $exam, $responses) {
            $score = 0;

            foreach ($exam->examQuestions as $eq) {
                $correct  = $eq->question?->correct_answer;
                $selected = $responses[$eq->question_id] ?? null;
                $selected = $selected ? strtoupper(substr($selected, 0, 1)) : null;
                $isCorrect = $selected !== null && $correct !== null && $selected === $correct;
                if ($isCorrect) {
                    $score++;
                }

                DB::table('v2_exam_answers')->updateOrInsert(
                    ['attempt_id' => $attempt->id, 'question_id' => $eq->question_id],
                    [
                        'selected_option' => $selected,
                        'correct_option'  => $correct,
                        'is_correct'      => $isCorrect,
                        'updated_at'      => now(),
                        'created_at'      => now(),
                    ]
                );
            }

            $attempt->update([
                'status'       => 'submitted',
                'score'        => $score,
                'submitted_at' => now(),
            ]);

            return $attempt->fresh();
        });
    }

    /** Per-topic breakdown for one attempt: [{topic, correct, total}]. */
    public function topicStatsForAttempt(ExamAttempt $attempt): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')->where('a.attempt_id', $attempt->id)
        );
    }

    /** Per-topic breakdown across all submitted attempts of an exam (class-wide). */
    public function topicStatsForExam(Exam $exam): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->where('at.exam_id', $exam->id)
                ->where('at.status', 'submitted')
        );
    }

    /**
     * Aggregate a student's performance: overall, then per subject, and within
     * each subject a per-topic breakdown and the list of tests taken.
     */
    public function studentStats(Student $student, ?\Illuminate\Support\Carbon $since = null): array
    {
        $attempts = ExamAttempt::where('student_id', $student->id)
            ->where('status', 'submitted')
            ->when($since, fn ($q) => $q->where('submitted_at', '>=', $since))
            ->with(['exam.subject', 'exam.topic'])
            ->orderByDesc('submitted_at')
            ->get();

        $topicRows = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            ->where('at.student_id', $student->id)
            ->where('at.status', 'submitted')
            ->when($since, fn ($q) => $q->where('at.submitted_at', '>=', $since))
            ->selectRaw('q.subject_id, t.external_id, t.title as topic, count(*) as total, sum(a.is_correct) as correct')
            ->groupBy('q.subject_id', 't.external_id', 't.title')
            ->orderByRaw('CAST(t.external_id AS UNSIGNED)')
            ->get()
            ->groupBy('subject_id');

        $subjects = [];
        foreach ($attempts->groupBy(fn ($a) => $a->exam->subject_id) as $subjectId => $group) {
            $subjects[] = [
                'subject'     => $group->first()->exam->subject?->name ?? 'Subject',
                'tests_count' => $group->count(),
                'avg'         => (int) round($group->avg(fn ($a) => $a->percentage)),
                'topics'      => collect($topicRows[$subjectId] ?? [])->map(fn ($r) => [
                    'topic'   => $r->topic ?? 'Untagged',
                    'correct' => (int) $r->correct,
                    'total'   => (int) $r->total,
                    'percent' => $r->total ? (int) round($r->correct / $r->total * 100) : 0,
                ])->all(),
                'tests'       => $group->map(fn ($a) => [
                    'exam_id' => $a->exam_id,
                    'title'   => $a->exam->title,
                    'topic'   => $a->exam->topic?->title ?? 'Mixed',
                    'score'   => $a->score,
                    'total'   => $a->total_questions,
                    'percent' => $a->percentage,
                    'date'    => $a->submitted_at,
                ])->all(),
            ];
        }

        return [
            'overall'  => [
                'tests' => $attempts->count(),
                'avg'   => $attempts->count() ? (int) round($attempts->avg(fn ($a) => $a->percentage)) : 0,
            ],
            'subjects' => $subjects,
        ];
    }

    /** Per-topic stats aggregated across every submitted attempt in a whole school. */
    public function schoolTopicStats(int $schoolId, ?int $branchId = null): array
    {
        return $this->topicStats($this->schoolAnswersBase($schoolId, $branchId));
    }

    /** Per-topic stats across one teacher's exams (their students' results by topic). */
    public function teacherTopicStats(int $teacherId): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->where('e.created_by', $teacherId)
                ->where('at.status', 'submitted')
        );
    }

    /** Overview totals for one teacher (their classes/students/exams + avg). */
    public function teacherOverview(int $teacherId): array
    {
        $classIds = DB::table('v2_class_teachers')->where('teacher_id', $teacherId)->pluck('class_id');

        $students = $classIds->isEmpty() ? 0 : (int) DB::table('v2_student_enrollments')
            ->whereIn('class_id', $classIds)->where('status', 'active')->distinct()->count('student_id');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.created_by', $teacherId)->where('at.status', 'submitted')
            ->selectRaw('count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')->first();

        return [
            'classes'     => $classIds->count(),
            'students'    => $students,
            'exams'       => DB::table('v2_exams')->where('created_by', $teacherId)->count(),
            'submissions' => (int) ($perf->submissions ?? 0),
            'avg'         => ($perf && $perf->avg_pct !== null) ? (int) round($perf->avg_pct) : null,
        ];
    }

    /** School-wide totals (optionally narrowed to one branch). */
    public function schoolOverview(int $schoolId, ?int $branchId = null): array
    {
        $ids = $branchId ? $this->branchClassIds($branchId) : null;

        $avg = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->when($ids !== null, fn ($q) => $q->whereIn('e.class_id', $ids))
            ->selectRaw('count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 as avg_pct')->first();

        return [
            'students'    => DB::table('v2_students')->where('school_id', $schoolId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'teachers'    => DB::table('v2_teachers')->where('school_id', $schoolId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'classes'     => DB::table('v2_classes')->where('school_id', $schoolId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'exams'       => DB::table('v2_exams')->where('school_id', $schoolId)->when($ids !== null, fn ($q) => $q->whereIn('class_id', $ids))->count(),
            'submissions' => (int) ($avg->submissions ?? 0),
            'avg'         => ($avg && $avg->avg_pct !== null) ? (int) round($avg->avg_pct) : null,
        ];
    }

    /** Per-teacher summary rows for the school (optionally narrowed to a branch). */
    public function schoolTeacherRows(int $schoolId, ?int $branchId = null): array
    {
        $exams = DB::table('v2_exams')->where('school_id', $schoolId)
            ->selectRaw('created_by, count(*) as exams, count(distinct class_id) as classes')
            ->groupBy('created_by')->get()->keyBy('created_by');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->selectRaw('e.created_by, count(*) submissions, avg(at.score / at.total_questions) * 100 as avg_pct')
            ->groupBy('e.created_by')->get()->keyBy('created_by');

        return DB::table('v2_teachers')->where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'classes'     => (int) ($exams[$t->id]->classes ?? 0),
                'exams'       => (int) ($exams[$t->id]->exams ?? 0),
                'submissions' => (int) ($perf[$t->id]->submissions ?? 0),
                'avg'         => isset($perf[$t->id]) && $perf[$t->id]->avg_pct !== null ? (int) round($perf[$t->id]->avg_pct) : null,
            ])->all();
    }

    /** Per-class summary rows for the school (optionally narrowed to a branch). */
    public function schoolClassRows(int $schoolId, ?int $branchId = null): array
    {
        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->selectRaw('e.class_id, avg(at.score / at.total_questions) * 100 as avg_pct')
            ->groupBy('e.class_id')->get()->keyBy('class_id');

        $examCounts = DB::table('v2_exams')->where('school_id', $schoolId)
            ->selectRaw('class_id, count(*) c')->groupBy('class_id')->pluck('c', 'class_id');

        // class -> teacher names via raw join (avoids the BelongsToMany school_id ambiguity)
        $classTeachers = DB::table('v2_class_teachers as ct')->join('v2_teachers as tt', 'tt.id', '=', 'ct.teacher_id')
            ->where('ct.school_id', $schoolId)->select('ct.class_id', 'tt.name')->get()->groupBy('class_id');

        return SchoolClass::where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['grade', 'subject'])
            ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')])
            ->orderBy('name')->get()
            ->map(fn ($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'grade'    => $c->grade?->name,
                'subject'  => $c->subject?->name,
                'teacher'  => ($classTeachers[$c->id] ?? collect())->pluck('name')->implode(', ') ?: '—',
                'students' => $c->student_count,
                'exams'    => (int) ($examCounts[$c->id] ?? 0),
                'avg'      => isset($perf[$c->id]) && $perf[$c->id]->avg_pct !== null ? (int) round($perf[$c->id]->avg_pct) : null,
            ])->all();
    }

    /** Class ids belonging to one branch (for branch-scoped roll-ups). */
    private function branchClassIds(int $branchId): array
    {
        return DB::table('v2_classes')->where('branch_id', $branchId)->pluck('id')->all();
    }

    private function schoolAnswersBase(int $schoolId, ?int $branchId = null)
    {
        return DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)
            ->where('at.status', 'submitted')
            ->when($branchId, fn ($q) => $q->whereIn('e.class_id', $this->branchClassIds($branchId)));
    }

    /** Per-topic stats aggregated across every submitted attempt in a class. */
    public function classTopicStats(SchoolClass $class): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->where('e.class_id', $class->id)
                ->where('at.status', 'submitted')
        );
    }

    /**
     * Per-student × per-topic matrix for a class (across all its exams):
     * ['topics' => [titles...], 'rows' => [['student','roll','overall','attempted','cells'=>[topic=>{...}|null]]]].
     */
    public function classStudentMatrix(SchoolClass $class): array
    {
        $rows = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            ->where('e.class_id', $class->id)
            ->where('at.status', 'submitted')
            ->selectRaw('at.student_id, t.external_id, COALESCE(t.title, "Untagged") as topic, count(*) total, sum(a.is_correct) correct')
            ->groupBy('at.student_id', 't.external_id', 't.title')
            ->orderByRaw('CAST(t.external_id AS UNSIGNED)')
            ->get();

        $topicCols = [];
        foreach ($rows as $r) {
            if (! in_array($r->topic, $topicCols, true)) {
                $topicCols[] = $r->topic;
            }
        }

        $byStudent = $rows->groupBy('student_id');
        $studentIds = StudentEnrollment::where('class_id', $class->id)->pluck('student_id');
        $students = Student::whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name', 'roll_number']);

        $matrix = [];
        foreach ($students as $st) {
            $sr = $byStudent[$st->id] ?? collect();
            $cells = [];
            $tc = $cc = 0;
            foreach ($topicCols as $topic) {
                $row = $sr->firstWhere('topic', $topic);
                if ($row) {
                    $cells[$topic] = [
                        'correct' => (int) $row->correct,
                        'total'   => (int) $row->total,
                        'percent' => $row->total ? (int) round($row->correct / $row->total * 100) : 0,
                    ];
                    $tc += $row->total;
                    $cc += $row->correct;
                } else {
                    $cells[$topic] = null;
                }
            }
            $matrix[] = [
                'id'        => $st->id,
                'student'   => $st->name,
                'roll'      => $st->roll_number,
                'overall'   => $tc ? (int) round($cc / $tc * 100) : null,
                'attempted' => $tc > 0,
                'cells'     => $cells,
            ];
        }

        return ['topics' => $topicCols, 'rows' => $matrix];
    }

    /*
    |--------------------------------------------------------------------------
    | Super Admin drill-down aggregates (grade / subject / topic / single-mixed)
    |--------------------------------------------------------------------------
    | All raw DB::table (so no global scope fires) and keyed on explicit ids, so
    | the unscoped Super Admin can call them for any school. New helpers use
    | nullif(total_questions,0) to avoid the divide-by-zero the older school*
    | rollups can hit on a 0-question exam.
    */

    /** Per-grade summary rows for one school (optionally narrowed to a branch). */
    public function gradeWideRows(int $schoolId, ?int $branchId = null): array
    {
        $classCounts = DB::table('v2_classes')->where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('grade_id, count(*) c')->groupBy('grade_id')->pluck('c', 'grade_id');

        $studentCounts = DB::table('v2_student_enrollments as se')
            ->join('v2_classes as c', 'c.id', '=', 'se.class_id')
            ->where('c.school_id', $schoolId)->where('se.status', 'active')
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.grade_id, count(distinct se.student_id) c')
            ->groupBy('c.grade_id')->pluck('c', 'grade_id');

        $examCounts = DB::table('v2_exams as e')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->where('e.school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.grade_id, count(*) c')->groupBy('c.grade_id')->pluck('c', 'grade_id');

        $perf = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.grade_id, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('c.grade_id')->get()->keyBy('grade_id');

        return DB::table('v2_grades')->where('school_id', $schoolId)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($g) => [
                'id'          => $g->id,
                'grade'       => $g->name,
                'classes'     => (int) ($classCounts[$g->id] ?? 0),
                'students'    => (int) ($studentCounts[$g->id] ?? 0),
                'exams'       => (int) ($examCounts[$g->id] ?? 0),
                'submissions' => (int) ($perf[$g->id]->submissions ?? 0),
                'avg'         => isset($perf[$g->id]) && $perf[$g->id]->avg_pct !== null ? (int) round($perf[$g->id]->avg_pct) : null,
            ])->all();
    }

    /** Per-topic stats restricted to one grade's classes within a school. */
    public function gradeTopicStats(int $schoolId, int $gradeId, ?int $branchId = null): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
                ->where('e.school_id', $schoolId)
                ->where('c.grade_id', $gradeId)
                ->where('at.status', 'submitted')
                ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
        );
    }

    /** Per-subject summary rows for one school (optionally narrowed to a branch). */
    public function subjectWideRows(int $schoolId, ?int $branchId = null): array
    {
        $ids = $branchId ? $this->branchClassIds($branchId) : null;

        $classCounts = DB::table('v2_classes')->where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('subject_id, count(*) c')->groupBy('subject_id')->pluck('c', 'subject_id');

        $studentCounts = DB::table('v2_student_enrollments as se')
            ->join('v2_classes as c', 'c.id', '=', 'se.class_id')
            ->where('c.school_id', $schoolId)->where('se.status', 'active')
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.subject_id, count(distinct se.student_id) c')
            ->groupBy('c.subject_id')->pluck('c', 'subject_id');

        $examCounts = DB::table('v2_exams')->where('school_id', $schoolId)
            ->when($ids !== null, fn ($q) => $q->whereIn('class_id', $ids))
            ->selectRaw('subject_id, count(*) c')->groupBy('subject_id')->pluck('c', 'subject_id');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->when($ids !== null, fn ($q) => $q->whereIn('e.class_id', $ids))
            ->selectRaw('e.subject_id, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('e.subject_id')->get()->keyBy('subject_id');

        $subjectIds = $classCounts->keys()->merge($examCounts->keys())->unique()->filter()->values();
        $names = DB::table('v2_subjects')->whereIn('id', $subjectIds)->pluck('name', 'id');

        return $subjectIds
            ->map(fn ($sid) => [
                'id'          => (int) $sid,
                'subject'     => $names[$sid] ?? 'Subject',
                'classes'     => (int) ($classCounts[$sid] ?? 0),
                'students'    => (int) ($studentCounts[$sid] ?? 0),
                'exams'       => (int) ($examCounts[$sid] ?? 0),
                'submissions' => (int) ($perf[$sid]->submissions ?? 0),
                'avg'         => isset($perf[$sid]) && $perf[$sid]->avg_pct !== null ? (int) round($perf[$sid]->avg_pct) : null,
            ])
            ->sortBy('subject')->values()->all();
    }

    /**
     * Per-topic stats within one subject. $schoolId null => platform-wide
     * (across all schools); an int restricts to that school.
     */
    public function subjectTopicStats(?int $schoolId, int $subjectId, ?int $branchId = null): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->where('e.subject_id', $subjectId)
                ->where('at.status', 'submitted')
                ->when($schoolId, fn ($q) => $q->where('e.school_id', $schoolId))
                ->when($branchId, fn ($q) => $q->whereIn('e.class_id', $this->branchClassIds($branchId)))
        );
    }

    /**
     * Answer-weighted per-topic rollup across all subjects. $schoolId null =>
     * platform-wide; an int restricts to that school.
     */
    public function topicWideStats(?int $schoolId = null): array
    {
        $base = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->where('at.status', 'submitted');

        if ($schoolId !== null) {
            $base->join('v2_exams as e', 'e.id', '=', 'at.exam_id')->where('e.school_id', $schoolId);
        }

        return $this->topicStats($base);
    }

    /**
     * Cross-school grade rollup. Grouped by grade NAME (grade_id is per-school
     * and not comparable across schools), so differently-named grades won't merge.
     */
    public function gradePlatformRows(): array
    {
        $classAgg = DB::table('v2_classes as c')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->selectRaw('g.name, count(distinct c.school_id) schools, count(*) classes')
            ->groupBy('g.name')->get()->keyBy('name');

        $studentAgg = DB::table('v2_student_enrollments as se')
            ->join('v2_classes as c', 'c.id', '=', 'se.class_id')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->where('se.status', 'active')
            ->selectRaw('g.name, count(distinct se.student_id) students')
            ->groupBy('g.name')->get()->keyBy('name');

        $examAgg = DB::table('v2_exams as e')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->selectRaw('g.name, count(*) exams')
            ->groupBy('g.name')->get()->keyBy('name');

        $perf = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->where('at.status', 'submitted')
            ->selectRaw('g.name, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('g.name')->get()->keyBy('name');

        return $classAgg->keys()->map(fn ($name) => [
            'grade'       => $name,
            'schools'     => (int) ($classAgg[$name]->schools ?? 0),
            'classes'     => (int) ($classAgg[$name]->classes ?? 0),
            'students'    => (int) ($studentAgg[$name]->students ?? 0),
            'exams'       => (int) ($examAgg[$name]->exams ?? 0),
            'submissions' => (int) ($perf[$name]->submissions ?? 0),
            'avg'         => isset($perf[$name]) && $perf[$name]->avg_pct !== null ? (int) round($perf[$name]->avg_pct) : null,
        ])->sortByDesc('submissions')->values()->all();
    }

    /** Cross-school subject rollup, grouped by the global subject id. */
    public function subjectPlatformRows(): array
    {
        $examAgg = DB::table('v2_exams')
            ->selectRaw('subject_id, count(distinct school_id) schools, count(*) exams')
            ->groupBy('subject_id')->get()->keyBy('subject_id');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('at.status', 'submitted')
            ->selectRaw('e.subject_id, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('e.subject_id')->get()->keyBy('subject_id');

        $names = DB::table('v2_subjects')->pluck('name', 'id');

        return $examAgg->keys()->filter()->map(fn ($sid) => [
            'id'          => (int) $sid,
            'subject'     => $names[$sid] ?? 'Subject',
            'schools'     => (int) ($examAgg[$sid]->schools ?? 0),
            'exams'       => (int) ($examAgg[$sid]->exams ?? 0),
            'submissions' => (int) ($perf[$sid]->submissions ?? 0),
            'avg'         => isset($perf[$sid]) && $perf[$sid]->avg_pct !== null ? (int) round($perf[$sid]->avg_pct) : null,
        ])->sortByDesc('submissions')->values()->all();
    }

    /**
     * Single-topic vs mixed-topic exam split (mixed = topic_id IS NULL).
     * Optional $schoolId / $classId narrow the scope; null = platform-wide.
     */
    public function examKindSplit(?int $schoolId = null, ?int $classId = null, ?int $createdBy = null, ?int $branchId = null): array
    {
        $ids = $branchId ? $this->branchClassIds($branchId) : null;

        $examRows = DB::table('v2_exams')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($createdBy, fn ($q) => $q->where('created_by', $createdBy))
            ->when($ids !== null, fn ($q) => $q->whereIn('class_id', $ids))
            ->selectRaw('(topic_id IS NULL) as is_mixed, count(*) exams')
            ->groupBy('is_mixed')->get()->keyBy('is_mixed');

        $perfRows = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('at.status', 'submitted')
            ->when($schoolId, fn ($q) => $q->where('e.school_id', $schoolId))
            ->when($classId, fn ($q) => $q->where('e.class_id', $classId))
            ->when($createdBy, fn ($q) => $q->where('e.created_by', $createdBy))
            ->when($ids !== null, fn ($q) => $q->whereIn('e.class_id', $ids))
            ->selectRaw('(e.topic_id IS NULL) as is_mixed, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('is_mixed')->get()->keyBy('is_mixed');

        $pick = fn ($mixed) => [
            'exams'       => (int) ($examRows[$mixed]->exams ?? 0),
            'submissions' => (int) ($perfRows[$mixed]->submissions ?? 0),
            'avg'         => isset($perfRows[$mixed]) && $perfRows[$mixed]->avg_pct !== null ? (int) round($perfRows[$mixed]->avg_pct) : null,
        ];

        return ['single' => $pick(0), 'mixed' => $pick(1)];
    }

    private function topicStats($query): array
    {
        return $query
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            ->selectRaw('t.external_id, t.title, count(*) as total, sum(a.is_correct) as correct')
            ->groupBy('t.external_id', 't.title')
            ->orderByRaw('CAST(t.external_id AS UNSIGNED)')
            ->get()
            ->map(fn ($r) => [
                'topic'   => $r->title ?? 'Untagged',
                'correct' => (int) $r->correct,
                'total'   => (int) $r->total,
                'percent' => $r->total ? (int) round($r->correct / $r->total * 100) : 0,
            ])
            ->all();
    }
}
