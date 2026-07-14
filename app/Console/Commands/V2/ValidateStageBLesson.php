<?php

namespace App\Console\Commands\V2;

use App\Services\V2\AuthoringArtifactReader;
use Illuminate\Console\Command;
use Throwable;

/*
|--------------------------------------------------------------------------
| v2:validate-stage-b-lesson
|--------------------------------------------------------------------------
| Deterministic Stage B validation of one artifact directory (lesson + packet
| + approved plan), including live approval-gate checks. Used by the offline
| authoring workflow (/author-stage-b) and by humans before review.
|
|   php artisan v2:validate-stage-b-lesson <artifact-id> [--json]
*/
class ValidateStageBLesson extends Command
{
    protected $signature = 'v2:validate-stage-b-lesson
        {artifact : Safe artifact id (directory basename under the authoring root)}
        {--json : Emit the raw validation result as JSON}';

    protected $description = 'Run the deterministic Stage B lesson validator against one authoring artifact';

    public function handle(AuthoringArtifactReader $reader): int
    {
        try {
            $artifact = $reader->load($this->argument('artifact'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $validation = $artifact['validation'];

        if ($this->option('json')) {
            $this->line(json_encode([
                'artifact' => $artifact['id'],
                'lesson_sha256' => $artifact['hashes']['lesson_sha256'],
                'validation' => $validation,
                'approvals' => $artifact['approvals'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Artifact {$artifact['id']} ({$artifact['lesson_file']}, sha {$artifact['hashes']['lesson_sha256']})");
            $this->line('Blocking: '.count($validation['blocking']));
            foreach ($validation['blocking'] as $violation) {
                $this->error("  ✗ {$violation}");
            }
            $this->line('Warnings: '.count($validation['warnings']));
            foreach ($validation['warnings'] as $warning) {
                $this->warn("  ! {$warning}");
            }
            foreach ($artifact['approvals'] as $gate => $state) {
                $this->line("Approval [{$gate}]: {$state['status']}".(isset($state['reason']) ? " ({$state['reason']})" : ''));
            }
        }

        return $validation['blocking'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
