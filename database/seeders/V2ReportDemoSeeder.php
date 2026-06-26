<?php

namespace Database\Seeders;

use App\Models\V2\Branch;
use App\Models\V2\ClassTeacher;
use App\Models\V2\Exam;
use App\Models\V2\Grade;
use App\Models\V2\Question;
use App\Models\V2\School;
use App\Models\V2\SchoolClass;
use App\Models\V2\SchoolSubject;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Subject;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| V2ReportDemoSeeder - one O-Level student with a full cross-subject history
|--------------------------------------------------------------------------
| Builds the missing O-Level Physics class, then a single "report demo" student
| (Hira Bukhari) enrolled in O-Level Chemistry + Physics + Biology, and gives
| her 10 tests per subject (30 total) spread across the last month with VARIED,
| topic-aware results so the branch-admin student report has rich data to show:
|   - strong Biology, mid Chemistry, weaker Physics (clear "area to improve")
|   - per-topic strengths/weaknesses within each subject
|   - a gentle improvement trend over the month
| The rest of the O-Level cohort also attempts (deterministically) so the exams
| have realistic class averages. Idempotent + deterministic.
|
| After running, open the report from: Branch Admin -> Analytics -> Students ->
| Hira Bukhari -> generate report.  Login (password `password`): hira.bukhari@thesage.edu.pk
*/
class V2ReportDemoSeeder extends Seeder
{
    private const PW = 'password';

    private const TESTS_PER_SUBJECT = 10;

    /** Per-subject base ability for the report student (drives clear differentiation). */
    private const BASE = ['5090' => 0.80, '5070' => 0.63, '5054' => 0.52];

    public function run(): void
    {
        $school = School::where('name', 'The Sage School')->first();
        if (! $school) {
            $this->command->error('The Sage School not found. Run V2DemoSeeder + V2ScienceDemoSeeder first.');
            return;
        }
        $branchId = Branch::where('school_id', $school->id)->where('name', 'Gulberg Campus')->value('id');
        $oGrade = Grade::where('school_id', $school->id)->where('short_name', 'O-1')->first();
        if (! $oGrade) {
            $this->command->error('O-Level grade (O-1) missing. Run V2ScienceDemoSeeder first.');
            return;
        }

        $subjects = Subject::whereIn('code', ['5070', '5054', '5090'])->get()->keyBy('code');
        $svc = app(ExamService::class);

        // --- Ensure O-Level Physics (5054): assignment, class, teacher (reuse Bilal) ---
        SchoolSubject::updateOrCreate(
            ['school_id' => $school->id, 'subject_id' => $subjects['5054']->id, 'grade_id' => $oGrade->id],
            ['is_active' => true]
        );
        $physicsTeacher = Teacher::where('email', 'bilal@thesage.edu.pk')->first()
            ?: Teacher::updateOrCreate(
                ['email' => 'bilal@thesage.edu.pk'],
                ['school_id' => $school->id, 'branch_id' => $branchId, 'name' => 'Mr. Bilal Ahmed', 'password' => self::PW,
                 'employee_id' => 'TCH-PHY', 'status' => 'active', 'must_change_password' => false, 'session_version' => 1]
            );

        // The three O-Level science classes (Chemistry + Biology already exist from V2ScienceDemoSeeder).
        $classes = [
            '5070' => SchoolClass::where('school_id', $school->id)->where('grade_id', $oGrade->id)->where('subject_id', $subjects['5070']->id)->first(),
            '5090' => SchoolClass::where('school_id', $school->id)->where('grade_id', $oGrade->id)->where('subject_id', $subjects['5090']->id)->first(),
            '5054' => SchoolClass::updateOrCreate(
                ['school_id' => $school->id, 'grade_id' => $oGrade->id, 'subject_id' => $subjects['5054']->id, 'section' => 'A'],
                ['name' => 'O-A Physics', 'branch_id' => $branchId, 'is_active' => true]
            ),
        ];
        ClassTeacher::updateOrCreate(
            ['class_id' => $classes['5054']->id, 'teacher_id' => $physicsTeacher->id],
            ['school_id' => $school->id, 'is_primary' => true]
        );

        // --- The report student, enrolled in all three sciences ---
        $student = Student::updateOrCreate(
            ['school_id' => $school->id, 'roll_number' => 'O26-301'],
            ['name' => 'Hira Bukhari', 'email' => 'hira.bukhari@thesage.edu.pk', 'password' => self::PW,
             'branch_id' => $branchId, 'grade' => 'O-1', 'status' => 'active', 'must_change_password' => false, 'session_version' => 1]
        );

        // Existing O-Level cohort (for realistic class averages); also enroll them in the new Physics class.
        $cohort = Student::where('school_id', $school->id)->where('grade', 'O-1')
            ->where('id', '!=', $student->id)->orderBy('id')->get();

        $allStudents = $cohort->concat([$student]); // does not mutate $cohort
        foreach ($classes as $code => $class) {
            foreach ($allStudents as $st) {
                StudentEnrollment::updateOrCreate(
                    ['student_id' => $st->id, 'class_id' => $class->id],
                    ['school_id' => $school->id, 'branch_id' => $branchId, 'status' => 'active']
                );
            }
        }

        // --- Per-subject topic affinity for the report student (deterministic) ---
        $affinity = [];
        foreach ($subjects as $code => $subj) {
            foreach (\App\Models\V2\Topic::where('subject_id', $subj->id)->pluck('id') as $tid) {
                $affinity[$tid] = $this->rand($student->id, $tid, 7) * 0.36 - 0.18; // -0.18 .. +0.18
            }
        }

        // --- 10 exams per subject across the last ~30 days + attempts ---
        $subjIndex = 0;
        foreach (['5070', '5054', '5090'] as $code) {
            $class = $classes[$code];
            $subj = $subjects[$code];
            $pool = Question::active()->has('options')->where('subject_id', $subj->id)->count();
            if ($pool < 14) {
                $this->command->warn("  {$subj->name}: pool {$pool} too small, skipping.");
                $subjIndex++;
                continue;
            }
            $enrolled = StudentEnrollment::where('class_id', $class->id)->pluck('student_id')
                ->map(fn ($id) => Student::find($id))->filter();

            for ($n = 1; $n <= self::TESTS_PER_SUBJECT; $n++) {
                // spread: test 1 ~28 days ago ... test 10 ~2 days ago, staggered per subject
                $dayAgo = max(1, 30 - ($n - 1) * 3 - $subjIndex);
                $title = "{$subj->name} Practice Test {$n}";

                $exam = Exam::where('class_id', $class->id)->where('title', $title)->first();
                if (! $exam) {
                    $exam = $svc->generate($this->teacherFor($class), [
                        'class_id' => $class->id, 'topic_id' => null,
                        'question_count' => 14, 'title' => $title,
                        'duration_minutes' => 25, 'year_from' => 2014, 'year_to' => 2024,
                    ]);
                }
                $examDate = Carbon::now()->subDays($dayAgo);
                $exam->forceFill([
                    'status' => 'released',
                    'available_from' => $examDate->copy()->subHours(2),
                    'available_until' => null,
                    'results_released_at' => $examDate,
                ])->save();

                // gentle improvement over the month: earliest -0.07 ... latest +0.06
                $trend = ((30 - $dayAgo) / 30) * 0.13 - 0.07;
                $this->seedAttempts($svc, $exam, $enrolled, $student->id, self::BASE[$code], $affinity, $trend, $examDate);
            }
            $subjIndex++;
        }

        $tests = \App\Models\V2\ExamAttempt::where('student_id', $student->id)->where('status', 'submitted')->count();
        $this->command->info("V2ReportDemoSeeder complete. Report student: Hira Bukhari (hira.bukhari@thesage.edu.pk, password: password) - {$tests} submitted tests across O-Level Chemistry, Physics, Biology.");
    }

