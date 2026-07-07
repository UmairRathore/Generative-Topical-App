<?php

namespace Database\Seeders;

use App\Models\V2\Exam;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Student;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| V2NotesDemoSeeder - Learning Hub Notes demo (PHYSICS ONLY)
|--------------------------------------------------------------------------
| Sets up a realistic path for demoing "add to notes" end-to-end:
|   1. Approves the DRAFT learning assets of 12 handpicked full-stack 5054
|      O-Level Physics questions (2010-2012, one per widget archetype) plus
|      the plank showcase question's draft simulator (9702 m25 Q13, id 373).
|      STATUS FLIP ONLY - asset content is never touched. Every flipped row
|      is marked reviewed_by = 'notes-demo-seeder'.
|   2. Creates a released exam for class 11 (O-A Physics @ Sage) through the
|      real ExamService (question versions frozen like any teacher exam).
|   3. Submits an attempt for Hira Bukhari (student 86) answering every
|      question WRONG, so each question lands in her Mistake Bank.
|
| Idempotent: re-running approves nothing new, reuses the exam, skips the
| submitted attempt.
|
| REVERT the asset approvals with:
|   QuestionLearningAsset::where('reviewed_by', 'notes-demo-seeder')
|       ->update(['status' => 'draft', 'reviewed_by' => null,
|                 'reviewed_at' => null, 'approved_at' => null]);
*/
class V2NotesDemoSeeder extends Seeder
{
    /** Handpicked full-stack 5054 questions (2010-2012), one per widget archetype:
     *  graph_regions, graph_explorer, calculator, heat_transfer, scene, dc_motor,
     *  moments_beam, pressure_area, ray_diagram, ac_generator, circuit_network, transformer. */
    private const QUESTION_IDS = [4336, 4337, 4340, 4342, 4347, 4360, 4374, 4417, 4428, 4443, 4516, 4523];

    /** The physics showcase plank question (9702 m25 Q13) - its simulator is still draft. */
    private const PLANK_ID = 373;

    private const CLASS_ID = 11;   // O-A Physics @ The Sage
    private const STUDENT_ID = 86; // Hira Bukhari
    private const MARKER = 'notes-demo-seeder';
    private const TITLE = 'O Level Physics - Learning Hub Demo';

    public function run(): void
    {
        // 1. Approve draft assets for the demo questions (status flip only, reversible).
        $flipped = QuestionLearningAsset::whereIn('question_id', [...self::QUESTION_IDS, self::PLANK_ID])
            ->where('status', 'draft')
            ->update([
                'status'      => 'approved',
                'reviewed_by' => self::MARKER,
                'reviewed_at' => now(),
                'approved_at' => now(),
            ]);
        $this->command->info("Approved {$flipped} draft assets (marker: ".self::MARKER.').');

        // 2. The demo exam - the real service path, so question versions freeze normally.
        $svc = app(ExamService::class);
        $teacherId = DB::table('v2_class_teachers')->where('class_id', self::CLASS_ID)
            ->orderByDesc('is_primary')->value('teacher_id') ?? 1;
        $teacher = Teacher::withoutGlobalScopes()->findOrFail($teacherId);

        $exam = Exam::withoutGlobalScopes()
            ->where('class_id', self::CLASS_ID)->where('title', self::TITLE)->first();
        if (! $exam) {
            $exam = $svc->createFromQuestions($teacher, [
                'class_id'         => self::CLASS_ID,
                'title'            => self::TITLE,
                'duration_minutes' => 30,
            ], self::QUESTION_IDS);
            $this->command->info("Created exam #{$exam->id} with {$exam->question_count} questions.");
        }

        $exam->forceFill([
            'status'              => 'released',
            'available_from'      => now()->subDay(),
            'available_until'     => null,
            'results_released_at' => now()->subHour(),
        ])->save();

        // 3. Hira answers everything wrong -> every question enters her Mistake Bank
        //    (ExamService::submit runs the mistake-capture sync itself).
        $student = Student::withoutGlobalScopes()->findOrFail(self::STUDENT_ID);
        $attempt = $svc->startAttempt($exam, $student);
        if ($attempt->status !== 'submitted') {
            $exam->loadMissing('examQuestions.question:id,correct_answer');
            $responses = [];
            foreach ($exam->examQuestions as $eq) {
                $correct = $eq->question?->correct_answer ?: 'A';
                $responses[$eq->question_id] = collect(['A', 'B', 'C', 'D'])->first(fn ($o) => $o !== $correct);
            }
            $svc->submit($attempt, $responses);
            $this->command->info('Submitted an all-wrong attempt for '.$student->name.'.');
        }

        $this->command->info('Demo ready → login '.$student->email.' / password → Learning Hub.');
    }
}
