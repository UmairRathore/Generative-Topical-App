<?php

namespace App\Console\Commands\V2;

use App\Models\V2\ReviewSignoff;
use App\Models\V2\SyllabusSource;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:approve-stage-a-plan
|--------------------------------------------------------------------------
| Records (or verifies) HUMAN approval of one Stage A PEDAGOGICAL PLAN,
| separate from academic-foundation sign-off (v2:signoff-lesson-foundation).
| Two independent audit questions, two records:
|   - "Has the academic truth packet been approved?"  -> foundation scope
|   - "Has this exact pedagogical plan been approved?" -> plan scope (here)
|
| The plan approval binds BOTH the plan hash and the foundation fingerprint
| it was validated against: a plan edit stales only the plan approval; a
| canonical-truth change stales the plan approval too (the plan must be
| revalidated against the new packet) while historical records remain valid
| for what was actually reviewed.
|
|   Approve:  php artisan v2:approve-stage-a-plan <plan.json> <packet.json> --reviewer=umair
|   Verify:   php artisan v2:approve-stage-a-plan <plan.json> <packet.json> --verify
*/
class ApproveStageAPlan extends Command
{
    protected $signature = 'v2:approve-stage-a-plan
        {plan : Path to the accepted Stage A plan JSON}
        {packet : Path to the packet the plan was validated against}
        {--reviewer= : Human reviewer identity (required to approve)}
        {--notes= : Optional review notes}
        {--verify : Verify the latest plan approval instead of approving}';

    protected $description = 'Record or verify hash-bound human approval of a Stage A pedagogical plan (separate from truth sign-off)';

    public function handle(): int
    {
        [$planPath, $packetPath] = [$this->resolve('plan'), $this->resolve('packet')];
        if (! $planPath || ! $packetPath || ! is_array($packet = json_decode(file_get_contents($packetPath), true))) {
            $this->error('Plan/packet file missing or invalid JSON.');

            return self::FAILURE;
        }

        $scope = 'stage-a-plan:'.substr(ReviewSignoff::scopeFor($packet), strlen('stage-a-foundation:'));
        $planSha = hash('sha256', file_get_contents($planPath));
        $fingerprint = ReviewSignoff::foundationFingerprint($packet);

        if ($this->option('verify')) {
            $latest = ReviewSignoff::where('scope', $scope)->orderByDesc('signed_at')->first();
            if (! $latest) {
                $this->warn("NONE - no plan approval recorded for scope {$scope}.");

                return self::FAILURE;
            }
            if ($latest->plan_sha256 !== $planSha) {
                $this->error("STALE_PLAN - the plan changed since {$latest->reviewer}'s approval on {$latest->signed_at}.");

                return self::FAILURE;
            }
            if ($latest->foundation_fingerprint !== $fingerprint) {
                $this->error('STALE_FOUNDATION - canonical truth changed since approval; revalidate the plan against the new packet and re-approve.');

                return self::FAILURE;
            }
            $this->info("VALID - {$latest->reviewer} approved this exact plan against this exact foundation on {$latest->signed_at}.");

            return self::SUCCESS;
        }

        if (! $this->option('reviewer')) {
            $this->error('--reviewer is required to approve (the human runs this themselves).');

            return self::FAILURE;
        }

        $source = SyllabusSource::resolveReference(
            $packet['identity']['syllabus_code'].'@'.$packet['identity']['syllabus_version']
        );
        if (! $source) {
            $this->error('Packet syllabus source is not registered.');

            return self::FAILURE;
        }

        $approval = ReviewSignoff::create([
            'syllabus_source_id' => $source->id,
            'scope' => $scope,
            'reviewer' => $this->option('reviewer'),
            'artifact_path' => dirname($planPath),
            'packet_sha256' => hash('sha256', file_get_contents($packetPath)),
            'foundation_fingerprint' => $fingerprint,
            'plan_sha256' => $planSha,
            'notes' => $this->option('notes'),
            'signed_at' => now(),
        ]);

        $this->info("Plan approval #{$approval->id} recorded for scope {$scope}.");
        $this->warn('This approves the PEDAGOGICAL PLAN only - academic truth sign-off is a separate record (v2:signoff-lesson-foundation).');

        return self::SUCCESS;
    }

    private function resolve(string $argument): ?string
    {
        $path = $this->argument($argument);
        $path = is_file($path) ? $path : base_path($path);

        return is_file($path) ? $path : null;
    }
}
