<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Paper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| v2:export-questions
|--------------------------------------------------------------------------
| Reverses v2:import-questions: snapshots the live V2 question bank back out
| to disk, one folder per paper, in (a superset of) the import "paper format".
|
| For each paper it writes:
|   storage/app/questions/backups/<stem>/questions.json   (DB reconstruction)
|   storage/app/questions/backups/<stem>/images/<file>    (asset file backups)
|
| The JSON carries everything the DB holds — markings (correct_answer + marks),
| status, difficulty, review/warnings, topic/subtopic tags, every option, every
| image with its metadata (role, bbox, dimensions, caption, ocr, confidence,
| diagram labels), and any quality flags. A top-level index.json manifests the
| whole run.
|
| Source of asset files: storage/app/public/<image_path>, where image_path is
| the web-relative "v2/questions/<stem>/<file>" stored at import time.
*/
class ExportQuestions extends Command
{
    protected $signature = 'v2:export-questions
        {--subject= : Only export this subject code (e.g. 9702). Default: every subject that has papers}
        {--paper= : Only export this source_paper stem (e.g. 9702_m16_qp_12)}
        {--out= : Output base directory (default: storage/app/questions/backups)}
        {--no-assets : Write JSON only; do not copy image files}
        {--with-trashed : Include soft-deleted questions}';

    protected $description = 'Export the V2 question bank (questions, markings, assets) to per-paper JSON + image backups';

