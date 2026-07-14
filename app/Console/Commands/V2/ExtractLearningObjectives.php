<?php

namespace App\Console\Commands\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\SyllabusObjectiveParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:extract-learning-objectives
|--------------------------------------------------------------------------
| Parses a registered syllabus source's extracted text into numbered learning
| objectives and upserts them as status 'extracted' (UNTRUSTED - equations
| arrive linearized by PDF text extraction and MUST be human-reviewed via
| v2:apply-lo-review before any authoring use).
|
|   php artisan v2:extract-learning-objectives --source=5054@2023-2025
|
| Safe to re-run: rows are keyed by (source, section_code, objective_number);
| rows already 'validated' or 'rejected' are NEVER overwritten (counted as
| skipped). Also writes the official structure map (topics + all sections,
| including intermediate headers) onto the source row, and maps each leaf
| section onto the existing v2_subtopics skeleton by external_id - the
| skeleton itself is never modified.
*/
class ExtractLearningObjectives extends Command
{
    protected $signature = 'v2:extract-learning-objectives
        {--source= : Syllabus source reference, e.g. 5054@2023-2025}
        {--dry-run : Parse and report without writing anything}';

    protected $description = 'Parse a syllabus source text into learning objectives (status: extracted, untrusted)';

    public function handle(): int
    {
        $source = $this->option('source') ? SyllabusSource::resolveReference($this->option('source')) : null;
        if (! $source) {
            $this->error('Pass --source=<code>@<version> for a registered source (v2:register-syllabus-source).');

            return self::FAILURE;
        }

        if (! $source->extracted_text_path) {
            $this->error("Source {$source->reference()} has no extracted text. Re-register with --text=.");

            return self::FAILURE;
        }

        $textPath = is_file($source->extracted_text_path)
            ? $source->extracted_text_path
            : base_path($source->extracted_text_path);
        if (! is_file($textPath)) {
            $this->error("Extracted text missing on disk: {$source->extracted_text_path}");

            return self::FAILURE;
        }

        // Integrity: refuse to parse text that no longer matches the registered hash.
        $hash = hash_file('sha256', $textPath);
        if ($source->extracted_text_sha256 && $hash !== $source->extracted_text_sha256) {
            $this->error('Extracted text sha256 mismatch vs registration - re-register the source before extracting.');

            return self::FAILURE;
        }

        $parsed = app(SyllabusObjectiveParser::class)->parse(file_get_contents($textPath));

        $this->info(sprintf(
            'Parsed %d sections (%d leaf) / %d objectives from %s.',
            $parsed['totals']['sections'],
            $parsed['totals']['leaf_sections'],
            $parsed['totals']['objectives'],
            $source->extracted_text_path,
        ));

        foreach ($parsed['warnings'] as $warning) {
            $this->warn("parser: {$warning}");
        }

        // Application mapping: official leaf code -> existing v2_subtopics row
        // (external_id within this subject). The skeleton is read, never written.
        $subtopicIds = Subtopic::query()
            ->join('v2_topics', 'v2_topics.id', '=', 'v2_subtopics.topic_id')
            ->where('v2_topics.subject_id', $source->subject_id)
            ->pluck('v2_subtopics.id', 'v2_subtopics.external_id');

        $stats = ['created' => 0, 'updated' => 0, 'skipped_reviewed' => 0, 'unmapped_sections' => 0];
        $unmapped = [];

        if ($this->option('dry-run')) {
            $this->line('Dry run - nothing written.');
            $this->renderSectionTable($parsed, $subtopicIds);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($source, $parsed, $subtopicIds, &$stats, &$unmapped) {
            foreach ($parsed['sections'] as $section) {
                if (! $section['leaf']) {
                    continue;
                }

                $subtopicId = $subtopicIds[$section['code']] ?? null;
                if ($subtopicId === null) {
                    $stats['unmapped_sections']++;
                    $unmapped[] = $section['code'];
                }

                foreach ($section['objectives'] as $objective) {
                    $existing = LearningObjective::where('syllabus_source_id', $source->id)
                        ->where('section_code', $section['code'])
                        ->where('objective_number', $objective['number'])
                        ->first();

                    // Human review is authoritative: never clobber validated/rejected rows.
                    if ($existing && $existing->status !== LearningObjective::STATUS_EXTRACTED) {
                        $stats['skipped_reviewed']++;

                        continue;
                    }

                    $raw = [
                        'text' => $objective['text'],
                        'sub_items' => $objective['sub_items'],
                    ];

                    $values = [
                        'subtopic_id' => $subtopicId,
                        'topic_number' => $section['topic'],
                        'section_title' => $section['title'],
                        'text' => $objective['text'],
                        'sub_items' => $objective['sub_items'] ?: null,
                        'raw_extract' => $raw,
                        'provenance' => [
                            'pdf_pages' => $objective['pdf_pages'],
                            'parser' => SyllabusObjectiveParser::class.' v1',
                            'text_sha256' => $source->extracted_text_sha256,
                        ],
                        'status' => LearningObjective::STATUS_EXTRACTED,
                    ];

                    if ($existing) {
                        $existing->update($values);
                        $stats['updated']++;
                    } else {
                        LearningObjective::create($values + [
                            'syllabus_source_id' => $source->id,
                            'section_code' => $section['code'],
                            'objective_number' => $objective['number'],
                        ]);
                        $stats['created']++;
                    }
                }
            }

            // Official structure map (incl. the 14 intermediate headers the flat
            // v2_subtopics tree cannot name) lives on the source row.
            $source->update([
                'structure_json' => [
                    'topics' => $parsed['topics'],
                    'sections' => collect($parsed['sections'])->map(fn ($s) => [
                        'code' => $s['code'],
                        'title' => $s['title'],
                        'topic' => $s['topic'],
                        'leaf' => $s['leaf'],
                        'lo_count' => $s['lo_count'],
                    ])->all(),
                ],
            ]);
        });

        $this->renderSectionTable($parsed, $subtopicIds);
        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->all());

        if ($unmapped) {
            $this->warn('Leaf sections without a matching v2_subtopics external_id (LOs stored with subtopic_id = NULL): '
                .implode(', ', $unmapped));
        }

        $this->warn('Extracted rows are UNTRUSTED. Review and promote them with v2:apply-lo-review before authoring use.');

        return self::SUCCESS;
    }

    private function renderSectionTable(array $parsed, $subtopicIds): void
    {
        $rows = collect($parsed['sections'])
            ->where('leaf', true)
            ->map(fn ($s) => [
                $s['code'],
                $s['title'],
                $s['lo_count'],
                isset($subtopicIds[$s['code']]) ? "#{$subtopicIds[$s['code']]}" : 'UNMAPPED',
            ])
            ->all();

        $this->table(['section', 'title', 'LOs', 'v2_subtopic'], $rows);
    }
}
