<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Question;
use App\Services\V2\DuplicateOptionImageFixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:fix-duplicate-option-images
|--------------------------------------------------------------------------
| Repairs questions where the same picture was stored under more than one image
| row, so the diagram renders twice (once in the stem, once under "OPTIONS"), or
| where a single-figure "label the diagram" question was extracted as four
| identical option crops. Collapses byte-identical images to one row (keeping
| figure > table > option image), then normalises option images: drops spurious
| crops on text questions, and promotes the real figure(s) on label-style
| questions so A/B/C/D render as plain choices. Idempotent.
|
| Detection is content-based (MD5), so it is robust to differing
| filenames/bboxes. Also invoked per-question by v2:import-questions --copy-images.
*/
class FixDuplicateOptionImages extends Command
{
    protected $signature = 'v2:fix-duplicate-option-images
        {--dry-run : Report what would change without writing}';

    protected $description = 'Collapse duplicate question/option images so each diagram renders once';

    public function handle(DuplicateOptionImageFixer $fixer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $fixed = [];
        $run = function () use ($fixer, &$fixed) {
            $fixed = $fixer->fixAll();
        };

        if ($dryRun) {
            // Apply inside a transaction, capture the plan, then roll everything back.
            try {
                DB::transaction(function () use ($run) {
                    $run();
                    throw new RollbackSignal();
                });
            } catch (RollbackSignal) {
                // expected — nothing was persisted
            }
        } else {
            $run();
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info($prefix.'Repaired '.count($fixed).' question(s).');

        foreach ($fixed as $r) {
            $q = Question::find($r['qid']);
            $bits = array_filter([
                $r['deleted'] ? "−{$r['deleted']} dup image(s)" : null,
                $r['promoted'] ? "+{$r['promoted']} figure(s)" : null,
                $r['cleared_text'] ? 'options→labels' : null,
                $r['layout'] ? 'layout→question_diagram' : null,
            ]);
            $this->line(sprintf(
                '  QID %-4d %-18s q%-3s %s',
                $r['qid'],
                $q?->source_paper ?? '?',
                $q?->question_number ?? '?',
                implode(', ', $bits),
            ));
        }

        return self::SUCCESS;
    }
}

/** Internal marker used to roll back the dry-run transaction. */
class RollbackSignal extends \RuntimeException
{
}
