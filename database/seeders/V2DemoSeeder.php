<?php

namespace Database\Seeders;

use App\Models\V2\ClassTeacher;
use App\Models\V2\Exam;
use App\Models\V2\Grade;
use App\Models\V2\School;
use App\Models\V2\SchoolAdmin;
use App\Models\V2\SchoolClass;
use App\Models\V2\SchoolSubject;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Subject;
use App\Models\V2\SuperAdmin;
use App\Models\V2\Teacher;
use App\Models\V2\Topic;
use App\Services\V2\ExamService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| V2DemoSeeder — the canonical, multi-school demo dataset
|--------------------------------------------------------------------------
| Builds the full hierarchy (School → Grade → Teacher → Subject → Class →
| Students → Exams → Attempts) across several schools so the stats interfaces
| at EVERY tier have meaningful, differentiated data:
|   - Student:      per-subject / per-topic over several tests
|   - Teacher:      class results + per-student × per-topic matrix
|   - School Admin: school → teacher → class → student drill-down
|   - Super Admin:  cross-school totals / averages / MRR
|
| Everything is idempotent and DETERMINISTIC (re-running produces identical
| data). Exams are built with ExamService::generate(); attempts are marked by
| ExamService::submit() with TOPIC-AWARE answers, so each student has a real
| strength/weakness profile (e.g. strong in Kinematics, weak in Waves).
|
| All demo accounts use the password `password`.
| Prerequisites (subjects + Physics topics) are seeded automatically.
| Requires the Physics 9702 question bank to be imported + topic-tagged.
*/
class V2DemoSeeder extends Seeder
{
    private const PW = 'password';

    /** @var array<int,string> rotating Pakistani student name pool */
    private array $names = [
        'Ali Hassan', 'Sara Ahmed', 'Bilal Khan', 'Fatima Malik', 'Omar Sheikh',
        'Ayesha Tariq', 'Hamza Iqbal', 'Zainab Riaz', 'Usman Farooq', 'Hira Nadeem',
        'Saad Mahmood', 'Mahnoor Aslam', 'Hassan Raza', 'Iqra Javed', 'Daniyal Butt',
        'Noor Fatima', 'Abdullah Yousaf', 'Rabia Saleem', 'Talha Anwar', 'Maryam Akhtar',
        'Faizan Shah', 'Areeba Qureshi', 'Hamza Aziz', 'Komal Bukhari', 'Shahzaib Ali',
        'Anaya Hashmi', 'Rehan Siddiqui', 'Laiba Kamran', 'Wasif Mirza', 'Eman Zafar',
        'Arsalan Cheema', 'Dua Naveed', 'Hamza Waseem', 'Sana Gul', 'Bilal Younas',
        'Mehak Pervaiz', 'Zohaib Ashraf', 'Aiman Rauf', 'Taimoor Khalid', 'Hooria Sajid',
    ];

    private int $nameCursor = 0;

    /** @var array<int> all Physics topic ids (for per-student affinity vectors) */
    private array $physicsTopicIds = [];

    public function run(): void
    {
        // Catalog prerequisites (idempotent): subjects + Physics topics/subtopics.
        $this->call([V2SubjectsSeeder::class, V2TopicsSeeder::class]);

        $physics = Subject::where('code', '9702')->first();
        if (! $physics) {
            $this->command->error('Physics subject (9702) not found. Run V2SubjectsSeeder first.');
            return;
        }
        $this->physicsTopicIds = Topic::where('subject_id', $physics->id)->pluck('id')->all();
        $topicsByTitle = Topic::where('subject_id', $physics->id)->get()
            ->keyBy(fn ($t) => strtolower($t->title));

        // Other subjects for the assignment-matrix UI (no exams — only Physics has questions).
        $matrixSubjects = Subject::whereIn('code', ['9702', '9701', '9709'])->pluck('id')->all();

        $svc = app(ExamService::class);

        // Founder / us.
        SuperAdmin::updateOrCreate(
            ['email' => 'super@topicaled.com'],
            ['name' => 'TopicalEd Super Admin', 'password' => self::PW, 'must_change_password' => false]
        );

        foreach ($this->schoolConfigs() as $cfg) {
            $this->seedSchool($cfg, $physics, $topicsByTitle, $matrixSubjects, $svc);
        }

        $this->command->info('V2DemoSeeder complete: '
            .School::count().' schools, '.Teacher::count().' teachers, '
            .Student::count().' students, '.Exam::count().' exams, '
            .\App\Models\V2\ExamAttempt::count().' attempts.');
    }

