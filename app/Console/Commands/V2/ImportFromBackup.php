<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Paper;
use App\Models\V2\Question;
use App\Models\V2\Subject;
use App\Models\V2\Subtopic;
use App\Models\V2\Topic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| v2:import-from-backup
|--------------------------------------------------------------------------
| Restores a v2:export-questions snapshot back into the DB. The whole point:
| re-apply the expensive layers (topic/subtopic tags + mark-scheme answers)
| WITHOUT re-running any AI / extract_marks / tagging pass.
|
| Source: storage/app/questions/backups/<stem>/questions.json (+ images/).
|
| Modes:
|   --mode=tags  (default) Restore topic_id, subtopic_id, correct_answer, marks,
|                difficulty, status, needs_review onto questions that ALREADY
|                exist (matched by source_paper + question_number). Creates
|                nothing. Use after a `v2:import-questions --fresh` re-import.
|   --mode=full  Also rebuild any MISSING papers/questions/options/images from
|                the JSON and copy the backed-up image files back into
|                storage/app/public/<image_path>. Use for disaster recovery.
|
| Tags are re-mapped through v2_topics/v2_subtopics by external_id (scoped to
| the subject), exactly as v2:tag-questions does — so they survive row-id churn.
| A tag whose external_id no longer resolves is left null and counted.
*/
class ImportFromBackup extends Command
{
    protected $signature = 'v2:import-from-backup
        {--path= : Backup base dir (default: storage/app/questions/backups)}
        {--subject= : Only restore this subject code (e.g. 9702)}
        {--paper= : Only restore this source_paper stem (e.g. 9702_m16_qp_12)}
        {--mode=tags : "tags" (restore tags/answers onto existing questions) or "full" (also rebuild missing rows + image files)}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Restore topic tags + answers (and optionally full rows/images) from a v2:export-questions backup — no AI re-run';

    /** Cache of external_id => id maps, keyed by subject_id. */
    private array $topicMaps = [];
    private array $subtopicMaps = [];

