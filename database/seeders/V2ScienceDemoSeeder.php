<?php

namespace Database\Seeders;

use App\Models\V2\Branch;
use App\Models\V2\ClassTeacher;
use App\Models\V2\Exam;
use App\Models\V2\Grade;
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
| V2ScienceDemoSeeder - Chemistry + Biology demo, O Level and A Level
|--------------------------------------------------------------------------
| The base V2DemoSeeder only sets up A-Level Physics. This adds the science
| spine the demo was missing: O-Level grade + Chemistry/Biology teachers,
| classes, O-Level students, subject assignments, and (for subjects whose
| question bank exists) sample exams with marked attempts - so a reviewer can
| log in and SEE Chemistry and Biology working end to end.
|
| Coverage (The Sage School, Gulberg campus):
|   - O Level grade   ← Chemistry 5070, Biology 5090   (both imported)
|   - AS Level grade   ← Chemistry 9701, Biology 9700   (9700 bank lands later)
|   - 2 teachers: one Chemistry (O+A), one Biology (O+A)
|   - 4 classes (O-Chem, AS-Chem, O-Bio, AS-Bio), teacher-assigned
|   - 16 fresh O-Level students enrolled in both O-Level classes
|   - existing Gulberg AS students also enrolled in the AS science classes
|   - 1 released exam + attempts for each class whose subject has questions
|
| Idempotent + deterministic. All accounts use password `password`.
| Re-runnable safely; A-Level Biology exams appear automatically once 9700 is imported.
*/
class V2ScienceDemoSeeder extends Seeder
{
    private const PW = 'password';

    private array $names = [
        'Hassan Javed', 'Ayesha Noor', 'Bilal Saeed', 'Mariam Khan', 'Owais Tariq',
        'Sana Riaz', 'Hamza Latif', 'Zoya Aslam', 'Usman Bhatti', 'Hira Shafiq',
        'Saad Qadir', 'Mahnoor Yousaf', 'Talha Mirza', 'Iqra Hmeed', 'Daniyal Raza',
        'Noor ul Ain', 'Abdullah Munir', 'Rabia Pervaiz', 'Faraz Anwar', 'Maryam Sajid',
    ];

    private int $cursor = 0;

