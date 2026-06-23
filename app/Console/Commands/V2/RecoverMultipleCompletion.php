<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Question;
use App\Models\V2\QuestionOption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:recover-multiple-completion
|--------------------------------------------------------------------------
| CIE "multiple completion" MCQs print the A–D key ONCE as a shared grid at the
| top of the section, not under each question — so the per-question extractor
| produced empty option sets for them (left optionless, then quarantined to
| under_review). The key is a FIXED rubric, verified identical across every
| affected 9701 paper (2010–2021):
|     A  1, 2 and 3 are correct      C  2 and 3 only are correct
|     B  1 and 2 only are correct    D  1 only is correct
| The correct_answer letter was already extracted from the mark scheme, so we
| only need to attach the four rubric options; grading is unchanged. We target
| only stems that actually contain the numbered statements 1, 2 and 3 (genuine
| multiple-completion) and re-activate those; diagram-choice questions (no
| numbered statements) are left quarantined for separate handling.
| Idempotent: only touches questions that still have zero option rows.
*/
class RecoverMultipleCompletion extends Command
{
    protected $signature = 'v2:recover-multiple-completion
        {--subject=9701 : Subject code to recover}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Re-attach the standard CIE multiple-completion A–D rubric to optionless questions and re-activate them';

    private const RUBRIC = [
        'A' => '1, 2 and 3 are correct',
        'B' => '1 and 2 only are correct',
        'C' => '2 and 3 only are correct',
        'D' => '1 only is correct',
    ];

    public function handle(): int
    {
        $code = (string) $this->option('subject');
        $sid = DB::table('v2_subjects')->where('code', $code)->value('id');
        if (! $sid) {
            $this->error("Subject {$code} not found.");

            return self::FAILURE;
        }

        // Optionless, quarantined questions for this subject.
        $candidates = Question::where('subject_id', $sid)
            ->where('status', 'under_review')
            ->whereDoesntHave('options')
            ->get(['id', 'source_paper', 'question_number', 'question_text', 'correct_answer', 'warnings', 'layout_type']);

        $mc = $candidates->filter(fn ($q) => $this->isMultipleCompletion($q->question_text) && in_array($q->correct_answer, ['A', 'B', 'C', 'D'], true));
        $skipped = $candidates->reject(fn ($q) => $mc->contains('id', $q->id));

        $this->info(($this->option('dry-run') ? '[DRY RUN] ' : '')."Subject {$code}: {$candidates->count()} optionless quarantined; recoverable multiple-completion = {$mc->count()}; left quarantined = {$skipped->count()}");
        $this->line('  left quarantined by layout: '.json_encode($skipped->groupBy('layout_type')->map->count()->toArray()));

        if ($mc->isEmpty()) {
            return self::SUCCESS;
        }

        // a couple of examples for eyeballing
        foreach ($mc->take(2) as $q) {
            $this->line("   e.g. {$q->source_paper} Q{$q->question_number} ans={$q->correct_answer}");
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run — would attach the A–D rubric to {$mc->count()} questions and set them active.");

            return self::SUCCESS;
        }

        $now = now();
        $done = 0;
        DB::transaction(function () use ($mc, $now, &$done) {
            foreach ($mc as $q) {
                $sort = 0;
                foreach (self::RUBRIC as $label => $text) {
                    QuestionOption::create([
                        'question_id' => $q->id,
                        'label'       => $label,
                        'text'        => $text,
                        'has_image'   => false,
                        'sort_order'  => $sort++,
                    ]);
                }
                $warnings = is_array($q->warnings) ? $q->warnings : [];
                $warnings[] = 'options auto-synthesized from the CIE multiple-completion rubric (v2:recover-multiple-completion '.$now->toDateString().')';
                Question::where('id', $q->id)->update([
                    'status'       => 'active',
                    'needs_review' => true, // recovered automatically — keep on the human review queue
                    'warnings'     => json_encode(array_values($warnings)),
                ]);
                $done++;
            }
        });

        $this->info("Recovered {$done} multiple-completion questions: attached A–D rubric options and set status=active (needs_review kept). {$skipped->count()} diagram-choice questions remain under_review.");

        return self::SUCCESS;
    }

    /** A genuine multiple-completion stem lists three numbered statements 1, 2 and 3. */
    private function isMultipleCompletion(?string $text): bool
    {
        $t = (string) $text;

        return preg_match('/(^|\s)1\s/', $t)
            && preg_match('/(^|\s)2\s/', $t)
            && preg_match('/(^|\s)3\s/', $t);
    }
}
