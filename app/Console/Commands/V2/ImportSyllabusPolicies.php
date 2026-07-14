<?php

namespace App\Console\Commands\V2;

use App\Models\V2\SyllabusPolicy;
use App\Models\V2\SyllabusSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:import-syllabus-policies
|--------------------------------------------------------------------------
| Imports curated syllabus-level authoring policies (command words, symbols &
| units, mathematical requirements, Language of Measurement, graph/data
| conventions, nomenclature, assessment model) from JSON files curated FROM
| the canonical source pages.
|
|   php artisan v2:import-syllabus-policies --source=5054@2023-2025 \
|       --path=storage/syllabus/physics/OLevels/5054_2023_2025/policies
|
| Policies land as 'extracted' (untrusted). Pass --validate ONLY after a human
| has checked each file against the source PDF; that promotes rows to
| 'validated' and records reviewed_by from each file. Files whose rows are
| already validated are skipped unless --revalidate is passed.
|
| Policy file shape:
| { "policy_type": "command_words", "title": "...", "reviewed_by": "umair",
|   "provenance": {"pdf_pages": [46]}, "content": {...} }
*/
class ImportSyllabusPolicies extends Command
{
    protected $signature = 'v2:import-syllabus-policies
        {--source= : Syllabus source reference, e.g. 5054@2023-2025}
        {--path= : Directory containing policy JSON files}
        {--validate : Promote imported rows to validated (only after human review)}
        {--revalidate : Allow overwriting rows that are already validated}';

    protected $description = 'Import curated syllabus authoring policies (command words, units, conventions...)';

    public function handle(): int
    {
        $source = $this->option('source') ? SyllabusSource::resolveReference($this->option('source')) : null;
        if (! $source) {
            $this->error('Pass --source=<code>@<version> for a registered source.');

            return self::FAILURE;
        }

        $dir = $this->option('path');
        $dir = is_dir($dir) ? $dir : base_path((string) $dir);
        if (! $this->option('path') || ! is_dir($dir)) {
            $this->error("Policy directory not found: {$this->option('path')}");

            return self::FAILURE;
        }

        $files = glob(rtrim($dir, '/\\').'/*.json');
        if (! $files) {
            $this->error("No *.json policy files in {$dir}.");

            return self::FAILURE;
        }

        $rows = [];
        $stats = ['imported' => 0, 'validated' => 0, 'skipped_validated' => 0, 'invalid' => 0];

        DB::transaction(function () use ($files, $source, &$rows, &$stats) {
            foreach ($files as $file) {
                $policy = json_decode(file_get_contents($file), true);
                $type = $policy['policy_type'] ?? null;

                if (! is_array($policy) || ! in_array($type, SyllabusPolicy::ALLOWED_TYPES, true)
                    || empty($policy['title']) || empty($policy['content'])) {
                    $stats['invalid']++;
                    $rows[] = [basename($file), $type ?? '?', 'INVALID (needs policy_type/title/content)'];

                    continue;
                }

                if ($this->option('validate') && empty($policy['reviewed_by'])) {
                    $stats['invalid']++;
                    $rows[] = [basename($file), $type, 'INVALID (--validate requires reviewed_by in file)'];

                    continue;
                }

                $existing = SyllabusPolicy::where('syllabus_source_id', $source->id)
                    ->where('policy_type', $type)
                    ->first();

                if ($existing && $existing->status === SyllabusPolicy::STATUS_VALIDATED && ! $this->option('revalidate')) {
                    $stats['skipped_validated']++;
                    $rows[] = [basename($file), $type, 'skipped (already validated; use --revalidate)'];

                    continue;
                }

                $validate = (bool) $this->option('validate');
                $provenance = ($policy['provenance'] ?? []) + [
                    'curated_from' => basename($source->source_pdf_path).'@'.$source->source_pdf_sha256,
                    'file' => basename($file).'@'.hash_file('sha256', $file),
                ];

                SyllabusPolicy::updateOrCreate(
                    ['syllabus_source_id' => $source->id, 'policy_type' => $type],
                    [
                        'title' => $policy['title'],
                        'content' => $policy['content'],
                        'provenance' => $provenance,
                        'status' => $validate ? SyllabusPolicy::STATUS_VALIDATED : SyllabusPolicy::STATUS_EXTRACTED,
                        'review_note' => $policy['review_note'] ?? null,
                        'validated_by' => $validate ? $policy['reviewed_by'] : null,
                        'validated_at' => $validate ? now() : null,
                    ]
                );

                $stats[$validate ? 'validated' : 'imported']++;
                $rows[] = [basename($file), $type, $validate ? 'validated' : 'extracted (untrusted)'];
            }
        });

        $this->table(['file', 'policy_type', 'result'], $rows);
        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->all());

        if (! $this->option('validate')) {
            $this->warn('Policies imported as UNTRUSTED. After checking each file against the source PDF, re-run with --validate.');
        }

        return $stats['invalid'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
