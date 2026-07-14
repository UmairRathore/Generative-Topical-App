<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\AuthoringContextBuilder;
use Illuminate\Console\Command;
use InvalidArgumentException;

/*
|--------------------------------------------------------------------------
| v2:authoring-context
|--------------------------------------------------------------------------
| Builds the deterministic lesson-authoring context for one syllabus leaf
| section and a chosen set of its official learning objectives, and prints
| (or writes) it as JSON. This is the exact structured input a lesson-
| authoring system (e.g. Fable) receives - nothing student-facing.
|
|   php artisan v2:authoring-context --source=5054@2023-2025 \
|       --subtopic=1.2 --objectives=1,2,3,7 --out=/tmp/context.json
|
| Refuses unvalidated learning objectives unless --include-draft-los is
| passed explicitly (and then flags them in validation.warnings).
*/
class BuildAuthoringContext extends Command
{
    protected $signature = 'v2:authoring-context
        {--source= : Syllabus source reference, e.g. 5054@2023-2025}
        {--subtopic= : Official leaf section code, e.g. 1.2 or 1.5.1}
        {--objectives= : Comma-separated official objective numbers (default: all validated)}
        {--include-draft-los : Allow unvalidated objectives (flagged, non-canonical)}
        {--max-questions=200 : Cap for the question corpus rows}
        {--out= : Write the JSON context to this file instead of stdout}
        {--with-manifest : Include the audit manifest alongside the context}';

    protected $description = 'Build the deterministic lesson-authoring context for a syllabus leaf section';

    public function handle(): int
    {
        $source = $this->option('source') ? SyllabusSource::resolveReference($this->option('source')) : null;
        if (! $source) {
            $this->error('Pass --source=<code>@<version> for a registered source.');

            return self::FAILURE;
        }

        if (! $this->option('subtopic')) {
            $this->error('Pass --subtopic=<leaf section code>, e.g. --subtopic=1.2');

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

        $objectiveNumbers = collect(explode(',', (string) $this->option('objectives')))
            ->map(fn ($n) => (int) trim($n))
            ->filter()
            ->values()
            ->all();

        try {
            $result = app(AuthoringContextBuilder::class)->build(
                $source,
                $subtopic,
                $objectiveNumbers,
                (bool) $this->option('include-draft-los'),
                (int) $this->option('max-questions'),
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $payload = $this->option('with-manifest') ? $result : $result['context'];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($this->option('out')) {
            file_put_contents($this->option('out'), $json.PHP_EOL);
            $this->info("Context written to {$this->option('out')} (".strlen($json).' bytes).');
        } else {
            $this->line($json);
        }

        $context = $result['context'];
        $this->table(['section', 'value'], [
            ['qualification', "{$context['qualification']['subject']} {$context['qualification']['syllabus_code']} ({$context['qualification']['syllabus_version']})"],
            ['leaf section', "{$context['curriculum']['leaf_section']['section_code']} {$context['curriculum']['leaf_section']['title']}"],
            ['objectives selected', count($context['learning_objectives'])],
            ['objectives out of scope', count($context['out_of_scope_objectives'])],
            ['academic references', implode(', ', array_filter(array_map(
                fn ($group, $rows) => $rows ? $group.':'.count($rows) : null,
                array_keys($context['academic_references']),
                $context['academic_references'],
            ))) ?: '—'],
            ['policy slices', implode(', ', array_keys($context['policy_slices'])) ?: '—'],
            ['exclusions', count($context['hard_constraints']['exclusions'])],
            ['approved assets', count($context['internal_assets']['approved'])],
            ['questions in corpus', $context['question_corpus']['counts']['total']],
            ['widget types', count($context['widgets']['types'])],
            ['warnings', count($context['validation']['warnings'])],
        ]);

        foreach ($context['validation']['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
