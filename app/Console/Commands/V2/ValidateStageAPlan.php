<?php

namespace App\Console\Commands\V2;

use App\Services\V2\LessonPlanPacketCompiler;
use App\Services\V2\StageAPlanValidator;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:validate-stage-a-plan
|--------------------------------------------------------------------------
| Deterministic validation of an authored Stage A plan artifact against the
| packet it was authored from. The core tool of the OFFLINE Fable authoring
| workflow (.claude/skills/author-stage-a): Fable authors the plan file in
| Claude Code, then this command is the non-negotiable gate.
|
|   php artisan v2:validate-stage-a-plan <plan.json> <packet.json> \
|       [--expect-objectives=7,8,9,11,12,13]
|
| --expect-objectives additionally runs the pre-authoring packet safety
| checks. Exit code is FAILURE on any blocking violation (warnings alone
| pass). Never edit validator output; correct the plan artifact and re-run.
*/
class ValidateStageAPlan extends Command
{
    protected $signature = 'v2:validate-stage-a-plan
        {plan : Path to the authored Stage A plan JSON}
        {packet : Path to the compiled Stage A packet JSON}
        {--expect-objectives= : Comma-separated target numbers; also runs packet pre-authoring checks}';

    protected $description = 'Validate an offline-authored Stage A plan against its packet (blocking/warnings/pass)';

    public function handle(): int
    {
        [$plan, $packet] = [$this->readJson('plan'), $this->readJson('packet')];
        if ($plan === null || $packet === null) {
            return self::FAILURE;
        }

        if ($this->option('expect-objectives')) {
            $expected = collect(explode(',', (string) $this->option('expect-objectives')))
                ->map(fn ($n) => (int) trim($n))->filter()->values()->all();
            foreach (LessonPlanPacketCompiler::preAuthoringChecks($packet, $expected) as $failure) {
                $this->error("PACKET PRE-CHECK: {$failure}");

                return self::FAILURE;
            }
            $this->info('Packet pre-authoring checks passed.');
        }

        $result = app(StageAPlanValidator::class)->validate($plan, $packet);

        foreach ($result['blocking'] as $issue) {
            $this->error("BLOCKING: {$issue}");
        }
        foreach ($result['warnings'] as $warning) {
            $this->warn("WARNING: {$warning}");
        }
        $this->table(['result', 'count'], [
            ['blocking', count($result['blocking'])],
            ['warnings', count($result['warnings'])],
        ]);
        if ($result['blocking'] === [] && $result['warnings'] === []) {
            $this->info('StageAPlanValidator: ALL RULES PASSED, ZERO WARNINGS.');
        } elseif ($result['blocking'] === []) {
            $this->info('StageAPlanValidator: no blocking violations.');
        }

        return $result['blocking'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function readJson(string $argument): ?array
    {
        $path = $this->argument($argument);
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) {
            $this->error("{$argument} file not found: {$this->argument($argument)}");

            return null;
        }
        $decoded = json_decode(file_get_contents($path), true);
        if (! is_array($decoded)) {
            $this->error("{$argument} file is not valid JSON: {$path}");

            return null;
        }

        return $decoded;
    }
}
