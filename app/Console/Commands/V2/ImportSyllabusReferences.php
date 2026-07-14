<?php

namespace App\Console\Commands\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\LearningObjectiveReference;
use App\Models\V2\SyllabusPolicy;
use App\Models\V2\SyllabusReference;
use App\Models\V2\SyllabusSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:import-syllabus-references
|--------------------------------------------------------------------------
| Imports the curated Canonical Syllabus Reference Library for a source:
| references.json (terms/quantities/definitions/relationships/constants)
| plus lo_mappings/<section>.json (LO -> reference/policy-item role rows).
|
|   php artisan v2:import-syllabus-references --source=5054@2023-2025 \
|       --path=storage/syllabus/physics/OLevels/5054_2023_2025/references --validate
|
| References land 'extracted' (untrusted); --validate promotes (requires
| reviewed_by in the file). Validated rows are never overwritten without
| --revalidate. Mapping files are declarative: existing mapping rows for each
| covered LO are replaced wholesale (idempotent re-import). Mappings are
| checked: reference handles must exist, policy item keys must exist in the
| validated policy content, roles must be in the allowed vocabulary, exactly
| one target per row.
*/
class ImportSyllabusReferences extends Command
{
    protected $signature = 'v2:import-syllabus-references
        {--source= : Syllabus source reference, e.g. 5054@2023-2025}
        {--path= : Directory containing references.json and lo_mappings/*.json}
        {--validate : Promote imported references to validated (requires reviewed_by)}
        {--revalidate : Allow overwriting references that are already validated}';

    protected $description = 'Import the curated canonical syllabus reference library + LO mappings';

    public function handle(): int
    {
        $source = $this->option('source') ? SyllabusSource::resolveReference($this->option('source')) : null;
        if (! $source) {
            $this->error('Pass --source=<code>@<version> for a registered source.');

            return self::FAILURE;
        }

        $dir = $this->option('path');
        $dir = is_dir((string) $dir) ? $dir : base_path((string) $dir);
        if (! $this->option('path') || ! is_dir($dir)) {
            $this->error("Reference directory not found: {$this->option('path')}");

            return self::FAILURE;
        }

        $stats = ['refs_imported' => 0, 'refs_validated' => 0, 'refs_skipped_validated' => 0,
            'mappings_written' => 0, 'los_mapped' => 0, 'errors' => 0];

        try {
            DB::transaction(function () use ($dir, $source, &$stats) {
                $this->importReferences("{$dir}/references.json", $source, $stats);
                foreach (glob(rtrim($dir, '/\\').'/lo_mappings/*.json') ?: [] as $file) {
                    $this->importMappings($file, $source, $stats);
                }

                // All-or-nothing: mapping import deletes an LO's rows before
                // re-creating them, so ANY per-row error must roll the whole
                // import back - a typo'd file must never strip curated mappings.
                if ($stats['errors'] > 0) {
                    throw new \RuntimeException("{$stats['errors']} error(s) - import rolled back, nothing changed.");
                }
            });
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->all());

            return self::FAILURE;
        }

        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->all());

        if (! $this->option('validate')) {
            $this->warn('References imported as UNTRUSTED. After human review, re-run with --validate.');
        }

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function importReferences(string $path, SyllabusSource $source, array &$stats): void
    {
        if (! is_file($path)) {
            $this->error('references.json not found in --path directory.');
            $stats['errors']++;

            return;
        }

        $file = json_decode(file_get_contents($path), true);
        $fileRef = basename($path).'@'.hash_file('sha256', $path);
        $validate = (bool) $this->option('validate');

        if ($validate && empty($file['reviewed_by'])) {
            $this->error('--validate requires reviewed_by in references.json (reviewer is part of provenance).');
            $stats['errors']++;

            return;
        }

        foreach ($file['references'] ?? [] as $entry) {
            if (! in_array($entry['type'] ?? null, SyllabusReference::ALLOWED_TYPES, true)
                || empty($entry['key']) || empty($entry['title']) || empty($entry['payload'])) {
                $this->error('Invalid reference entry: '.json_encode($entry['key'] ?? $entry));
                $stats['errors']++;

                continue;
            }

            $existing = SyllabusReference::where('syllabus_source_id', $source->id)
                ->where('reference_type', $entry['type'])
                ->where('key', $entry['key'])
                ->first();

            if ($existing && $existing->status === SyllabusReference::STATUS_VALIDATED && ! $this->option('revalidate')) {
                $stats['refs_skipped_validated']++;

                continue;
            }

            SyllabusReference::updateOrCreate(
                [
                    'syllabus_source_id' => $source->id,
                    'reference_type' => $entry['type'],
                    'key' => $entry['key'],
                ],
                [
                    'title' => $entry['title'],
                    'payload' => $entry['payload'],
                    'provenance' => ($entry['provenance'] ?? []) + [
                        'file' => $fileRef,
                        'source_pdf_sha256' => $source->source_pdf_sha256,
                    ],
                    'status' => $validate ? SyllabusReference::STATUS_VALIDATED : SyllabusReference::STATUS_EXTRACTED,
                    'review_note' => $entry['review_note'] ?? null,
                    'validated_by' => $validate ? $file['reviewed_by'] : null,
                    'validated_at' => $validate ? now() : null,
                ]
            );

            $stats[$validate ? 'refs_validated' : 'refs_imported']++;
        }
    }

    private function importMappings(string $path, SyllabusSource $source, array &$stats): void
    {
        $file = json_decode(file_get_contents($path), true);
        $section = $file['section_code'] ?? null;
        if (! $section || empty($file['mappings'])) {
            $this->error(basename($path).': mapping file needs section_code + mappings[].');
            $stats['errors']++;

            return;
        }

        // Policy item keys are checked against the policy's indexed content.
        $policyIndex = [];

        foreach ($file['mappings'] as $entry) {
            $lo = LearningObjective::where('syllabus_source_id', $source->id)
                ->where('section_code', $section)
                ->where('objective_number', (int) ($entry['objective'] ?? 0))
                ->first();

            if (! $lo) {
                $this->error(basename($path).": no LO {$section} #{$entry['objective']}.");
                $stats['errors']++;

                continue;
            }

            // Declarative semantics: replace this LO's mapping rows wholesale.
            LearningObjectiveReference::where('learning_objective_id', $lo->id)->delete();

            foreach ($entry['references'] ?? [] as $target) {
                $role = $target['role'] ?? null;
                if (! in_array($role, LearningObjectiveReference::ALLOWED_ROLES, true)) {
                    $this->error("{$section} #{$lo->objective_number}: invalid role '{$role}'.");
                    $stats['errors']++;

                    continue;
                }

                if (isset($target['ref'])) {
                    [$type, $key] = array_pad(explode(':', $target['ref'], 2), 2, null);
                    $reference = SyllabusReference::where('syllabus_source_id', $source->id)
                        ->where('reference_type', $type)
                        ->where('key', $key)
                        ->first();
                    if (! $reference) {
                        $this->error("{$section} #{$lo->objective_number}: unknown reference '{$target['ref']}'.");
                        $stats['errors']++;

                        continue;
                    }
                    LearningObjectiveReference::create([
                        'learning_objective_id' => $lo->id,
                        'syllabus_reference_id' => $reference->id,
                        'role' => $role,
                        'note' => $target['note'] ?? null,
                    ]);
                } elseif (isset($target['policy'], $target['item'])) {
                    $policyIndex[$target['policy']] ??= $this->policyItemKeys($source, $target['policy']);
                    if (! in_array($target['item'], $policyIndex[$target['policy']], true)) {
                        $this->error("{$section} #{$lo->objective_number}: policy '{$target['policy']}' has no item '{$target['item']}'.");
                        $stats['errors']++;

                        continue;
                    }
                    LearningObjectiveReference::create([
                        'learning_objective_id' => $lo->id,
                        'policy_type' => $target['policy'],
                        'policy_item_key' => $target['item'],
                        'role' => $role,
                        'note' => $target['note'] ?? null,
                    ]);
                } else {
                    $this->error("{$section} #{$lo->objective_number}: mapping needs 'ref' or 'policy'+'item'.");
                    $stats['errors']++;

                    continue;
                }

                $stats['mappings_written']++;
            }

            $stats['los_mapped']++;
        }
    }

    /** All addressable item keys of a policy (keyed items + natural word/term keys). */
    private function policyItemKeys(SyllabusSource $source, string $type): array
    {
        $policy = SyllabusPolicy::where('syllabus_source_id', $source->id)
            ->where('policy_type', $type)
            ->first();
        if (! $policy) {
            return [];
        }

        $keys = [];
        $walk = function ($node) use (&$walk, &$keys) {
            if (! is_array($node)) {
                return;
            }
            foreach (['key', 'word', 'term'] as $field) {
                if (isset($node[$field]) && is_string($node[$field])) {
                    $keys[] = $node[$field];
                }
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($policy->content);

        return $keys;
    }
}
