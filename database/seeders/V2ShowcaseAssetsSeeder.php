<?php

namespace Database\Seeders;

use App\Models\V2\Exam;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| V2ShowcaseAssetsSeeder - register the bespoke Biology showcase pages as
| real learning assets, mapped onto the actual bank questions
|--------------------------------------------------------------------------
| The two hand-built showcase blades (temp/photosynthesis-showcase and
| temp/biology-showcase) had rich worked solutions, flashcards, memcards,
| mermaid flows and a simulator that lived ONLY in the blade - not in the
| DB. This ports that content into v2_question_learning_assets against the
| exact matching questions, so it surfaces in the Learning Hub / Notes like
| any other asset:
|   * 5090 w19 qp_11 Q6 (id 15294, O-Level Biology) - lake / limiting factors
|     -> incl. a LIVE photosynthesis_lake widget (that type is registered)
|   * 9700 w15 qp_12 Q39 (id 18487, A-Level Biology) - energy flow / GPP
|     (energy_flow_sankey isn't built yet, so no widget - text assets only)
|
| Every asset is status=approved, generated_by='demo_showcase',
| reviewed_by='notes-demo-seeder' (same marker as V2NotesDemoSeeder, so one
| revert clears both). It also attaches each to Hira Bukhari (student 86) as
| a released mistake so it's demoable end to end.
|
| REVERT:
|   // 1. delete the NEW showcase rows (biology assets + the 3D plank widget)
|   QuestionLearningAsset::where('generated_by','demo_showcase')->delete();
|   // 2. restore q373's calculator widget that registerPlank() flipped to draft
|   QuestionLearningAsset::where('question_id',373)
|       ->where('asset_type','interactive_widget')->where('asset_key','calculator')
|       ->update(['status'=>'approved','reviewed_by'=>'notes-demo-seeder',
|                 'reviewed_at'=>now(),'approved_at'=>now()]);
*/
class V2ShowcaseAssetsSeeder extends Seeder
{
    private const MARKER = 'notes-demo-seeder';
    private const STUDENT_ID = 86; // Hira Bukhari (O-Level, biology showcases)
    private const PHYSICS_STUDENT_ID = 1; // Ali Hassan (AS-A Physics @ Sage, 9702 showcase)
    private const LAKE_QID = 15294;
    private const ENERGY_QID = 18487;
    private const PLANK_QID = 373; // 9702 m25 qp_12 Q13 - the 3D plank showcase

    public function run(): void
    {
        $this->registerLake();
        $this->registerEnergy();
        $this->registerPlank();

        $svc = app(ExamService::class);
        // O-Level Biology (class 9) - Hira already enrolled; A-Level Biology
        // (class 10) - enroll her so the 9700 question can be examined.
        $this->examFor($svc, 9, self::LAKE_QID, 'O Level Biology - Showcase Demo', self::STUDENT_ID);
        $this->examFor($svc, 10, self::ENERGY_QID, 'A Level Biology - Showcase Demo', self::STUDENT_ID);
        // A-Level Physics (class 1, AS-A Physics @ Sage) - the 3D plank, for Ali.
        $this->examFor($svc, 1, self::PLANK_QID, 'A Level Physics - Showcase Demo', self::PHYSICS_STUDENT_ID);

        $this->command->info('Showcase assets registered on q'.self::LAKE_QID.', q'.self::ENERGY_QID.', q'.self::PLANK_QID.'.');
    }

    /* ===================== 9702 - 3D plank / moments ===================== */

    private function registerPlank(): void
    {
        $qid = self::PLANK_QID;

        // q373 already has real pipeline assets (worked solution, cards, mermaid,
        // option explanation) approved - we DON'T touch those. Its only
        // interactive_widget slot is a DRAFT 'calculator' that V2NotesDemoSeeder
        // flipped to approved; revert THAT flip (restore its original draft
        // state) so our new 3D widget becomes the sole visible interactive_widget.
        QuestionLearningAsset::where('question_id', $qid)
            ->where('asset_type', 'interactive_widget')
            ->where('asset_key', 'calculator')
            ->where('reviewed_by', self::MARKER)
            ->update(['status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null, 'approved_at' => null]);

        // The genuine Three.js plank simulator, as a NEW keyed row (no collision
        // with the calculator; no existing asset overwritten).
        QuestionLearningAsset::updateOrCreate(
            ['question_id' => $qid, 'asset_type' => 'interactive_widget', 'asset_key' => 'plank_moments_3d'],
            [
                'title' => 'Balancing the plank — moments in 3D',
                'payload_json' => [
                    'widget' => 'plank_moments_3d',
                    'config' => ['length' => 4.0, 'plankWeight' => 300, 'childWeight' => 600, 'supports' => ['X', 'Y'], 'd' => 0],
                ],
                'format' => 'json', 'status' => 'approved', 'generated_by' => 'demo_showcase',
                'reviewed_by' => self::MARKER, 'reviewed_at' => now(), 'approved_at' => now(),
            ],
        );
    }

    /* ===================== 5090 - lake / limiting factors ===================== */

    private function registerLake(): void
    {
        $qid = self::LAKE_QID;

        $this->put($qid, 'worked_solution', 'Trace the light, not the gases', <<<'MD'
## 1. What actually changed?
Rain stirs up mud (the water turns **turbid**) and raises the level (the plants are now **deeper**). Neither adds or removes carbon dioxide, nitrates or oxygen in a way that matters here.

## 2. How each change affects light
Suspended mud **scatters and absorbs** light; deeper water means light travels **further** before reaching the bed. Both reduce the **light intensity** that arrives at the plants.

## 3. Light becomes the limiting factor
On a bright day the plants are usually limited by CO₂ or temperature — light is plentiful. Cut the light enough and it becomes the **limiting factor**: the rate now rises and falls with light, so less light → slower photosynthesis.

## Why the distractors fail
- **A** extra CO₂ would *raise* the rate, not lower it.
- **B** nitrates affect protein/growth, not the immediate rate.
- **D** oxygen is a *product* — its concentration doesn't limit the reaction here.

**Answer: C — lower light intensity.**
MD, 'markdown');

        $this->putPayload($qid, 'option_explanation', 'Why each answer is right — or wrong', [
            'options' => [
                ['label' => 'A', 'text' => 'extra carbon dioxide', 'why' => 'Extra CO₂ would raise the rate, not lower it — and turbidity adds none.'],
                ['label' => 'B', 'text' => 'extra dissolved nitrates', 'why' => 'Nitrates affect protein/growth, not the immediate rate of photosynthesis.'],
                ['label' => 'C', 'text' => 'lower light intensity', 'why' => '✓ correct', 'correct' => true],
                ['label' => 'D', 'text' => 'lower oxygen concentration', 'why' => 'Oxygen is a product, not a reactant, so its level doesn’t limit the rate.'],
            ],
        ]);

        $this->putPayload($qid, 'flashcards', 'Self-test the core ideas', [
            ['front' => 'Name the three main limiting factors of photosynthesis.', 'back' => 'Light intensity, carbon dioxide concentration, and temperature.'],
            ['front' => 'What is a "limiting factor"?', 'back' => 'The factor in shortest supply — it caps the rate. Increasing it raises the rate until another factor runs short.'],
            ['front' => 'Why does stirred-up mud lower photosynthesis?', 'back' => 'Suspended particles scatter and absorb light, so less light reaches the plants — light becomes limiting.'],
            ['front' => 'Why does deeper water lower it too?', 'back' => 'Light is absorbed as it passes through water, so at greater depth less light intensity arrives at the bed.'],
            ['front' => "Why isn't the answer 'extra CO₂' (A)?", 'back' => 'More CO₂ would speed photosynthesis up, not slow it down — and turbidity doesn’t add CO₂.'],
            ['front' => "Why isn't it 'lower oxygen' (D)?", 'back' => 'Oxygen is a product of photosynthesis, not a reactant, so its level doesn’t limit the rate here.'],
        ]);

        $this->putPayload($qid, 'memcards', 'The facts behind it', [
            ['front' => 'Law of limiting factors', 'back' => 'The rate of a process is limited by the factor in shortest supply. (rate ← slowest factor)'],
            ['front' => 'Light intensity', 'back' => 'Provides energy for the light-dependent stage. Below saturation, rate rises with light (to a plateau).'],
            ['front' => 'Carbon dioxide', 'back' => 'A raw material fixed in photosynthesis; often limiting on a bright day. 6CO₂ + 6H₂O → C₆H₁₂O₆ + 6O₂.'],
            ['front' => 'Temperature', 'back' => 'Enzyme-controlled: rises to an optimum (~25–35 °C) then falls as enzymes denature.'],
            ['front' => 'Turbidity & depth cut light', 'back' => 'Suspended solids scatter/absorb light; water absorbs it with depth — both lower intensity at the bed.'],
            ['front' => 'Bubbles measure rate', 'back' => 'Oxygen bubbles from pondweed are a classic measure of photosynthetic rate.'],
        ]);

        $this->put($qid, 'mermaid', 'From rainfall to slower photosynthesis', <<<'MMD'
flowchart TD
    A[Heavy rainfall] --> B[Mud stirred up = turbid water]
    A --> C[Water level rises = plants deeper]
    B --> D[Less light reaches the lake bed]
    C --> D
    D --> E{Is light now the limiting factor?}
    E -->|Yes, below saturation| F[Rate of photosynthesis falls]
    F --> G([Answer C: lower light intensity])
MMD, 'mermaid');

        // Live widget — photosynthesis_lake IS registered. Start murky (low
        // clarity) so it opens ON the exam scenario: light-limited.
        $this->putPayload($qid, 'interactive_widget', 'Rate-of-photosynthesis lake', [
            'widget' => 'photosynthesis_lake',
            'config' => ['sun' => 80, 'clarity' => 35, 'co2' => 70, 'temp' => 22],
        ]);
    }

    /* ===================== 9700 - energy flow / GPP ===================== */

    private function registerEnergy(): void
    {
        $qid = self::ENERGY_QID;

        $this->put($qid, 'worked_solution', 'Add respiration back to get GPP', <<<'MD'
## 1. Decode the wording
Energy "used for photosynthesis" is the **total** chemical energy the plant fixes — its **Gross Primary Productivity (GPP)**. That's more than the energy left stored, because some fixed energy is already burned in **respiration**.

## 2. Rebuild GPP from the diagram
Stored energy is the Net Primary Productivity (NPP = 27 000). Add back the respiratory loss (3 000):

`GPP = NPP + R = 27 000 + 3 000 = 30 000 kJ m⁻² y⁻¹`

## 3. Express as a percentage of sunlight
`% = GPP ÷ sunlight × 100 = 30 000 ÷ (1 × 10⁶) × 100 = 3.00%`

## Why the traps catch people
- **C (2.70%)** uses NPP alone (27 000) and forgets respiration — the classic slip.
- **B (0.30%)** divides only the 3 000 loss by sunlight.
- **A (0.03%)** is a power-of-ten error.

**Answer: D — 3.00%.**
MD, 'markdown');

        $this->putPayload($qid, 'option_explanation', 'Why each answer is right — or wrong', [
            'options' => [
                ['label' => 'A', 'text' => '0.03%', 'why' => 'A power-of-ten slip in the division.'],
                ['label' => 'B', 'text' => '0.30%', 'why' => 'Used only the 3 000 respiratory loss over the sunlight.'],
                ['label' => 'C', 'text' => '2.70%', 'why' => 'Used NPP (27 000) alone and forgot to add respiration back.'],
                ['label' => 'D', 'text' => '3.00%', 'why' => '✓ correct — GPP ÷ sunlight', 'correct' => true],
            ],
        ]);

        $this->putPayload($qid, 'flashcards', 'Self-test the core ideas', [
            ['front' => 'What does "energy used for photosynthesis" mean?', 'back' => 'Gross Primary Productivity (GPP) — the TOTAL chemical energy fixed by the plant before any is respired.'],
            ['front' => 'Formula linking GPP, NPP and respiration?', 'back' => 'GPP = NPP + R. Energy fixed = energy stored + energy respired.'],
            ['front' => 'Plug in the numbers for GPP.', 'back' => 'GPP = 27 000 + 3 000 = 30 000 kJ m⁻² y⁻¹.'],
            ['front' => 'Now the percentage of sunlight?', 'back' => '30 000 ÷ (1 × 10⁶) × 100 = 3.00% → option D.'],
            ['front' => 'Why is C (2.70%) wrong?', 'back' => 'It uses NPP (27 000) alone and forgets to add back the 3 000 lost to respiration.'],
            ['front' => 'Why is photosynthetic efficiency only ~3%?', 'back' => 'Most sunlight is reflected, is the wrong wavelength, passes through the leaf, or misses the chloroplasts.'],
        ]);

        $this->putPayload($qid, 'memcards', 'The facts behind it', [
            ['front' => 'Gross Primary Productivity', 'back' => 'Total rate at which producers fix chemical energy by photosynthesis. GPP = total energy fixed.'],
            ['front' => 'Net Primary Productivity', 'back' => 'Energy left in the plant after respiration — available to consumers. NPP = GPP − R.'],
            ['front' => 'Add respiration back', 'back' => 'Respired energy was fixed by photosynthesis first, so it counts toward GPP. GPP = NPP + R.'],
            ['front' => 'Low light-use efficiency', 'back' => 'Only ~1–3% of incident sunlight is fixed: reflection, wrong wavelength, transmission, missed chloroplasts.'],
            ['front' => '~10% transfer', 'back' => 'Roughly 10% of energy passes to the next trophic level; the rest is lost.'],
            ['front' => 'Units', 'back' => 'Productivity is a rate per unit area per time — watch the units: kJ m⁻² y⁻¹.'],
        ]);

        $this->put($qid, 'mermaid', 'Where the sunlight goes', <<<'MMD'
flowchart TD
    S["Sunlight 1 x 10^6 kJ"] --> A{Captured by chlorophyll?}
    A -->|"No, ~97% reflected / wrong wavelength / transmitted"| L[Energy not used]
    A -->|"Yes, 3% fixed"| G["GPP = 30 000 kJ (photosynthesis)"]
    G --> R["Respiration 3 000 kJ"]
    G --> N["NPP stored 27 000 kJ"]
    N --> H[Herbivore]
    H --> C[Carnivore]
MMD, 'mermaid');
    }

    /* ============================ helpers ============================ */

    /** Text/markdown/mermaid asset (content column). */
    private function put(int $qid, string $type, string $title, string $content, string $format): void
    {
        QuestionLearningAsset::updateOrCreate(
            ['question_id' => $qid, 'asset_type' => $type, 'asset_key' => ''],
            [
                'title' => $title, 'content' => $content, 'format' => $format,
                'status' => 'approved', 'generated_by' => 'demo_showcase',
                'reviewed_by' => self::MARKER, 'reviewed_at' => now(), 'approved_at' => now(),
            ],
        );
    }

    /** Structured asset (payload_json column). */
    private function putPayload(int $qid, string $type, string $title, array $payload): void
    {
        QuestionLearningAsset::updateOrCreate(
            ['question_id' => $qid, 'asset_type' => $type, 'asset_key' => ''],
            [
                'title' => $title, 'payload_json' => $payload, 'format' => 'json',
                'status' => 'approved', 'generated_by' => 'demo_showcase',
                'reviewed_by' => self::MARKER, 'reviewed_at' => now(), 'approved_at' => now(),
            ],
        );
    }

    /** Enroll the student in the class (if needed), build a released 1-question exam, mark it wrong. */
    private function examFor(ExamService $svc, int $classId, int $qid, string $title, int $studentId): void
    {
        $student = Student::withoutGlobalScopes()->findOrFail($studentId);
        // Reuse the branch from any existing enrollment so we don't assume a column.
        $branchId = DB::table('v2_student_enrollments')->where('student_id', $student->id)->value('branch_id');

        StudentEnrollment::firstOrCreate(
            ['student_id' => $student->id, 'class_id' => $classId],
            ['school_id' => $student->school_id, 'branch_id' => $branchId, 'status' => 'active'],
        );

        $teacherId = DB::table('v2_class_teachers')->where('class_id', $classId)
            ->orderByDesc('is_primary')->value('teacher_id') ?? 1;
        $teacher = Teacher::withoutGlobalScopes()->findOrFail($teacherId);

        $exam = Exam::withoutGlobalScopes()->where('class_id', $classId)->where('title', $title)->first();
        if (! $exam) {
            $exam = $svc->createFromQuestions($teacher, [
                'class_id' => $classId, 'title' => $title, 'duration_minutes' => 15,
            ], [$qid]);
        }
        $exam->forceFill([
            'status' => 'released', 'available_from' => now()->subDay(),
            'available_until' => null, 'results_released_at' => now()->subHour(),
        ])->save();

        $attempt = $svc->startAttempt($exam, $student);
        if ($attempt->status !== 'submitted') {
            $exam->loadMissing('examQuestions.question:id,correct_answer');
            $responses = [];
            foreach ($exam->examQuestions as $eq) {
                $correct = $eq->question?->correct_answer ?: 'A';
                $responses[$eq->question_id] = collect(['A', 'B', 'C', 'D'])->first(fn ($o) => $o !== $correct);
            }
            $svc->submit($attempt, $responses);
        }
    }
}
