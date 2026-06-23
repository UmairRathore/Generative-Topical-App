<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:quarantine-optionless
|--------------------------------------------------------------------------
| An MCQ with no answer-choice rows can never be served (ExamService and the
| teacher picker both filter ->has('options')), yet the importer still marks
| such questions `active` when the extractor produced an empty option set.
| This moves every active question that has ZERO option rows to `under_review`
| so the "active" pool reflects only usable items and these surface in the
| admin review queue. Reversible: re-running after options are restored leaves
| them alone (they're no longer optionless), and nothing is deleted.
*/
class QuarantineOptionlessQuestions extends Command
{
    protected $signature = 'v2:quarantine-optionless
        {--dry-run : Show what would change without writing}
        {--status=under_review : Status to move optionless active questions to}';

    protected $description = 'Move active questions that have no answer-choice rows to under_review (they cannot be served)';

    public function handle(): int
    {
        $status = (string) $this->option('status');

        $base = Question::query()->where('status', 'active')->whereDoesntHave('options');

        $total = (clone $base)->count();
        if ($total === 0) {
            $this->info('No active optionless questions found — nothing to quarantine.');

            return self::SUCCESS;
        }

        // per-subject breakdown for the report
        $bySubject = (clone $base)
            ->join('v2_subjects as s', 's.id', '=', 'v2_questions.subject_id')
            ->select('s.code', 's.name', DB::raw('count(*) c'))
            ->groupBy('s.code', 's.name')->orderByDesc('c')->get();

        $this->info(($this->option('dry-run') ? '[DRY RUN] ' : '')."Active optionless questions: {$total}");
        foreach ($bySubject as $row) {
            $this->line("  {$row->code}  {$row->name}: {$row->c}");
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run — no changes written. Would move {$total} questions to '{$status}'.");

            return self::SUCCESS;
        }

        $changed = (clone $base)->update(['status' => $status, 'needs_review' => true]);
        $this->info("Moved {$changed} questions to '{$status}' (needs_review=1). Files and rows untouched.");

        return self::SUCCESS;
    }
}
