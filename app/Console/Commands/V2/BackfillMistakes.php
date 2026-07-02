<?php

namespace App\Console\Commands\V2;

use App\Models\V2\ExamAttempt;
use App\Services\V2\MistakeBankService;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:backfill-mistakes
|--------------------------------------------------------------------------
| Folds every already-submitted attempt's wrong (non-voided) answers into the
| Learning Hub Mistake Bank. New submissions capture automatically via the
| ExamService::submit hook; this seeds the bank for attempts made BEFORE the
| feature existed. Idempotent - safe to re-run (a mistake already recorded for
| an attempt is skipped, counts are not double-incremented).
*/
class BackfillMistakes extends Command
{
    protected $signature = 'v2:backfill-mistakes {--student= : Limit to one student id}';

    protected $description = 'Backfill the Mistake Bank from existing submitted exam attempts';

    public function handle(MistakeBankService $mistakes): int
    {
        $query = ExamAttempt::withoutGlobalScopes()->where('status', 'submitted');
        if ($student = $this->option('student')) {
            $query->where('student_id', (int) $student);
        }

        $total = (clone $query)->count();
        $this->info("Backfilling from {$total} submitted attempt(s)…");
        $bar = $this->output->createProgressBar($total);

        $recorded = 0;
        $query->orderBy('id')->chunkById(200, function ($attempts) use ($mistakes, &$recorded, $bar) {
            foreach ($attempts as $attempt) {
                try {
                    $recorded += $mistakes->syncFromAttempt($attempt);
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->warn("Attempt {$attempt->id} skipped: {$e->getMessage()}");
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Recorded/updated {$recorded} mistake(s) across {$total} attempt(s).");

        return self::SUCCESS;
    }
}
