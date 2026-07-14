<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\SyllabusReferenceResolver;
use Illuminate\Console\Command;
use InvalidArgumentException;

/*
|--------------------------------------------------------------------------
| v2:resolve-references
|--------------------------------------------------------------------------
| Prints the deterministic canonical-reference package for a leaf section
| and selected validated Learning Objectives - the academic-truth slice a
| future Lesson / Fable packet / Tutor context / validator consumes.
|
|   php artisan v2:resolve-references --source=5054@2023-2025 \
|       --subtopic=1.2 --objectives=7,8,9,11,12,13 --out=/tmp/motion_graphs.json
*/
class ResolveReferences extends Command
{
    protected $signature = 'v2:resolve-references
        {--source= : Syllabus source reference, e.g. 5054@2023-2025}
        {--subtopic= : Official leaf section code, e.g. 1.2 or 1.5.4}
        {--objectives= : Comma-separated objective numbers (default: all validated)}
        {--include-draft-los : Allow unvalidated objectives (flagged, non-canonical)}
        {--out= : Write JSON to this file instead of stdout}';

    protected $description = 'Resolve the canonical academic-reference package for selected learning objectives';

    public function handle(): int
    {
        $source = $this->option('source') ? SyllabusSource::resolveReference($this->option('source')) : null;
        if (! $source) {
            $this->error('Pass --source=<code>@<version> for a registered source.');

            return self::FAILURE;
        }

        $subtopic = Subtopic::query()
            ->join('v2_topics', 'v2_topics.id', '=', 'v2_subtopics.topic_id')
            ->where('v2_topics.subject_id', $source->subject_id)
            ->where('v2_subtopics.external_id', $this->option('subtopic'))
            ->select('v2_subtopics.*')
            ->first();
        if (! $subtopic) {
            $this->error("No v2_subtopics row with external_id '{$this->option('subtopic')}' for subject {$source->syllabus_code}.");

            return self::FAILURE;
        }

        $numbers = collect(explode(',', (string) $this->option('objectives')))
            ->map(fn ($n) => (int) trim($n))->filter()->values()->all();

        try {
            $result = app(SyllabusReferenceResolver::class)->resolve(
                $source, $subtopic, $numbers, (bool) $this->option('include-draft-los'),
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $json = json_encode($result['package'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($this->option('out')) {
            file_put_contents($this->option('out'), $json.PHP_EOL);
            $this->info("Package written to {$this->option('out')} (".strlen($json).' bytes).');
        } else {
            $this->line($json);
        }

        $package = $result['package'];
        $this->table(['section', 'value'], [
            ['scope', "{$package['scope']['syllabus']['syllabus_code']}@{$package['scope']['syllabus']['version']} {$package['scope']['leaf_section']['section_code']} {$package['scope']['leaf_section']['title']}"],
            ['objectives', count($package['scope']['selected_objectives']).' selected / '.count($package['scope']['out_of_scope_objectives']).' out of scope'],
            ['terminology', count($package['references']['terminology'])],
            ['quantities', count($package['references']['quantities'])],
            ['definitions', count($package['references']['definitions'])],
            ['relationships', count($package['references']['relationships'])],
            ['constants', count($package['references']['constants'])],
            ['policy slices', implode(', ', array_keys($package['policy_slices'])) ?: '—'],
            ['exclusions', count($package['hard_constraints']['exclusions'])],
            ['warnings', count($package['validation']['warnings'])],
        ]);

        foreach ($package['validation']['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
