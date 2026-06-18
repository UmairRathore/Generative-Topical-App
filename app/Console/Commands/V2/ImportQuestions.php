<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Paper;
use App\Models\V2\Question;
use App\Models\V2\Subject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| v2:import-questions
|--------------------------------------------------------------------------
| Imports the Generative-Topical pipeline output into the V2 question bank.
|
| Source : storage/Generative-Topical/output/papers/<stem>/questions.json
| Targets: v2_papers, v2_questions, v2_question_options, v2_question_images
|
| Images: built from each question's "assets" (question diagrams + option
| images) PLUS the "option_table.image_path" (the table picture, which is not
| in assets). All paths point inside images_final/. With --copy-images the
| files are copied to storage/app/public/v2/questions/<stem>/ and the stored
| path is web-relative ("v2/questions/<stem>/<file>") for Storage::url().
|
| correct_answer / topic_id are read through as-is: answers come from running
| extract_marks.py first; topics from the tagging pass. Both are nullable.
*/
class ImportQuestions extends Command
{
    protected $signature = 'v2:import-questions
        {path? : Path to output/papers (default: storage/Generative-Topical/output/papers)}
        {--subject=9702 : Subject code in v2_subjects}
        {--copy-images : Copy images_final/* into storage/app/public/v2/questions/}
        {--fresh : Delete existing v2 question data for this subject before importing}';

    protected $description = 'Import Generative-Topical questions into the V2 question bank';

    public function handle(): int
    {
        $papersRoot = $this->argument('path')
            ?: storage_path('Generative-Topical/output/papers');
        $papersRoot = rtrim($papersRoot, '/\\');
        $outputRoot = dirname($papersRoot); // image_path values are relative to output/

        if (! is_dir($papersRoot)) {
            $this->error("Papers directory not found: {$papersRoot}");
            return self::FAILURE;
        }

        $subject = Subject::where('code', $this->option('subject'))->first();
        if (! $subject) {
            $this->error("Subject {$this->option('subject')} not found in v2_subjects. Run V2SubjectsSeeder.");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            // FK cascade deletes questions -> options/images.
            $deleted = DB::table('v2_papers')->where('subject_id', $subject->id)->delete();
            $this->warn("--fresh: removed {$deleted} existing papers (and their questions) for subject {$subject->code}.");
        }

        // Re-importing on top of existing data would collide on the
        // UNIQUE(paper_id, question_number) index and abort each paper. Fail fast
        // with a clear instruction instead of surfacing a raw SQL exception.
        $existingPapers = DB::table('v2_papers')->where('subject_id', $subject->id)->count();
        if ($existingPapers > 0 && ! $this->option('fresh')) {
            $this->error("Subject {$subject->code} already has {$existingPapers} imported papers. Re-run with --fresh to replace them (a plain re-run would fail on the UNIQUE(paper_id, question_number) constraint).");
            return self::FAILURE;
        }

        $dirs = collect(File::directories($papersRoot))
            ->filter(fn ($d) => is_file($d.'/questions.json'))
            ->sort()
            ->values();

        if ($dirs->isEmpty()) {
            $this->error("No <stem>/questions.json found under {$papersRoot}.");
            return self::FAILURE;
        }

        $totals = ['papers' => 0, 'questions' => 0, 'options' => 0, 'images' => 0, 'answered' => 0];
        $bar = $this->output->createProgressBar($dirs->count());
        $bar->start();

        foreach ($dirs as $dir) {
            $stem = basename($dir);
            $json = json_decode(File::get($dir.'/questions.json'), true);
            if (! $json || empty($json['questions'])) {
                $this->warn("  skipped {$stem}: empty/invalid questions.json");
                $bar->advance();
                continue;
            }

            DB::transaction(function () use ($json, $stem, $subject, $outputRoot, &$totals) {
                $paper = $this->importPaper($json['paper'] ?? [], $stem, $subject);
                $totals['papers']++;

                foreach ($json['questions'] as $qData) {
                    $counts = $this->importQuestion($qData, $paper, $subject->id, $stem, $outputRoot);
                    $totals['questions']++;
                    $totals['options'] += $counts['options'];
                    $totals['images']  += $counts['images'];
                    $totals['answered'] += $counts['answered'];
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Import complete for subject {$subject->code} ({$subject->name}):");
        $this->table(['metric', 'count'], collect($totals)->map(fn ($v, $k) => [$k, $v])->all());

        if ($totals['answered'] < $totals['questions']) {
            $missing = $totals['questions'] - $totals['answered'];
            $this->warn("{$missing} questions have no correct_answer. Run extract_marks.py to populate answers, then re-import with --fresh.");
        }
        if (! $this->option('copy-images')) {
            $this->warn('Images were not copied. Re-run with --copy-images (and `php artisan storage:link`) so the app can serve diagrams.');
        }

        return self::SUCCESS;
    }

    /** Upsert one paper row, deriving year/session/variant from the folder stem. */
    private function importPaper(array $meta, string $stem, Subject $subject): Paper
    {
        $year = null;
        $sessionCode = null;
        $variant = null;
        $paperNumber = null;

        // Subject-agnostic CAIE stem: <code>_<session><yy>_qp_<variant>
        // e.g. 9702_m16_qp_12 (A Level) or 5054_w19_qp_11 (O Level).
        if (preg_match('/^(\d+)_([a-z])(\d{2})_qp_(\d+)$/i', $stem, $m)) {
            $sessionCode = strtolower($m[2]);
            $year        = 2000 + (int) $m[3];
            $variant     = $m[4];
            $paperNumber = (int) substr($m[4], 0, 1);
        }

        return Paper::updateOrCreate(
            ['source_file' => ($meta['source_file'] ?? $stem.'.pdf')],
            [
                'subject_id'      => $subject->id,
                'source_paper'    => $stem,
                // Always the syllabus code of the subject being imported (5054 / 9702 / …),
                // so the shared tables stay cleanly partitioned by subject + level.
                'subject_code'    => $subject->code,
                // paper_code is NOT NULL — derive a subject-generic fallback if the JSON omits it.
                'paper_code'      => $meta['paper_code'] ?? trim($subject->code.'/'.(string) $variant, '/'),
                'paper_number'    => $paperNumber,
                'variant'         => $variant,
                'session_code'    => $sessionCode,
                'session_label'   => $meta['session'] ?? null,
                'year'            => $year,
                'total_questions' => $meta['total_questions'] ?? count($meta),
            ]
        );
    }

    /** Insert one question with its options + images. Returns per-question counts. */
    private function importQuestion(array $q, Paper $paper, int $subjectId, string $stem, string $outputRoot): array
    {
        $optionTable = null;
        if (! empty($q['option_table']) && is_array($q['option_table'])) {
            $optionTable = [
                'headers' => $q['option_table']['headers'] ?? [],
                'rows'    => $q['option_table']['rows'] ?? [],
            ];
        }

        $answer = $q['correct_answer'] ?? null;
        $answer = ($answer !== null && $answer !== '') ? strtoupper(substr($answer, 0, 1)) : null;

        // A question with neither options nor an option-table is unanswerable
        // (incomplete source extraction) — flag it so it surfaces for review and
        // is never drawn into a generated test (ExamService requires has('options')).
        $incomplete = empty($q['options']) && empty($q['option_table']);

        // Strip Cambridge end-of-paper footer text the extractor may have swept in
        // (keeps the original if a strip would empty the field).
        $stripper = app(\App\Services\V2\QuestionBoilerplateStripper::class);
        $clean = function (?string $text) use ($stripper): ?string {
            $stripped = $stripper->strip($text);
            return ($text !== null && $text !== '' && trim((string) $stripped) === '') ? $text : $stripped;
        };

        $question = Question::create([
            'paper_id'        => $paper->id,
            'subject_id'      => $subjectId,
            'topic_id'        => null,
            'subtopic_id'     => null,
            'year'            => $paper->year,
            'question_number' => $q['question_number'],
            'question_text'   => $clean($q['question_text'] ?? null),
            'text_before'     => $clean($q['image_between_question_before_text'] ?? null),
            'text_after'      => $clean($q['image_between_question_after_text'] ?? null),
            'layout_type'     => $q['layout_type'] ?? 'text_only',
            'correct_answer'  => $answer,
            'option_table'    => $optionTable,
            'marks'           => 1,
            'page_start'      => $q['page_start'] ?? null,
            'page_end'        => $q['page_end'] ?? null,
            'needs_review'    => (bool) ($q['needs_review'] ?? false) || $incomplete,
            'warnings'        => $q['warnings'] ?? null,
            'source_paper'    => $stem,
        ]);

        // ---- Options (A/B/C/D) ----
        $optionRows = [];
        foreach (($q['options'] ?? []) as $i => $opt) {
            $optionRows[] = [
                'question_id' => $question->id,
                'label'       => strtoupper(substr($opt['label'] ?? chr(65 + $i), 0, 1)),
                'text'        => $opt['text'] ?? '',
                'has_image'   => ! empty($opt['images']),
                'sort_order'  => $i,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        if ($optionRows) {
            DB::table('v2_question_options')->insert($optionRows);
        }

        // ---- Images: assets[] (diagrams + option images) + the table image ----
        $imageRows = [];
        $order = 0;
        foreach (($q['assets'] ?? []) as $asset) {
            $imageRows[] = $this->imageRow($question->id, $asset, $stem, $order++, $outputRoot);
        }
        if (! empty($q['option_table']['image_path'])) {
            $imageRows[] = $this->imageRow($question->id, [
                'id'         => Str::beforeLast(basename($q['option_table']['image_path']), '.'),
                'image_path' => $q['option_table']['image_path'],
                'role'       => 'table',
                'bbox'       => $q['option_table']['bbox'] ?? null,
                'diagram_labels' => $q['option_table']['diagram_labels'] ?? null,
            ], $stem, $order++, $outputRoot);
        }
        if ($imageRows) {
            DB::table('v2_question_images')->insert($imageRows);
        }

        // Repair "option_images" questions whose option crops are really duplicates
        // of the one question figure (e.g. "at which point on the graph…"). Needs
        // the image files on disk, so only when we've copied them.
        if ($this->option('copy-images')) {
            app(\App\Services\V2\DuplicateOptionImageFixer::class)
                ->fixQuestion($question->load('images', 'options'));
        }

        return [
            'options'  => count($optionRows),
            'images'   => count($imageRows),
            'answered' => $answer !== null ? 1 : 0,
        ];
    }

    /** Build a v2_question_images row, copying the file when --copy-images is set. */
    private function imageRow(int $questionId, array $img, string $stem, int $order, string $outputRoot): array
    {
        $original = $img['image_path'] ?? '';
        $filename = basename($original);
        $webPath  = "v2/questions/{$stem}/{$filename}";
        $width = null;
        $height = null;

        if ($this->option('copy-images') && $original !== '') {
            $src = $outputRoot.'/'.ltrim($original, '/');
            $destDir = storage_path("app/public/v2/questions/{$stem}");
            if (is_file($src)) {
                File::ensureDirectoryExists($destDir);
                File::copy($src, $destDir.'/'.$filename);
                if ($size = @getimagesize($destDir.'/'.$filename)) {
                    [$width, $height] = $size;
                }
            }
        }

        $role = $img['role'] ?? 'question_image_between_text';
        $optionLabel = null;
        if ($role === 'option_image' && preg_match('/option_([A-D])/i', $img['id'] ?? '', $m)) {
            $optionLabel = strtoupper($m[1]);
        }

        return [
            'question_id'    => $questionId,
            'external_id'    => $img['id'] ?? null,
            'image_path'     => $webPath,
            'role'           => $role,
            'option_label'   => $optionLabel,
            'page'           => $img['page'] ?? null,
            'bbox'           => isset($img['bbox']) ? json_encode($img['bbox']) : null,
            'width'          => $width,
            'height'         => $height,
            'caption'        => $img['caption'] ?? ($img['caption_short'] ?? null),
            'ocr_text'       => $img['ocr_text'] ?? null,
            'confidence'     => $img['confidence'] ?? null,
            'diagram_labels' => isset($img['diagram_labels']) ? json_encode($img['diagram_labels']) : null,
            'sort_order'     => $order,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];
    }
}