    /** The three demo schools (flagship + two smaller for cross-school comparison). */
    private function schoolConfigs(): array
    {
        // Exam templates reused per class.
        $asExams = [
            ['title' => 'Kinematics Assessment',          'topic' => 'kinematics',      'count' => 15, 'dur' => 30],
            ['title' => 'Waves Topic Test',               'topic' => 'waves',           'count' => 15, 'dur' => 30],
            ['title' => 'AS Physics Mid-Term (Mixed)',    'topic' => null,              'count' => 20, 'dur' => 45],
        ];
        $a2Exams = [
            ['title' => 'Electric Fields Quiz',           'topic' => 'electric fields', 'count' => 12, 'dur' => 25],
            ['title' => 'A2 Physics Mock (Mixed)',        'topic' => null,              'count' => 15, 'dur' => 40],
        ];

        return [
            [
                'name' => 'The Sage School', 'domain' => 'thesage.edu.pk', 'city' => 'Lahore',
                'fee' => 100000, 'tier' => 'founding_partner', 'max_t' => 20, 'max_s' => 200,
                'grades' => [['AS Level Year 1', 'AS-1', 1], ['A2 Level Year 2', 'A2-1', 2]],
                'teachers' => ['Mr. Bilal Ahmed', 'Ms. Ayesha Raza', 'Mr. Hamza Tariq'],
                'classes' => [
                    ['name' => 'AS-A Physics', 'grade' => 'AS-1', 'teacher' => 'Mr. Bilal Ahmed', 'students' => 14, 'exams' => $asExams],
                    ['name' => 'AS-B Physics', 'grade' => 'AS-1', 'teacher' => 'Ms. Ayesha Raza', 'students' => 12, 'exams' => $asExams],
                    ['name' => 'A2-A Physics', 'grade' => 'A2-1', 'teacher' => 'Mr. Hamza Tariq', 'students' => 10, 'exams' => $a2Exams],
                ],
            ],
            [
                'name' => 'Beaconhouse Model', 'domain' => 'beaconhouse.edu.pk', 'city' => 'Karachi',
                'fee' => 75000, 'tier' => 'standard', 'max_t' => 30, 'max_s' => 300,
                'grades' => [['AS Level Year 1', 'AS-1', 1]],
                'teachers' => ['Mr. Usman Ali', 'Ms. Fatima Sheikh'],
                'classes' => [
                    ['name' => 'AS-A Physics', 'grade' => 'AS-1', 'teacher' => 'Mr. Usman Ali', 'students' => 12, 'exams' => $asExams],
                    ['name' => 'AS-B Physics', 'grade' => 'AS-1', 'teacher' => 'Ms. Fatima Sheikh', 'students' => 11, 'exams' => $asExams],
                ],
            ],
            [
                'name' => 'Roots Millennium', 'domain' => 'roots.edu.pk', 'city' => 'Islamabad',
                'fee' => 60000, 'tier' => 'standard', 'max_t' => 15, 'max_s' => 150,
                'grades' => [['AS Level Year 1', 'AS-1', 1]],
                'teachers' => ['Mr. Kamran Khan'],
                'classes' => [
                    ['name' => 'AS-A Physics', 'grade' => 'AS-1', 'teacher' => 'Mr. Kamran Khan', 'students' => 10, 'exams' => $asExams],
                ],
            ],
        ];
    }

    private function seedSchool(array $cfg, Subject $physics, $topicsByTitle, array $matrixSubjects, ExamService $svc): void
    {
        $school = School::updateOrCreate(
            ['contact_email' => 'admin@'.$cfg['domain']],
            [
                'name' => $cfg['name'], 'campus_name' => 'Main Campus', 'address' => $cfg['city'],
                'contact_phone' => '+92-300-0000000', 'monthly_fee' => $cfg['fee'],
                'license_tier' => $cfg['tier'], 'max_teachers' => $cfg['max_t'], 'max_students' => $cfg['max_s'],
                'status' => 'active', 'activated_at' => now()->subMonths(3),
            ]
        );

        SchoolAdmin::updateOrCreate(
            ['email' => 'admin@'.$cfg['domain']],
            ['school_id' => $school->id, 'name' => 'School Admin', 'password' => self::PW,
             'is_primary' => true, 'status' => 'active', 'must_change_password' => false, 'session_version' => 1]
        );

        // Grades.
        $grades = [];
        foreach ($cfg['grades'] as [$gname, $gshort, $gsort]) {
            $grades[$gshort] = Grade::updateOrCreate(
                ['school_id' => $school->id, 'name' => $gname],
                ['short_name' => $gshort, 'sort_order' => $gsort, 'is_active' => true]
            );
        }

        // Subject assignment matrix (Physics + a couple others, per grade).
        foreach ($grades as $grade) {
            foreach ($matrixSubjects as $subjId) {
                SchoolSubject::updateOrCreate(
                    ['school_id' => $school->id, 'subject_id' => $subjId, 'grade_id' => $grade->id],
                    ['is_active' => true]
                );
            }
        }

        // Teachers.
        $teachers = [];
        foreach ($cfg['teachers'] as $i => $tname) {
            $email = $this->teacherEmail($tname, $cfg['domain']);
            $teachers[$tname] = Teacher::updateOrCreate(
                ['email' => $email],
                ['school_id' => $school->id, 'name' => $tname, 'password' => self::PW,
                 'employee_id' => 'TCH'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                 'status' => 'active', 'must_change_password' => false, 'session_version' => 1]
            );
        }

        // Classes + students + exams.
        $rollSeq = 1;
        foreach ($cfg['classes'] as $cls) {
            $grade = $grades[$cls['grade']];
            $teacher = $teachers[$cls['teacher']];

            $class = SchoolClass::updateOrCreate(
                ['school_id' => $school->id, 'grade_id' => $grade->id, 'subject_id' => $physics->id,
                 'section' => substr($cls['name'], strpos($cls['name'], '-') + 1, 1)],
                ['name' => $cls['name'], 'is_active' => true]
            );

            ClassTeacher::updateOrCreate(
                ['class_id' => $class->id, 'teacher_id' => $teacher->id],
                ['school_id' => $school->id, 'is_primary' => true]
            );

            // Students enrolled in this class.
            $students = collect();
            for ($n = 0; $n < $cls['students']; $n++) {
                $roll = date('Y').'-'.str_pad((string) $rollSeq++, 3, '0', STR_PAD_LEFT);
                $student = Student::updateOrCreate(
                    ['school_id' => $school->id, 'roll_number' => $roll],
                    ['name' => $this->nextName(), 'email' => $roll.'@'.$cfg['domain'], 'password' => self::PW,
                     'grade' => $grade->short_name, 'status' => 'active', 'must_change_password' => false, 'session_version' => 1]
                );
                StudentEnrollment::updateOrCreate(
                    ['student_id' => $student->id, 'class_id' => $class->id],
                    ['school_id' => $school->id, 'status' => 'active']
                );
                $students->push($student);
            }

            // Exams for this class (single-topic + mixed), generated via ExamService.
            foreach ($cls['exams'] as $spec) {
                $topicId = $spec['topic'] ? optional($topicsByTitle[$spec['topic']] ?? null)->id : null;

                $exam = Exam::where('class_id', $class->id)->where('title', $spec['title'])->first();
                if (! $exam) {
                    $exam = $svc->generate($teacher, [
                        'class_id' => $class->id, 'topic_id' => $topicId,
                        'question_count' => $spec['count'], 'title' => $spec['title'],
                        'duration_minutes' => $spec['dur'], 'year_from' => 2018, 'year_to' => 2024,
                    ]);
                }

                $this->seedAttempts($exam, $students, $svc);
            }
        }
    }

