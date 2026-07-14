<?php

namespace App\Console\Commands\V2;

use App\Models\V2\SyllabusSource;
use App\Services\V2\SyllabusSourceDiff;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:diff-syllabus-sources
|--------------------------------------------------------------------------
| Deterministic structured diff between two registered syllabus sources.
| The version gate that must run before mass course generation against a
| new syllabus edition.
|
|   php artisan v2:diff-syllabus-sources 5054@2023-2025 5054@2026-2028 \
|       --out=/path/diff.json
*/
class DiffSyllabusSources extends Command
{
    protected $signature = 'v2:diff-syllabus-sources
        {old : Old source reference, e.g. 5054@2023-2025}
        {new : New source reference, e.g. 5054@2026-2028}
        {--out= : Write the full diff JSON to this file}';

    protected $description = 'Deterministic structural diff between two registered syllabus sources';

    public function handle(SyllabusSourceDiff $diff): int
    {
        $old = SyllabusSource::resolveReference($this->argument('old'));
        $new = SyllabusSource::resolveReference($this->argument('new'));
        if (! $old || ! $new) {
            $this->error('Both sources must be registered (v2:register-syllabus-source).');

            return self::FAILURE;
        }

        $result = $diff->compare($old, $new);

        if ($this->option('out')) {
            file_put_contents(
                $this->option('out'),
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL
            );
            $this->info("Diff written to {$this->option('out')}.");
        }

        $this->table(['dimension', 'result'], [
            ['topics', 'added '.count($result['topics']['added']).' / removed '.count($result['topics']['removed']).' / renamed '.count($result['topics']['renamed'])],
            ['sections', 'added '.count($result['sections']['added']).' / removed '.count($result['sections']['removed']).' / renamed '.count($result['sections']['renamed']).' / count-changed '.count($result['sections']['lo_count_changed'])],
            ['objectives', collect($result['objective_counts'])->map(fn ($n, $k) => "{$k}: {$n}")->implode(' | ')],
            ['exclusion bounds', 'old '.$result['exclusions']['total_old'].' / new '.$result['exclusions']['total_new'].' / added '.count($result['exclusions']['added']).' / removed '.count($result['exclusions']['removed'])],
        ]);

        foreach (['text_changed', 'added', 'removed'] as $class) {
            foreach ($result['objectives'][$class] as $row) {
                $this->warn(strtoupper($class).': '.($row['old_reference'] ?? $row['new_reference'] ?? '?'));
            }
        }

        return self::SUCCESS;
    }
}