    public function run(): void
    {
        $school = School::where('name', 'The Sage School')->first();
        if (! $school) {
            $this->command->error('The Sage School not found. Run V2DemoSeeder first.');
            return;
        }
        $branch = Branch::where('school_id', $school->id)->where('name', 'Gulberg Campus')->first()
            ?: Branch::where('school_id', $school->id)->orderBy('id')->first();
        $branchId = $branch?->id;

        // Subjects (already seeded by V2SubjectsSeeder - resolve, don't create).
        $subjects = Subject::whereIn('code', ['5070', '9701', '5090', '9700'])->get()->keyBy('code');
        foreach (['5070', '9701', '5090', '9700'] as $code) {
            if (! isset($subjects[$code])) {
                $this->command->error("Subject {$code} missing from v2_subjects. Run V2SubjectsSeeder.");
                return;
            }
        }

        // --- Grades: reuse AS Level Year 1, add O Level ---
        $asGrade = Grade::firstWhere(['school_id' => $school->id, 'short_name' => 'AS-1'])
            ?: Grade::where('school_id', $school->id)->where('name', 'like', 'AS%')->first();
        $oGrade = Grade::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'O Level Year 1'],
            ['short_name' => 'O-1', 'sort_order' => 3, 'is_active' => true]
        );

        // --- Subject assignment matrix (per grade) ---
        $assign = [
            [$oGrade, '5070'], [$oGrade, '5090'],     // O Level: Chemistry + Biology
            [$asGrade, '9701'], [$asGrade, '9700'],   // AS Level: Chemistry + Biology
        ];
        foreach ($assign as [$grade, $code]) {
            if ($grade) {
                SchoolSubject::updateOrCreate(
                    ['school_id' => $school->id, 'subject_id' => $subjects[$code]->id, 'grade_id' => $grade->id],
                    ['is_active' => true]
                );
            }
        }

        // --- Teachers: one Chemistry, one Biology (both teach O + A) ---
        $chemT = Teacher::updateOrCreate(
            ['email' => 'sana@thesage.edu.pk'],
            ['school_id' => $school->id, 'branch_id' => $branchId, 'name' => 'Ms. Sana Iqbal', 'password' => self::PW,
             'employee_id' => 'TCH-CHEM', 'status' => 'active', 'must_change_password' => false, 'session_version' => 1]
        );
        $bioT = Teacher::updateOrCreate(
            ['email' => 'imran@thesage.edu.pk'],
            ['school_id' => $school->id, 'branch_id' => $branchId, 'name' => 'Mr. Imran Dar', 'password' => self::PW,
             'employee_id' => 'TCH-BIO', 'status' => 'active', 'must_change_password' => false, 'session_version' => 1]
        );

        // --- Classes: (grade, subject, section, name, teacher) ---
        $svc = app(ExamService::class);
        $blueprint = [
            ['grade' => $oGrade,  'code' => '5070', 'section' => 'A', 'name' => 'O-A Chemistry',  'teacher' => $chemT, 'level' => 'O'],
            ['grade' => $asGrade, 'code' => '9701', 'section' => 'A', 'name' => 'AS-A Chemistry', 'teacher' => $chemT, 'level' => 'A'],
            ['grade' => $oGrade,  'code' => '5090', 'section' => 'A', 'name' => 'O-A Biology',    'teacher' => $bioT,  'level' => 'O'],
            ['grade' => $asGrade, 'code' => '9700', 'section' => 'A', 'name' => 'AS-A Biology',   'teacher' => $bioT,  'level' => 'A'],
        ];

        // 16 fresh O-Level students (distinct roll range so they never collide with the physics rolls).
        $oStudents = collect();
        for ($i = 1; $i <= 16; $i++) {
            $roll = 'O26-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $st = Student::updateOrCreate(
                ['school_id' => $school->id, 'roll_number' => $roll],
                ['name' => $this->nextName(), 'email' => strtolower($roll).'@thesage.edu.pk', 'password' => self::PW,
                 'branch_id' => $branchId, 'grade' => 'O-1', 'status' => 'active',
                 'must_change_password' => false, 'session_version' => 1]
            );
            $oStudents->push($st);
        }

        // Existing Gulberg AS students (the physics AS-A cohort) reused for the AS science classes.
        $asStudents = Student::where('school_id', $school->id)
            ->where('grade', 'AS-1')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id')->get();
        if ($asStudents->isEmpty()) {
            $asStudents = Student::where('school_id', $school->id)->where('grade', 'AS-1')->orderBy('id')->limit(14)->get();
        }

        foreach ($blueprint as $b) {
            if (! $b['grade']) {
                continue;
            }
            $class = SchoolClass::updateOrCreate(
                ['school_id' => $school->id, 'grade_id' => $b['grade']->id, 'subject_id' => $subjects[$b['code']]->id, 'section' => $b['section']],
                ['name' => $b['name'], 'branch_id' => $branchId, 'is_active' => true]
            );
            ClassTeacher::updateOrCreate(
                ['class_id' => $class->id, 'teacher_id' => $b['teacher']->id],
                ['school_id' => $school->id, 'is_primary' => true]
            );

            $cohort = $b['level'] === 'O' ? $oStudents : $asStudents;
            foreach ($cohort as $st) {
                StudentEnrollment::updateOrCreate(
                    ['student_id' => $st->id, 'class_id' => $class->id],
                    ['school_id' => $school->id, 'branch_id' => $branchId, 'status' => 'active']
                );
            }

            // One released, mixed exam + marked attempts - only if the subject has a usable pool.
            $this->seedExam($svc, $b, $class, $cohort);
        }

        $this->command->info('V2ScienceDemoSeeder complete. Chemistry teacher: sana@thesage.edu.pk · Biology teacher: imran@thesage.edu.pk · O-Level student: o26-001@thesage.edu.pk (password: password).');
    }

    private function seedExam(ExamService $svc, array $b, SchoolClass $class, $cohort): void
    {
        $pool = \App\Models\V2\Question::query()->active()->has('options')
            ->where('subject_id', $class->subject_id)->count();
        if ($pool < 10) {
            $this->command->warn("  {$b['name']}: only {$pool} usable questions - skipping exam (e.g. 9700 not imported yet).");
            return;
        }

        $title = $b['name'].' - Term Test';

        $exam = Exam::where('class_id', $class->id)->where('title', $title)->first();
        if (! $exam) {
            try {
                $exam = $svc->generate($b['teacher'], [
                    'class_id' => $class->id, 'topic_id' => null,
                    'question_count' => 20, 'title' => $title,
                    'duration_minutes' => 40, 'year_from' => 2016, 'year_to' => 2024,
                ]);
            } catch (\Throwable $e) {
                $this->command->warn("  {$b['name']}: exam generation failed ({$e->getMessage()}).");
                return;
            }
        }

        // Release it (and its results) so it is visible from both the teacher and student sides.
        $exam->forceFill([
            'status' => 'released',
            'available_from' => now()->subDays(6),
            'available_until' => null,
            'results_released_at' => now()->subDays(1),
        ])->save();

        $this->seedAttempts($svc, $exam, $cohort);
    }

    private function seedAttempts(ExamService $svc, Exam $exam, $students): void
    {
        $exam->loadMissing('examQuestions.question:id,correct_answer');
        foreach ($students as $student) {
            if ($this->rand($student->id, $exam->id, 1) > 0.85) {
                continue; // ~15% have not attempted yet
            }
            $attempt = $svc->startAttempt($exam, $student);
            if ($attempt->status === 'submitted') {
                continue;
            }
            $ability = 0.45 + $this->rand($student->id, 100) * 0.45; // 0.45–0.90
            $responses = [];
            foreach ($exam->examQuestions as $eq) {
                $q = $eq->question;
                if (! $q || ! $q->correct_answer) {
                    continue;
                }
                if ($this->rand($student->id, $q->id, $exam->id, 2) <= $ability) {
                    $responses[$eq->question_id] = $q->correct_answer;
                } else {
                    $wrong = array_values(array_diff(['A', 'B', 'C', 'D'], [$q->correct_answer]));
                    $responses[$eq->question_id] = $wrong[(int) floor($this->rand($student->id, $q->id, 3) * count($wrong))];
                }
            }
            $svc->submit($attempt, $responses);

            $submitted = Carbon::now()->subDays((int) floor($this->rand($exam->id, $student->id, 4) * 5) + 1)
                ->subMinutes((int) floor($this->rand($exam->id, $student->id, 5) * 500));
            $attempt->update([
                'started_at'   => $submitted->copy()->subMinutes((int) floor($this->rand($exam->id, $student->id, 6) * 25) + 10),
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

    private function nextName(): string
    {
        $n = $this->names[$this->cursor % count($this->names)];
        $this->cursor++;

        return $n;
    }
}
