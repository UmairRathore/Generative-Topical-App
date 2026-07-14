<?php

namespace App\Console\Commands\V2;

use App\Models\V2\LearningObjective;
use App\Models\V2\SyllabusSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:apply-lo-review
|--------------------------------------------------------------------------
| Applies a human-reviewed overlay to machine-extracted learning objectives
| and promotes them to 'validated' (canonical authoring context). The overlay
| file IS the review artifact: it carries corrected wording, reconstructed
| equations, structured constraints and the reviewer's identity.
|
|   php artisan v2:apply-lo-review \
|       storage/syllabus/physics/OLevels/5054_2023_2025/review/lo_overlays/1.2.json \
|       --source=5054@2023-2025
|
| Overlay shape:
| {
|   "section_code": "1.2",
|   "reviewed_by": "umair",
|   "objectives": [
|     { "number": 1, "text": "...corrected canonical wording...",
|       "sub_items": [...], "equations": [...], "constraints": [...],
|       "review_note": "...", "status": "validated" }   // status optional; default validated
|   ]
| }
| Fields omitted for an objective keep their extracted values; every objective
| listed is promoted (or explicitly rejected). Objectives of the section NOT
| listed in the overlay are left untouched at 'extracted'.
*/
class ApplyLoReview extends Command
{
    protected $signature = 'v2:apply-lo-review
        {overlay : Path to the review overlay JSON}
        {--source= : Syllabus source reference, e.g. 5054@2023-2025}
        {--revalidate : Allow overwriting rows that are already validated (stale-overlay protection)}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Apply a human review overlay to extracted learning objectives (promotes to validated)';

    public function handle(): int
    {
        $source = $this->option('source') ? SyllabusSource::resolveReference($this->option('source')) : null;
        if (! $source) {
            $this->error('Pass --source=<code>@<version> for a registered source.');

            return self::FAILURE;
        }

        $path = $this->argument('overlay');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) {
            $this->error("Overlay not found: {$this->argument('overlay')}");

            return self::FAILURE;
        }

        $overlay = json_decode(file_get_contents($path), true);
        if (! is_array($overlay) || empty($overlay['section_code']) || empty($overlay['objectives'])) {
            $this->error('Overlay must contain section_code and a non-empty objectives[] array.');

            return self::FAILURE;
        }
        if (empty($overlay['reviewed_by'])) {
            $this->error('Overlay must record reviewed_by - the human reviewer is part of the provenance chain.');

            return self::FAILURE;
        }

        $sectionCode = $overlay['section_code'];
        $overlayRef = basename($path).'@'.hash_file('sha256', $path);

        $existing = LearningObjective::where('syllabus_source_id', $source->id)
            ->where('section_code', $sectionCode)
            ->get()
            ->keyBy('objective_number');

        if ($existing->isEmpty()) {
            $this->error("No extracted objectives for section {$sectionCode} in {$source->reference()}. Run v2:extract-learning-objectives first.");

            return self::FAILURE;
        }

        $stats = ['validated' => 0, 'rejected' => 0, 'missing' => 0];
        $changes = [];

        DB::transaction(function () use ($overlay, $existing, $overlayRef, $sectionCode, &$stats, &$changes) {
            foreach ($overlay['objectives'] as $reviewed) {
                $number = (int) ($reviewed['number'] ?? 0);
                $row = $existing->get($number);
                if (! $row) {
                    $stats['missing']++;
                    $changes[] = [$sectionCode, $number, 'MISSING IN DB', ''];

                    continue;
                }

                // Consistent with the policy/reference importers: a validated row
                // is only re-touched with an explicit --revalidate (protects
                // against replaying a stale overlay over newer human review).
                if ($row->status === LearningObjective::STATUS_VALIDATED && ! $this->option('revalidate')) {
                    $changes[] = [$sectionCode, $number, 'skipped (already validated; use --revalidate)', ''];

                    continue;
                }

                $status = $reviewed['status'] ?? LearningObjective::STATUS_VALIDATED;
                if (! in_array($status, LearningObjective::STATUSES, true)) {
                    $changes[] = [$sectionCode, $number, "INVALID STATUS {$status}", ''];

                    continue;
                }

                $values = [
                    'status' => $status,
                    'review_note' => $reviewed['review_note'] ?? $row->review_note,
                    'validated_by' => $overlay['reviewed_by'],
                    'validated_at' => now(),
                    'provenance' => array_merge($row->provenance ?? [], ['overlay' => $overlayRef]),
                ];

                // Only fields present in the overlay replace extracted values; the
                // untouched machine output stays in raw_extract for audit.
                foreach (['text', 'sub_items', 'equations', 'constraints'] as $field) {
                    if (array_key_exists($field, $reviewed)) {
                        $values[$field] = $reviewed[$field];
                    }
                }

                if (! $this->option('dry-run')) {
                    $row->update($values);
                }

                $stats[$status === LearningObjective::STATUS_REJECTED ? 'rejected' : 'validated']++;
                $changes[] = [
                    $sectionCode,
                    $number,
                    $status,
                    implode(', ', array_keys(array_intersect_key($reviewed, array_flip(['text', 'sub_items', 'equations', 'constraints'])))),
                ];
            }
        });

        $this->table(['section', '#', 'status', 'fields overridden'], $changes);
        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->all());

        if ($this->option('dry-run')) {
            $this->line('Dry run - nothing written.');
        } else {
            $untouched = $existing->count() - count($overlay['objectives']);
            if ($untouched > 0) {
                $this->warn("{$untouched} objective(s) of {$sectionCode} remain 'extracted' (not covered by this overlay).");
            }
        }

        return $stats['missing'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