    private function teacherFor(SchoolClass $class): Teacher
    {
        $tid = ClassTeacher::where('class_id', $class->id)->value('teacher_id');
        return Teacher::find($tid) ?? Teacher::where('school_id', $class->school_id)->first();
    }

    private function seedAttempts(ExamService $svc, Exam $exam, $students, int $reportStudentId, float $base, array $affinity, float $trend, Carbon $examDate): void
    {
        $exam->loadMissing('examQuestions.question:id,topic_id,correct_answer');

        foreach ($students as $student) {
            $isReport = $student->id === $reportStudentId;
            // Report student always attempts; cohort ~75%.
            if (! $isReport && $this->rand($student->id, $exam->id, 1) > 0.75) {
                continue;
            }
            $attempt = $svc->startAttempt($exam, $student);
            if ($attempt->status === 'submitted') {
                continue;
            }
            $ability = $isReport ? $base : (0.45 + $this->rand($student->id, 99) * 0.4);

            $responses = [];
            foreach ($exam->examQuestions as $eq) {
                $q = $eq->question;
                if (! $q || ! $q->correct_answer) {
                    continue;
                }
                $p = $isReport
                    ? max(0.05, min(0.97, $base + ($affinity[$q->topic_id] ?? 0) + $trend))
                    : max(0.05, min(0.95, $ability));
                if ($this->rand($student->id, $q->id, $exam->id, 2) <= $p) {
                    $responses[$eq->question_id] = $q->correct_answer;
                } else {
                    $wrong = array_values(array_diff(['A', 'B', 'C', 'D'], [$q->correct_answer]));
                    $responses[$eq->question_id] = $wrong[(int) floor($this->rand($student->id, $q->id, 3) * count($wrong))];
                }
            }
            $svc->submit($attempt, $responses);

            $submitted = $examDate->copy()->addMinutes((int) floor($this->rand($student->id, $exam->id, 4) * 600));
            $attempt->update([
                'started_at'   => $submitted->copy()->subMinutes((int) floor($this->rand($student->id, $exam->id, 5) * 18) + 10),
                'submitted_at' => $submitted,
            ]);
        }
    }

    private function rand(int ...$parts): float
    {
        $x = 2166136261;
        foreach ($parts as $p) {
            $x ^= $p;
            $x = ($x * 16777619) & 0xFFFFFFFF;
        }

        return ($x % 100000) / 100000;
    }
}
