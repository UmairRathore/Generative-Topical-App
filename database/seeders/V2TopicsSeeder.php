<?php

namespace Database\Seeders;

use App\Models\V2\Subject;
use App\Models\V2\Topic;
use App\Models\V2\Subtopic;
use Illuminate\Database\Seeder;

/*
|--------------------------------------------------------------------------
| V2TopicsSeeder
|--------------------------------------------------------------------------
| Seeds v2_topics + v2_subtopics for CAIE A Level Physics (9702) from the
| official syllabus JSON:
|   storage/syllabus/physics/Alevels/subject_content_a_level_physics.json
| Idempotent: keyed on (subject, external_id). Run V2SubjectsSeeder first.
*/
class V2TopicsSeeder extends Seeder
{
    public function run(): void
    {
        $subject = Subject::where('code', '9702')->first();

        if (! $subject) {
            $this->command->error('Subject 9702 not found. Run V2SubjectsSeeder first.');
            return;
        }

        $path = storage_path('syllabus/physics/Alevels/subject_content_a_level_physics.json');

        if (! is_file($path)) {
            $this->command->error("Syllabus file not found: {$path}");
            return;
        }

        $data = json_decode(file_get_contents($path), true);
        $topics = $data['topics'] ?? [];

        $topicCount = 0;
        $subtopicCount = 0;

        foreach ($topics as $i => $t) {
            $topic = Topic::updateOrCreate(
                ['subject_id' => $subject->id, 'external_id' => (string) $t['id']],
                [
                    'title'      => $t['title'] ?? '',
                    'level'      => $t['level'] ?? null,
                    'sort_order' => is_numeric($t['id']) ? (int) $t['id'] : ($i + 1),
                ]
            );
            $topicCount++;

            foreach (($t['subtopics'] ?? []) as $j => $s) {
                Subtopic::updateOrCreate(
                    ['topic_id' => $topic->id, 'external_id' => (string) $s['id']],
                    [
                        'title'      => $s['title'] ?? '',
                        'sort_order' => $j + 1,
                    ]
                );
                $subtopicCount++;
            }
        }

        $this->command->info("Seeded {$topicCount} topics and {$subtopicCount} subtopics for Physics 9702.");
    }
}
