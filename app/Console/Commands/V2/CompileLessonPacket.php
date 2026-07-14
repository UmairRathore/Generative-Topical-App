<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Subtopic;
use App\Models\V2\SyllabusSource;
use App\Services\V2\LessonPlanPacketCompiler;
use Illuminate\Console\Command;
use InvalidArgumentException;

/*
|--------------------------------------------------------------------------
| v2:compile-lesson-packet
|--------------------------------------------------------------------------
| Compiles the Stage A lesson-plan packet (server-only) for selected target
| Learning Objectives - the exact deterministic input a controlled Fable
| Stage A experiment will receive. Does NOT call any AI.
|
|   php artisan v2:compile-lesson-packet --source=5054@2023-2025 \
|       --subtopic=1.2 --objectives=7,8,9,11,12,13 --out=/tmp/packet.json
*/
class CompileLessonPacket extends Command
{
    protected $signature = 'v2:compile-lesson-packet
        {--source= : Syllabus source reference, e.g. 5054@2023-2025}
        {--subtopic= : Official leaf section code}
        {--objectives= : Comma-separated TARGET objective numbers (required)}
        {--out= : Write JSON to this file instead of stdout}';

    protected $description = 'Compile the deterministic Stage A lesson-plan packet (server-only, no AI call)';

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
        if ($numbers === []) {
            $this->error('Pass --objectives= with explicit target objective numbers - Stage A never defaults to "everything".');

            return self::FAILURE;
        }

        try {
            $result = app(LessonPlanPacketCompiler::class)->compile($source, $subtopic, $numbers);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $packet = $result['packet'];
        $json = json_encode($packet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($this->option('out')) {
            file_put_contents($this->option('out'), $json.PHP_EOL);
            $this->info("Packet written to {$this->option('out')} (".strlen($json).' bytes). SERVER-ONLY - never expose to students.');
        } else {
            $this->line($json);
        }

        $this->table(['section', 'value'], [
            ['identity', "{$packet['identity']['syllabus_code']}@{$packet['identity']['syllabus_version']} {$packet['identity']['leaf_section']['section_code']}"],
            ['target objectives', count($packet['target_coverage']['objectives'])],
            ['out of scope', count($packet['out_of_scope_curriculum']['objectives'])],
            ['direct refs', implode(', ', array_filter(array_map(fn ($g, $r) => $r ? "$g:".count($r) : null, array_keys($packet['direct_academic_truth']), $packet['direct_academic_truth'])))],
            ['supporting refs', implode(', ', array_filter(array_map(fn ($g, $r) => $r ? "$g:".count($r) : null, array_keys($packet['supporting_academic_truth']['references']), $packet['supporting_academic_truth']['references'])))],
            ['exclusions', count($packet['hard_constraints']['exclusions'])],
            ['policy slices', implode(', ', array_keys($packet['policy_slices']))],
            ['approved assets', count($packet['internal_content_evidence']['assets'])],
            ['evidence questions', count($packet['assessment_evidence']['questions'])],
            ['widgets', collect($packet['relevant_widgets']['widgets'])->map(fn ($w) => "{$w['key']} ({$w['compatibility_verdict']})")->implode(', ') ?: '—'],
            ['warnings', count($packet['validation']['warnings'])],
        ]);

        foreach ($packet['validation']['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
