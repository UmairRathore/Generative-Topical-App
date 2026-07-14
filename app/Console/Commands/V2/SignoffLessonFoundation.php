<?php

namespace App\Console\Commands\V2;

use App\Models\V2\ReviewSignoff;
use App\Models\V2\SyllabusSource;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:signoff-lesson-foundation
|--------------------------------------------------------------------------
| Records (or verifies) a HUMAN sign-off of one lesson's academic foundation,
| bound to the exact content state via a deterministic fingerprint of the
| packet's canonical-truth sections. Additive: never touches row-level
| reviewed_by/validated_by provenance; scoped: approves nothing beyond the
| packet's own targets/references/policies/misconceptions/widgets.
|
|   Sign (the human runs this themselves after reading the review pack):
|     php artisan v2:signoff-lesson-foundation <packet.json> --reviewer=umair \
|         [--plan=<revised_plan.json>] [--artifact=<dir>] [--notes="..."]
|
|   Verify (is the latest sign-off still valid for current content?):
|     php artisan v2:signoff-lesson-foundation <packet.json> --verify
|     -> VALID | STALE (content changed since sign-off) | NONE
*/
class SignoffLessonFoundation extends Command
{
    protected $signature = 'v2:signoff-lesson-foundation
        {packet : Path to the compiled Stage A packet JSON that was reviewed}
        {--reviewer= : Human reviewer identity (required to sign)}
        {--plan= : Optional path to the reviewed Stage A plan JSON}
        {--artifact= : Optional reviewed artifact directory/pack path}
        {--notes= : Optional review notes}
        {--verify : Verify the latest sign-off for this scope instead of signing}';

    protected $description = 'Record or verify a hash-bound, scoped human sign-off of a lesson academic foundation';

    public function handle(): int
    {
        $path = $this->argument('packet');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path) || ! is_array($packet = json_decode(file_get_contents($path), true))) {
            $this->error('Packet file missing or not valid JSON.');

            return self::FAILURE;
        }

        $scope = ReviewSignoff::scopeFor($packet);
        $fingerprint = ReviewSignoff::foundationFingerprint($packet);
        $packetSha = hash('sha256', file_get_contents($path));

        if ($this->option('verify')) {
            $latest = ReviewSignoff::where('scope', $scope)->orderByDesc('signed_at')->first();
            if (! $latest) {
                $this->warn("NONE - no sign-off recorded for scope {$scope}.");

                return self::FAILURE;
            }
            if ($latest->foundation_fingerprint === $fingerprint) {
                $this->info("VALID - {$latest->reviewer} signed this exact foundation on {$latest->signed_at} (fingerprint {$fingerprint}).");

                return self::SUCCESS;
            }
            $this->error("STALE - foundation changed since {$latest->reviewer}'s sign-off on {$latest->signed_at}.");
            $this->line("signed fingerprint:  {$latest->foundation_fingerprint}");
            $this->line("current fingerprint: {$fingerprint}");

            return self::FAILURE;
        }

        if (! $this->option('reviewer')) {
            $this->error('--reviewer is required to sign (the human runs this themselves after reading the review pack).');

            return self::FAILURE;
        }
        if (($packet['validation']['warnings'] ?? ['unknown']) !== []) {
            $this->error('Refusing to sign a packet with validation warnings.');

            return self::FAILURE;
        }

        $source = SyllabusSource::resolveReference(
            $packet['identity']['syllabus_code'].'@'.$packet['identity']['syllabus_version']
        );
        if (! $source) {
            $this->error('Packet syllabus source is not registered.');

            return self::FAILURE;
        }

        $planPath = $this->option('plan');
        $signoff = ReviewSignoff::create([
            'syllabus_source_id' => $source->id,
            'scope' => $scope,
            'reviewer' => $this->option('reviewer'),
            'artifact_path' => $this->option('artifact'),
            'packet_sha256' => $packetSha,
            'foundation_fingerprint' => $fingerprint,
            'plan_sha256' => $planPath && is_file($planPath) ? hash('sha256', file_get_contents($planPath)) : null,
            'notes' => $this->option('notes'),
            'signed_at' => now(),
        ]);

        $this->info("Sign-off #{$signoff->id} recorded.");
        $this->table(['field', 'value'], [
            ['scope', $scope],
            ['reviewer', $signoff->reviewer],
            ['foundation fingerprint', $fingerprint],
            ['packet sha256', $packetSha],
            ['plan sha256', $signoff->plan_sha256 ?? '—'],
        ]);
        $this->warn('Scoped approval only: this signs the exact fingerprinted foundation of this one lesson - nothing else.');

        return self::SUCCESS;
    }
}