    /** Mark attempts with topic-aware, deterministic answers so per-topic stats are realistic. */
    private function seedAttempts(Exam $exam, $students, ExamService $svc): void
    {
        $exam->loadMissing('examQuestions.question:id,topic_id,correct_answer');

        foreach ($students as $student) {
            // ~85% of students attempted (deterministic per student+exam).
            if ($this->rand($student->id, $exam->id, 1) > 0.85) {
                continue;
            }

            $attempt = $svc->startAttempt($exam, $student);
            if ($attempt->status === 'submitted') {
                continue; // already seeded — idempotent
            }

            [$ability, $affinity] = $this->profile($student->id);

            $responses = [];
            foreach ($exam->examQuestions as $eq) {
                $q = $eq->question;
                if (! $q) {
                    continue;
                }
                $p = max(0.05, min(0.97, $ability + ($affinity[$q->topic_id] ?? 0)));
                $correct = $q->correct_answer && $this->rand($student->id, $q->id, $exam->id, 2) <= $p;
                if ($correct) {
                    $responses[$eq->question_id] = $q->correct_answer;
                } else {
                    $wrong = array_values(array_diff(['A', 'B', 'C', 'D'], [$q->correct_answer]));
                    $responses[$eq->question_id] = $wrong[(int) floor($this->rand($student->id, $q->id, 3) * count($wrong))];
                }
            }

            $svc->submit($attempt, $responses);

            // Backdate over the last ~4 weeks so the data has a timeline.
            $submitted = Carbon::now()->subDays((int) floor($this->rand($exam->id, $student->id, 4) * 27) + 1)
                ->subMinutes((int) floor($this->rand($exam->id, $student->id, 5) * 540));
            $attempt->update([
                'started_at'   => $submitted->copy()->subMinutes((int) floor($this->rand($exam->id, $student->id, 6) * 30) + 8),
                'submitted_at' => $submitted,
            ]);
        }
    }

    /** Deterministic per-student ability (0.45–0.88) + per-topic affinity vector (−0.22…+0.18). */
    private function profile(int $studentId): array
    {
        $ability = 0.45 + $this->rand($studentId, 100) * 0.43;
        $affinity = [];
        foreach ($this->physicsTopicIds as $tid) {
            $affinity[$tid] = $this->rand($studentId, $tid, 200) * 0.40 - 0.22;
        }

        return [$ability, $affinity];
    }

    /** Deterministic pseudo-random in [0,1) from integer parts (FNV-1a hash) — stable across runs. */
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
        $name = $this->names[$this->nameCursor % count($this->names)];
        $this->nameCursor++;

        return $name;
    }

    private function teacherEmail(string $name, string $domain): string
    {
        $first = strtolower(explode(' ', str_replace(['Mr. ', 'Ms. ', 'Mrs. '], '', $name))[0]);

        return $first.'@'.$domain;
    }
}
