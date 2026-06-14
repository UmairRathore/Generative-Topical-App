<?php

namespace App\Services\Syllabus;

use App\Models\SyllabusLearningObjective;
use App\Models\SyllabusSubtopic;
use App\Models\SyllabusTopic;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyllabusLoader
{
    public const DEFAULT_CODE = 'physics_alevel_9702';
    public const DEFAULT_PATH = 'syllabus/physics/Alevels/subject_content_a_level_physics.json';

    /**
     * Read and decode a syllabus JSON file. Throws on missing file or invalid shape.
     *
     * @return array{section:string,title:string,source:string,topics:array<int,array<string,mixed>>}
     */
    public function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Syllabus JSON not found: {$path}");
        }
        $raw = file_get_contents($path);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded) || ! isset($decoded['topics']) || ! is_array($decoded['topics'])) {
            throw new RuntimeException("Syllabus JSON is invalid (missing topics): {$path}");
        }
        return $decoded;
    }

    /**
     * Resolve a syllabus path: absolute, project-relative, or storage-relative.
     */
    public function resolvePath(?string $path): string
    {
        $path = $path ?: self::DEFAULT_PATH;
        if (preg_match('#^([a-zA-Z]:[\\\\/]|/)#', $path) === 1 && is_file($path)) {
            return $path;
        }
        $candidates = [
            base_path($path),
            storage_path($path),
            storage_path('app/'.$path),
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return $path;
    }

    /**
     * Sync the syllabus JSON into the syllabus_* tables. Idempotent.
     */
    public function sync(string $path, string $syllabusCode = self::DEFAULT_CODE): array
    {
        $data = $this->readJson($path);

        $stats = ['topics' => 0, 'subtopics' => 0, 'objectives' => 0];

        DB::transaction(function () use ($data, $syllabusCode, &$stats) {
            $topicSort = 0;
            foreach ($data['topics'] as $topicData) {
                $topicSort++;
                $topic = SyllabusTopic::updateOrCreate(
                    [
                        'syllabus_code' => $syllabusCode,
                        'external_id' => (string) $topicData['id'],
                    ],
                    [
                        'title' => (string) $topicData['title'],
                        'level' => (string) ($topicData['level'] ?? 'AS'),
                        'intro_note' => $topicData['intro_note'] ?? null,
                        'sort_order' => $topicSort,
                        'raw_json' => $topicData,
                    ]
                );
                $stats['topics']++;

                $subSort = 0;
                foreach ($topicData['subtopics'] ?? [] as $subData) {
                    $subSort++;
                    $sub = SyllabusSubtopic::updateOrCreate(
                        [
                            'syllabus_topic_id' => $topic->id,
                            'external_id' => (string) $subData['id'],
                        ],
                        [
                            'title' => (string) $subData['title'],
                            'sort_order' => $subSort,
                            'raw_json' => $subData,
                        ]
                    );
                    $stats['subtopics']++;

                    foreach ($subData['learning_objectives'] ?? [] as $loData) {
                        SyllabusLearningObjective::updateOrCreate(
                            [
                                'syllabus_subtopic_id' => $sub->id,
                                'objective_number' => (int) $loData['id'],
                            ],
                            [
                                'text' => (string) $loData['text'],
                                'sub_items' => $loData['sub_items'] ?? null,
                                'raw_json' => $loData,
                            ]
                        );
                        $stats['objectives']++;
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * Build a flat in-memory index of the syllabus for the classifier.
     * Each row: ['topic_id','topic_title','level','subtopic_id','subtopic_title','objective_number','objective_text','sub_items']
     *
     * @return array<int,array<string,mixed>>
     */
    public function flattenFromArray(array $data): array
    {
        $rows = [];
        foreach ($data['topics'] ?? [] as $topic) {
            foreach ($topic['subtopics'] ?? [] as $sub) {
                $objectives = $sub['learning_objectives'] ?? [];
                if (empty($objectives)) {
                    $rows[] = [
                        'topic_id' => (string) $topic['id'],
                        'topic_title' => (string) $topic['title'],
                        'level' => (string) ($topic['level'] ?? 'AS'),
                        'subtopic_id' => (string) $sub['id'],
                        'subtopic_title' => (string) $sub['title'],
                        'objective_number' => null,
                        'objective_text' => null,
                        'sub_items' => null,
                    ];
                    continue;
                }
                foreach ($objectives as $lo) {
                    $rows[] = [
                        'topic_id' => (string) $topic['id'],
                        'topic_title' => (string) $topic['title'],
                        'level' => (string) ($topic['level'] ?? 'AS'),
                        'subtopic_id' => (string) $sub['id'],
                        'subtopic_title' => (string) $sub['title'],
                        'objective_number' => (int) $lo['id'],
                        'objective_text' => (string) $lo['text'],
                        'sub_items' => $lo['sub_items'] ?? null,
                    ];
                }
            }
        }
        return $rows;
    }
}
