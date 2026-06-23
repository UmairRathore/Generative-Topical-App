<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:detach-reference-images
|--------------------------------------------------------------------------
| The CAIE Periodic Table / Data Sheet is a full-page reference printed next to
| the last question, so the extractor wrongly attached it to that question as a
| stem diagram. This re-roles those full-page reference pages to 'reference' so
| the question renderer (stemBlocks, which only reads question_image_* roles)
| skips them — the files stay on disk, nothing is deleted, fully reversible.
|
| Detection (conservative): a Chemistry (9701/5070) stem image that is a large
| PORTRAIT full page AND sits on the LAST question of its paper — the Periodic
| Table is always printed after the final question, so this targets it precisely
| and never touches a mid-paper diagram.
*/
class DetachReferenceImages extends Command
{
    protected $signature = 'v2:detach-reference-images {--dry-run : List what would change without writing}';

    protected $description = 'Re-role full-page Periodic Table / Data Sheet pages off questions so they are not shown inline';

    private const STEM_ROLES = ['question_image_between_text', 'question_image_after_text'];
    private const CHEM = ['9701', '5070'];

    public function handle(): int
    {
        $candidates = DB::table('v2_question_images as i')
            ->join('v2_questions as q', 'q.id', '=', 'i.question_id')
            ->join('v2_subjects as s', 's.id', '=', 'q.subject_id')
            ->whereIn('s.code', self::CHEM)
            ->whereIn('i.role', self::STEM_ROLES)
            ->where('i.height', '>=', 1400)
            ->where('i.width', '>=', 800)
            ->whereColumn('i.height', '>=', 'i.width') // portrait full page
            // ...and only on the LAST question of its paper (where the table is printed)
            ->whereRaw('q.question_number = (select max(q2.question_number) from v2_questions q2 where q2.paper_id = q.paper_id)')
            ->orderBy('q.source_paper')
            ->get(['i.id', 'i.width', 'i.height', 'q.source_paper', 'q.question_number', 's.code']);

        if ($candidates->isEmpty()) {
            $this->info('No full-page reference images found attached to questions.');

            return self::SUCCESS;
        }

        $this->info(($this->option('dry-run') ? '[DRY RUN] ' : '') . "Full-page reference pages attached to questions: {$candidates->count()}");
        foreach ($candidates->groupBy('code') as $code => $g) {
            $this->line("  {$code}: {$g->count()} (e.g. " . $g->first()->source_paper . ' Q' . $g->first()->question_number
                . ' ' . $g->first()->width . 'x' . $g->first()->height . ')');
        }

        if (! $this->option('dry-run')) {
            $ids = $candidates->pluck('id');
            $n = DB::table('v2_question_images')->whereIn('id', $ids)->update(['role' => 'reference']);
            $this->info("Re-roled {$n} images to 'reference' (removed from question rendering; files kept).");
        }

        return self::SUCCESS;
    }
}
