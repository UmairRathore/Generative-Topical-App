<?php

namespace App\Console\Commands\V2;

use App\Services\V2\QuestionBoilerplateStripper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| v2:strip-question-boilerplate
|--------------------------------------------------------------------------
| Removes Cambridge end-of-paper copyright/footer text ("BLANK PAGE … Permission
| to reproduce … UCLES …") that the extractor swept into question text from the
| page after the last question. Truncates question_text/text_before/text_after at
| the earliest footer marker. Idempotent.
|
| Safety:
|   - --dry-run shows every change without writing.
|   - A timestamped backup of the original rows is written before any change.
|   - A field is never blanked, and never stripped if a "?" sits in the removed
|     tail (the source questions.json files also remain the system of record).
*/
class StripQuestionBoilerplate extends Command
{
    protected $signature = 'v2:strip-question-boilerplate
        {--dry-run : Show every change without writing}';

    protected $description = 'Strip Cambridge footer/boilerplate text out of question fields';

    public function handle(QuestionBoilerplateStripper $stripper): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $candidates = $stripper->candidates();

        // Compute the plan for every candidate first (no writes).
        $plans = [];
        foreach ($candidates as $q) {
            if ($plan = $stripper->plan($q)) {
                $plans[$q->id] = ['question' => $q, 'plan' => $plan];
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Will strip ".count($plans)." of ".$candidates->count()." candidate question(s).");

        foreach ($plans as $entry) {
            $q = $entry['question'];
            $this->line("  QID {$q->id} {$q->source_paper} q{$q->question_number} — ".implode(', ', array_keys($entry['plan'])));
            foreach ($entry['plan'] as $field => $c) {
                $this->line("      removed from {$field}: …".mb_strimwidth(preg_replace('/\s+/', ' ', mb_substr($c['from'], mb_strlen($c['to']))), 0, 90, '…'));
            }
        }

        if ($dryRun || ! $plans) {
            return self::SUCCESS;
        }

        // --- backup originals before writing ---
        $backup = [];
        foreach ($plans as $entry) {
            $q = $entry['question'];
            $backup[] = [
                'id'            => $q->id,
                'question_text' => $q->question_text,
                'text_before'   => $q->text_before,
                'text_after'    => $q->text_after,
            ];
        }
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/question-boilerplate-'.now()->format('Ymd_His').'.json';
        File::put($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info("Backed up ".count($backup)." original row(s) to: ".$path);

        // --- apply ---
        $changed = 0;
        foreach ($plans as $entry) {
            if ($stripper->stripQuestion($entry['question'])) {
                $changed++;
            }
        }

        $this->info("Stripped boilerplate from {$changed} question(s).");
        $this->line("To restore: re-import the affected papers, or load the backup JSON above.");

        return self::SUCCESS;
    }
}
