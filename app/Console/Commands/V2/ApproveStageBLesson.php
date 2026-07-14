<?php

namespace App\Console\Commands\V2;

use App\Services\V2\AuthoringArtifactReader;
use App\Services\V2\StageBReviewService;
use Illuminate\Console\Command;
use Throwable;

/*
|--------------------------------------------------------------------------
| v2:approve-stage-b-lesson
|--------------------------------------------------------------------------
| CLI seam over the SAME StageBReviewService the Super Admin UI uses.
| Records (or verifies) hash-bound HUMAN approval of one exact Stage B lesson
| artifact. Approval binds lesson SHA + Stage A plan SHA + foundation
| fingerprint + syllabus source; any lesson edit stales it.
|
|   Approve:  php artisan v2:approve-stage-b-lesson <artifact-id> --reviewer=<human>
|   Verify:   php artisan v2:approve-stage-b-lesson <artifact-id> --verify
*/
class ApproveStageBLesson extends Command
{
    protected $signature = 'v2:approve-stage-b-lesson
        {artifact : Safe artifact id (directory basename under the authoring root)}
        {--reviewer= : Human reviewer identity (required to approve)}
        {--notes= : Optional review notes}
        {--verify : Verify the latest lesson approval instead of approving}';

    protected $description = 'Record or verify hash-bound human approval of one exact Stage B lesson artifact (shared service with the review UI)';

    public function handle(AuthoringArtifactReader $reader, StageBReviewService $service): int
    {
        try {
            $artifact = $reader->load($this->argument('artifact'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('verify')) {
            $state = $artifact['approvals']['lesson'];
            if ($state['status'] === 'none') {
                $this->warn("NONE - no Stage B approval recorded for scope {$artifact['scopes']['lesson']}.");

                return self::FAILURE;
            }
            if ($state['status'] === 'stale') {
                $this->error(strtoupper('stale_'.$state['reason'])." - since {$state['reviewer']}'s approval on {$state['signed_at']}.");

                return self::FAILURE;
            }
            $this->info("VALID - {$state['reviewer']} approved this exact lesson artifact on {$state['signed_at']}.");

            return self::SUCCESS;
        }

        if (! $this->option('reviewer')) {
            $this->error('--reviewer is required to approve (the human runs this themselves).');

            return self::FAILURE;
        }

        try {
            $approval = $service->approveExact(
                $this->argument('artifact'),
                $artifact['hashes']['lesson_sha256'],
                (string) $this->option('reviewer'),
                $this->option('notes'),
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Stage B approval #{$approval->id} recorded for scope {$approval->scope}.");
        $this->warn('This approves the EXACT lesson artifact SHA - any content change makes it stale.');

        return self::SUCCESS;
    }
}