    public function handle(): int
    {
        $base = rtrim($this->option('path') ?: storage_path('app/questions/backups'), '/\\');
        $mode = $this->option('mode');
        $dry = (bool) $this->option('dry-run');

        if (! in_array($mode, ['tags', 'full'], true)) {
            $this->error("Invalid --mode={$mode}. Use 'tags' or 'full'.");
            return self::FAILURE;
        }
        if (! is_dir($base)) {
            $this->error("Backup directory not found: {$base}");
            return self::FAILURE;
        }

        $dirs = collect(File::directories($base))
            ->filter(fn ($d) => is_file($d.'/questions.json'))
            ->when($this->option('paper'), fn ($c, $stem = null) => $c->filter(fn ($d) => basename($d) === $this->option('paper')))
            ->sort()->values();

        if ($dirs->isEmpty()) {
            $this->error("No <stem>/questions.json found under {$base}.");
            return self::FAILURE;
        }

        $t = ['papers' => 0, 'questions_matched' => 0, 'questions_created' => 0,
            'tags_restored' => 0, 'tags_unresolved' => 0, 'answers_restored' => 0,
            'options_created' => 0, 'images_created' => 0, 'images_copied' => 0, 'skipped_missing' => 0];

        $bar = $this->output->createProgressBar($dirs->count());
        $bar->start();

        foreach ($dirs as $dir) {
            $json = json_decode(File::get($dir.'/questions.json'), true);
            $paperMeta = $json['paper'] ?? null;
            if (! $paperMeta) {
                $bar->advance();
                continue;
            }

            $subjectCode = $paperMeta['subject_code'] ?? ($paperMeta['subject']['code'] ?? null);
            if ($this->option('subject') && $subjectCode !== $this->option('subject')) {
                $bar->advance();
                continue;
            }

            $subject = Subject::where('code', $subjectCode)->first();
            if (! $subject) {
                $this->warn("  {$paperMeta['source_paper']}: subject {$subjectCode} not in v2_subjects — skipped.");
                $bar->advance();
                continue;
            }

            $this->restorePaper($json, $dir, $subject, $mode, $dry, $t);
            $t['papers']++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(($dry ? '[DRY RUN] ' : '')."Restore complete (mode={$mode}):");
        $this->table(['metric', 'count'], collect($t)->map(fn ($v, $k) => [$k, $v])->all());

        if ($t['tags_unresolved'] > 0) {
            $this->warn("{$t['tags_unresolved']} tags could not be re-mapped (external_id missing from v2_topics/subtopics). Ensure the syllabus is seeded for the subject.");
        }
        if ($mode === 'tags' && $t['skipped_missing'] > 0) {
            $this->warn("{$t['skipped_missing']} questions in the backup have no matching DB row. Re-run `v2:import-questions` first, or use --mode=full.");
        }

        return self::SUCCESS;
    }

    private function restorePaper(array $json, string $dir, Subject $subject, string $mode, bool $dry, array &$t): void
    {
        $meta = $json['paper'];
        $stem = $meta['source_paper'];

        $paper = Paper::where('source_file', $meta['source_file'] ?? $stem.'.pdf')->first();
        if (! $paper) {
            if ($mode !== 'full') {
                // tags mode never creates papers; every question will count as missing.
                foreach (($json['questions'] ?? []) as $q) {
                    $t['skipped_missing']++;
                }
                return;
            }
            $paper = $dry ? new Paper($this->paperAttrs($meta, $subject)) : Paper::create($this->paperAttrs($meta, $subject));
        }

        $topicMap = $this->topicMap($subject->id);
        $subtopicMap = $this->subtopicMap($subject->id);

        foreach (($json['questions'] ?? []) as $q) {
            $existing = $paper->exists
                ? Question::where('paper_id', $paper->id)->where('question_number', $q['question_number'])->first()
                : null;

            $tagIds = $this->resolveTags($q, $topicMap, $subtopicMap, $t);
            $answer = $q['marking']['correct_answer'] ?? ($q['correct_answer'] ?? null);

            if ($existing) {
                $t['questions_matched']++;
                $this->applyRestorable($existing, $q, $tagIds, $answer, $dry, $t);
                continue;
            }

            if ($mode !== 'full') {
                $t['skipped_missing']++;
                continue;
            }

            $this->createQuestion($q, $paper, $subject, $dir, $tagIds, $answer, $dry, $t);
        }
    }

    /** Restore tags/answers/status onto an existing question (the no-AI re-apply). */
    private function applyRestorable(Question $q, array $data, array $tagIds, ?string $answer, bool $dry, array &$t): void
    {
        $attrs = [
            'topic_id'     => $tagIds['topic_id'],
            'subtopic_id'  => $tagIds['subtopic_id'],
            'correct_answer' => $answer,
            'marks'        => $data['marking']['marks'] ?? ($data['marks'] ?? $q->marks),
            'difficulty'   => $data['difficulty'] ?? $q->difficulty,
            'status'       => $data['status'] ?? $q->status,
            'needs_review' => (bool) ($data['needs_review'] ?? $q->needs_review),
        ];

        if ($tagIds['topic_id'] !== null) {
            $t['tags_restored']++;
        }
        if ($answer !== null) {
            $t['answers_restored']++;
        }

        if (! $dry) {
            $q->update($attrs);
        }
    }

    /** Recreate a missing question with its options + images, copying image files back. */
    private function createQuestion(array $q, Paper $paper, Subject $subject, string $dir, array $tagIds, ?string $answer, bool $dry, array &$t): void
    {
        $t['questions_created']++;
        if ($tagIds['topic_id'] !== null) {
            $t['tags_restored']++;
        }
        if ($answer !== null) {
            $t['answers_restored']++;
        }

        $t['options_created'] += count($q['options'] ?? []);
        $t['images_created'] += count($q['images'] ?? []);

        if ($dry) {
            // still account for image copies that would happen
            foreach (($q['images'] ?? []) as $img) {
                if (is_file($dir.'/'.($img['backup_path'] ?? ''))) {
                    $t['images_copied']++;
                }
            }
            return;
        }

        $question = Question::create([
            'paper_id'        => $paper->id,
            'subject_id'      => $subject->id,
            'topic_id'        => $tagIds['topic_id'],
            'subtopic_id'     => $tagIds['subtopic_id'],
            'year'            => $paper->year,
            'question_number' => $q['question_number'],
            'question_text'   => $q['question_text'] ?? null,
            'text_before'     => $q['text_before'] ?? null,
            'text_after'      => $q['text_after'] ?? null,
            'layout_type'     => $q['layout_type'] ?? 'text_only',
            'correct_answer'  => $answer,
            'option_table'    => $q['option_table'] ?? null,
            'marks'           => $q['marking']['marks'] ?? 1,
            'difficulty'      => $q['difficulty'] ?? null,
            'page_start'      => $q['page_start'] ?? null,
            'page_end'        => $q['page_end'] ?? null,
            'needs_review'    => (bool) ($q['needs_review'] ?? false),
            'warnings'        => $q['warnings'] ?? null,
            'status'          => $q['status'] ?? 'active',
            'source_paper'    => $paper->source_paper,
        ]);

        $optionRows = [];
        foreach (($q['options'] ?? []) as $i => $o) {
            $optionRows[] = [
                'question_id' => $question->id,
                'label'       => $o['label'],
                'text'        => $o['text'] ?? '',
                'has_image'   => (bool) ($o['has_image'] ?? false),
                'sort_order'  => $o['sort_order'] ?? $i,
                'created_at'  => now(), 'updated_at' => now(),
            ];
        }
        if ($optionRows) {
            DB::table('v2_question_options')->insert($optionRows);
        }

        $imageRows = [];
        foreach (($q['images'] ?? []) as $img) {
            $imageRows[] = [
                'question_id'    => $question->id,
                'external_id'    => $img['external_id'] ?? null,
                'image_path'     => $img['image_path'],
                'role'           => $img['role'] ?? 'question_image_between_text',
                'option_label'   => $img['option_label'] ?? null,
                'page'           => $img['page'] ?? null,
                'bbox'           => isset($img['bbox']) ? json_encode($img['bbox']) : null,
                'width'          => $img['width'] ?? null,
                'height'         => $img['height'] ?? null,
                'caption'        => $img['caption'] ?? null,
                'ocr_text'       => $img['ocr_text'] ?? null,
                'confidence'     => $img['confidence'] ?? null,
                'diagram_labels' => isset($img['diagram_labels']) ? json_encode($img['diagram_labels']) : null,
                'sort_order'     => $img['sort_order'] ?? 0,
                'created_at'     => now(), 'updated_at' => now(),
            ];

            // Copy the backed-up file back to where the app serves it.
            $srcFile = $dir.'/'.($img['backup_path'] ?? '');
            if (is_file($srcFile)) {
                $dest = storage_path('app/public/'.ltrim($img['image_path'], '/'));
                File::ensureDirectoryExists(dirname($dest));
                File::copy($srcFile, $dest);
                $t['images_copied']++;
            }
        }
        if ($imageRows) {
            DB::table('v2_question_images')->insert($imageRows);
        }
    }

    /** Map the JSON question's topic/subtopic external_ids to live v2 primary keys. */
    private function resolveTags(array $q, $topicMap, $subtopicMap, array &$t): array
    {
        $topicExt = $q['topic']['external_id'] ?? null;
        $subExt = $q['subtopic']['external_id'] ?? null;

        if ($topicExt === null) {
            return ['topic_id' => null, 'subtopic_id' => null];
        }

        $topicId = $topicMap[(string) $topicExt] ?? null;
        if ($topicId === null) {
            $t['tags_unresolved']++;
            return ['topic_id' => null, 'subtopic_id' => null];
        }

        return [
            'topic_id'    => $topicId,
            'subtopic_id' => $subExt !== null ? ($subtopicMap[(string) $subExt] ?? null) : null,
        ];
    }

    private function topicMap(int $subjectId)
    {
        return $this->topicMaps[$subjectId] ??= Topic::where('subject_id', $subjectId)->pluck('id', 'external_id');
    }

    private function subtopicMap(int $subjectId)
    {
        return $this->subtopicMaps[$subjectId] ??= Subtopic::whereIn('topic_id', $this->topicMap($subjectId)->values())
            ->pluck('id', 'external_id');
    }

    private function paperAttrs(array $meta, Subject $subject): array
    {
        return [
            'subject_id'      => $subject->id,
            'source_file'     => $meta['source_file'] ?? $meta['source_paper'].'.pdf',
            'source_paper'    => $meta['source_paper'],
            'subject_code'    => $meta['subject_code'] ?? $subject->code,
            'paper_code'      => $meta['paper_code'] ?? trim($subject->code.'/'.($meta['variant'] ?? ''), '/'),
            'paper_number'    => $meta['paper_number'] ?? null,
            'variant'         => $meta['variant'] ?? null,
            'session_code'    => $meta['session_code'] ?? null,
            'session_label'   => $meta['session_label'] ?? null,
            'year'            => $meta['year'] ?? null,
            'total_questions' => $meta['total_questions'] ?? 0,
        ];
    }
}