    public function handle(): int
    {
        $outBase = rtrim($this->option('out') ?: storage_path('app/questions/backups'), '/\\');
        $publicRoot = storage_path('app/public'); // image_path is relative to this
        $exportedAt = now()->toIso8601String();
        $copyAssets = ! $this->option('no-assets');

        $papers = Paper::query()
            ->with(['subject'])
            ->when($this->option('subject'), fn ($q, $code) => $q->where('subject_code', $code))
            ->when($this->option('paper'), fn ($q, $stem) => $q->where('source_paper', $stem))
            ->orderBy('subject_code')
            ->orderBy('source_paper')
            ->get();

        if ($papers->isEmpty()) {
            $this->error('No papers matched the given filters.');
            return self::FAILURE;
        }

        File::ensureDirectoryExists($outBase);

        $totals = ['papers' => 0, 'questions' => 0, 'options' => 0, 'images' => 0,
            'answered' => 0, 'assets_copied' => 0, 'assets_missing' => 0];
        $manifest = [];

        $bar = $this->output->createProgressBar($papers->count());
        $bar->start();

        foreach ($papers as $paper) {
            $stem = $paper->source_paper;
            $paperDir = $outBase.'/'.$stem;
            File::ensureDirectoryExists($paperDir);

            $questions = $paper->questions()
                ->when($this->option('with-trashed'), fn ($q) => $q->withTrashed())
                ->with(['options', 'images', 'topic', 'subtopic', 'flags'])
                ->orderBy('question_number')
                ->get();

            $payload = $this->buildPaperPayload($paper, $questions, $exportedAt, $copyAssets, $publicRoot, $paperDir, $totals);

            File::put(
                $paperDir.'/questions.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $manifest[] = [
                'source_paper' => $stem,
                'subject_code' => $paper->subject_code,
                'year'         => $paper->year,
                'session_code' => $paper->session_code,
                'variant'      => $paper->variant,
                'questions'    => $questions->count(),
                'images'       => $payload['asset_summary']['images_total'],
                'path'         => $stem.'/questions.json',
            ];

            $totals['papers']++;
            $totals['questions'] += $questions->count();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        File::put($outBase.'/index.json', json_encode([
            'exported_at'    => $exportedAt,
            'schema_version' => 'v2-export-1',
            'assets_copied'  => $copyAssets,
            'with_trashed'   => (bool) $this->option('with-trashed'),
            'totals'         => $totals,
            'papers'         => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Export complete → {$outBase}");
        $this->table(['metric', 'count'], collect($totals)->map(fn ($v, $k) => [$k, $v])->all());

        if ($totals['assets_missing'] > 0) {
            $this->warn("{$totals['assets_missing']} image files referenced in the DB were not found on disk (logged per-paper in asset_summary.missing_files).");
        }

        return self::SUCCESS;
    }

    /** Reconstruct one paper's full export payload from its DB rows. */
    private function buildPaperPayload(Paper $paper, $questions, string $exportedAt, bool $copyAssets, string $publicRoot, string $paperDir, array &$totals): array
    {
        $imagesTotal = 0;
        $imagesCopied = 0;
        $imagesMissing = 0;
        $missingFiles = [];

        $questionPayloads = $questions->map(function ($q) use ($copyAssets, $publicRoot, $paperDir, &$totals, &$imagesTotal, &$imagesCopied, &$imagesMissing, &$missingFiles) {
            $totals['options'] += $q->options->count();
            if ($q->correct_answer !== null) {
                $totals['answered']++;
            }

            $images = $q->images->map(function ($img) use ($copyAssets, $publicRoot, $paperDir, &$totals, &$imagesTotal, &$imagesCopied, &$imagesMissing, &$missingFiles) {
                $imagesTotal++;
                $totals['images']++;
                $filename = basename($img->image_path);
                $src = $publicRoot.'/'.ltrim($img->image_path, '/');
                $exists = is_file($src);

                if (! $exists) {
                    $imagesMissing++;
                    $totals['assets_missing']++;
                    $missingFiles[] = $img->image_path;
                } elseif ($copyAssets) {
                    $destDir = $paperDir.'/images';
                    File::ensureDirectoryExists($destDir);
                    File::copy($src, $destDir.'/'.$filename);
                    $imagesCopied++;
                    $totals['assets_copied']++;
                }

                return [
                    'id'             => $img->id,
                    'external_id'    => $img->external_id,
                    'file'           => $filename,
                    'image_path'     => $img->image_path,
                    'backup_path'    => 'images/'.$filename,
                    'role'           => $img->role,
                    'option_label'   => $img->option_label,
                    'page'           => $img->page,
                    'bbox'           => $img->bbox,
                    'width'          => $img->width,
                    'height'         => $img->height,
                    'caption'        => $img->caption,
                    'ocr_text'       => $img->ocr_text,
                    'confidence'     => $img->confidence,
                    'diagram_labels' => $img->diagram_labels,
                    'sort_order'     => $img->sort_order,
                    'file_exists'    => $exists,
                ];
            })->all();

            return [
                'id'              => $q->id,
                'question_number' => $q->question_number,
                'page_start'      => $q->page_start,
                'page_end'        => $q->page_end,
                'layout_type'     => $q->layout_type,
                'question_text'   => $q->question_text,
                'text_before'     => $q->text_before,
                'text_after'      => $q->text_after,
                'marking'         => [
                    'correct_answer' => $q->correct_answer,
                    'marks'          => $q->marks,
                ],
                'difficulty'      => $q->difficulty,
                'status'          => $q->status,
                'needs_review'    => (bool) $q->needs_review,
                'warnings'        => $q->warnings,
                'deleted_at'      => optional($q->deleted_at)->toIso8601String(),
                'topic'           => $q->topic ? [
                    'id' => $q->topic->id, 'external_id' => $q->topic->external_id, 'title' => $q->topic->title,
                ] : null,
                'subtopic'        => $q->subtopic ? [
                    'id' => $q->subtopic->id, 'external_id' => $q->subtopic->external_id, 'title' => $q->subtopic->title,
                ] : null,
                'option_table'    => $q->option_table,
                'options'         => $q->options->map(fn ($o) => [
                    'id'         => $o->id,
                    'label'      => $o->label,
                    'text'       => $o->text,
                    'has_image'  => (bool) $o->has_image,
                    'sort_order' => $o->sort_order,
                ])->all(),
                'images'          => $images,
                'flags'           => $q->flags->map(fn ($f) => [
                    'id'         => $f->id,
                    'level'      => $f->level,
                    'reason'     => $f->reason,
                    'note'       => $f->note,
                    'status'     => $f->status,
                    'created_at' => optional($f->created_at)->toIso8601String(),
                ])->all(),
            ];
        })->all();

        return [
            'exported_at'    => $exportedAt,
            'schema_version' => 'v2-export-1',
            'paper'          => [
                'id'              => $paper->id,
                'source_file'     => $paper->source_file,
                'source_paper'    => $paper->source_paper,
                'subject'         => $paper->subject ? [
                    'id'    => $paper->subject->id,
                    'code'  => $paper->subject->code,
                    'name'  => $paper->subject->name,
                    'level' => $paper->subject->level,
                ] : null,
                'subject_code'    => $paper->subject_code,
                'paper_code'      => $paper->paper_code,
                'paper_number'    => $paper->paper_number,
                'variant'         => $paper->variant,
                'session_code'    => $paper->session_code,
                'session_label'   => $paper->session_label,
                'year'            => $paper->year,
                'total_questions' => $paper->total_questions,
            ],
            'questions'      => $questionPayloads,
            'asset_summary'  => [
                'images_total'   => $imagesTotal,
                'images_copied'  => $imagesCopied,
                'images_missing' => $imagesMissing,
                'missing_files'  => $missingFiles,
            ],
        ];
    }
}
