<?php

namespace App\Console\Commands;

use App\Services\Questions\QuestionEnrichmentService;
use App\Services\Syllabus\SyllabusLoader;
use Illuminate\Console\Command;

class EnrichQuestionTopicsCommand extends Command
{
    protected $signature = 'questions:enrich-topics
        {--paper=* : One or more paper stems (e.g. 9702_m17_qp_12). Repeatable.}
        {--all : Process every paper in the source.}
        {--from-json : Read questions from storage/output/papers/{stem}/questions.json.}
        {--from-db : Read questions from the database (default).}
        {--dry-run : Run the classifier without writing to the DB.}
        {--force : Re-classify and overwrite existing tags/difficulty rows.}
        {--limit= : Limit questions per paper (useful for sampling).}
        {--syllabus= : Override syllabus JSON path.}
        {--export-json : Write storage/output/papers/{stem}/questions_enriched.json.}
        {--output-root= : Override storage/output root (where /papers/{stem} lives).}
    ';

    protected $description = 'Classify Cambridge A Level Physics questions against the syllabus and evaluate difficulty.';

    public function handle(QuestionEnrichmentService $service, SyllabusLoader $loader): int
    {
        $papers = (array) $this->option('paper');
        $all = (bool) $this->option('all');
        $fromJson = (bool) $this->option('from-json');
        $fromDb = (bool) $this->option('from-db');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $exportJson = (bool) $this->option('export-json');
        $syllabusOpt = $this->option('syllabus');
        $outputRoot = $this->option('output-root') ?: storage_path('output');

        if (! $all && empty($papers)) {
            $this->error('Provide --paper=<stem> (repeatable) or --all.');
            return self::FAILURE;
        }

        if ($fromJson && $fromDb) {
            $this->error('Use either --from-json or --from-db, not both.');
            return self::FAILURE;
        }
        $source = $fromJson ? 'json' : 'db';

        $syllabusPath = $loader->resolvePath($syllabusOpt ?: SyllabusLoader::DEFAULT_PATH);
        if (! is_file($syllabusPath)) {
            $this->error("Syllabus JSON not found: {$syllabusPath}");
            return self::FAILURE;
        }

        // Sync the syllabus into DB on real runs so the FK lookups succeed.
        // (Skip in dry-run; classifier still uses the in-memory tree.)
        if (! $dryRun) {
            $stats = $loader->sync($syllabusPath, SyllabusLoader::DEFAULT_CODE);
            $this->line(sprintf(
                '[syllabus] synced topics=%d subtopics=%d objectives=%d',
                $stats['topics'], $stats['subtopics'], $stats['objectives']
            ));
        }

        $service->loadSyllabus($syllabusPath);
        $this->line("[syllabus] loaded into classifier from {$syllabusPath}");
        $this->line(sprintf(
            '[mode] source=%s dry_run=%s force=%s export=%s%s',
            $source, $dryRun ? 'yes' : 'no', $force ? 'yes' : 'no',
            $exportJson ? 'yes' : 'no',
            $limit ? " limit={$limit}" : ''
        ));

        $opts = [
            'papers' => $papers,
            'all' => $all,
            'source' => $source,
            'dry_run' => $dryRun,
            'force' => $force,
            'limit' => $limit,
            'export_json' => $exportJson,
            'output_root' => $outputRoot,
        ];

        $start = microtime(true);
        $totals = $service->processPapers(
            $opts,
            onPaper: function (string $stem, array $r) {
                $this->line(sprintf(
                    '  paper=%s questions=%d tags+%d/upd%d low=%d diff+%d/upd%d skipped=%d%s',
                    $stem,
                    $r['questions'],
                    $r['tags_created'],
                    $r['tags_updated'],
                    $r['tags_low_confidence'],
                    $r['difficulty_created'],
                    $r['difficulty_updated'],
                    $r['skipped_existing'],
                    $r['export_path'] ? " export={$r['export_path']}" : ''
                ));
            }
        );
        $elapsed = number_format(microtime(true) - $start, 2);

        $this->newLine();
        $this->info('Enrichment complete:');
        $this->line(sprintf('  papers processed       = %d', $totals['papers']));
        $this->line(sprintf('  questions processed    = %d', $totals['questions']));
        $this->line(sprintf('  topic tags created     = %d', $totals['tags_created']));
        $this->line(sprintf('  topic tags updated     = %d', $totals['tags_updated']));
        $this->line(sprintf('  low-confidence tags    = %d', $totals['tags_low_confidence']));
        $this->line(sprintf('  difficulty created     = %d', $totals['difficulty_created']));
        $this->line(sprintf('  difficulty updated     = %d', $totals['difficulty_updated']));
        $this->line(sprintf('  skipped (existing)     = %d', $totals['skipped_existing']));
        $this->line(sprintf('  elapsed                = %s s', $elapsed));
        if (! empty($totals['exports'])) {
            $this->line('  exports:');
            foreach ($totals['exports'] as $p) {
                $this->line('    - '.$p);
            }
        }

        return self::SUCCESS;
    }
}
