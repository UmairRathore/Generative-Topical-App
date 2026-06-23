<?php

namespace Database\Seeders;

use App\Models\V2\Subject;
use App\Models\V2\Topic;
use Illuminate\Database\Seeder;

/*
|--------------------------------------------------------------------------
| V2BiologyTopicsSeeder — CAIE O-Level Biology 5090 topic taxonomy
|--------------------------------------------------------------------------
| The 19 top-level subject-content topics of the Cambridge O Level Biology
| 5090 syllabus (2023–2025, derived from 5090_y25_sy.pdf), used to topic-tag
| the imported 5090 question bank. Idempotent (keyed on subject + external_id).
*/
class V2BiologyTopicsSeeder extends Seeder
{
    /** external_id => title (external_id is the syllabus topic number). */
    private const TOPICS = [
        '1'  => 'Cells',
        '2'  => 'Classification',
        '3'  => 'Movement into and out of cells',
        '4'  => 'Biological molecules',
        '5'  => 'Enzymes',
        '6'  => 'Plant nutrition',
        '7'  => 'Transport in flowering plants',
        '8'  => 'Human nutrition',
        '9'  => 'Human gas exchange',
        '10' => 'Respiration',
        '11' => 'Transport in humans',
        '12' => 'Disease and immunity',
        '13' => 'Excretion',
        '14' => 'Coordination and control',
        '15' => 'Coordination and response in plants',
        '16' => 'Development of organisms and continuity of life',
        '17' => 'Inheritance',
        '18' => 'Biotechnology and genetic modification',
        '19' => 'Relationships of organisms with one another and with the environment',
    ];

    public function run(): void
    {
        $subject = Subject::where('code', '5090')->first();
        if (! $subject) {
            $this->command->error('Biology subject (5090) not found. Run V2SubjectsSeeder first.');
            return;
        }

        $sort = 1;
        foreach (self::TOPICS as $externalId => $title) {
            Topic::updateOrCreate(
                ['subject_id' => $subject->id, 'external_id' => $externalId],
                ['title' => $title, 'level' => $subject->level, 'sort_order' => $sort++]
            );
        }

        $this->command->info('V2BiologyTopicsSeeder: ' . count(self::TOPICS) . ' Biology 5090 topics seeded.');
    }
}
