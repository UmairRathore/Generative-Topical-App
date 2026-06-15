<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| v2:import-answers
|--------------------------------------------------------------------------
| Reads correct_answer from the import-source questions.json files and writes
| it into v2_questions (matched by source_paper + question_number). Run after
| answers have been synced into the JSON (sync_answers.py). Idempotent; does
| NOT touch topics/options/images, so tagging is preserved.
*/
class ImportAnswers extends Command
{
    protected $signature = 'v2:import-answers
        {path? : Path to output/papers (default: storage/Generative-Topical/output/papers)}';

    protected $description = 'Import correct answers from questions.json into v2_questions';

    public function handle(): int
    {
        $root = rtrim($this->argument('path') ?: storage_path('Generative-Topical/output/papers'), '/\\');
        if (! is_dir($root)) {
            $this->error("Papers directory not found: {$root}");
            return self::FAILURE;
        }

        $dirs = collect(File::directories($root))
            ->filter(fn ($d) => is_file($d.'/questions.json'))->sort()->values();

        $papers = 0;
        $updated = 0;
        $bar = $this->output->createProgressBar($dirs->count());
        $bar->start();

        foreach ($dirs as $dir) {
            $stem = basename($dir);
            $json = json_decode(File::get($dir.'/questions.json'), true);
            $questions = $json['questions'] ?? [];

            DB::transaction(function () use ($questions, $stem, &$updated) {
                foreach ($questions as $q) {
                    $answer = $q['correct_answer'] ?? null;
                    if (! $answer) {
                        continue;
                    }
                    $updated += DB::table('v2_questions')
                        ->where('source_paper', $stem)
                        ->where('question_number', $q['question_number'])
                        ->update([
                            'correct_answer' => strtoupper(substr($answer, 0, 1)),
                            'updated_at'     => now(),
                        ]);
                }
            });

            $papers++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $total = DB::table('v2_questions')->count();
        $answered = DB::table('v2_questions')->whereNotNull('correct_answer')->count();
        $this->info("Answers imported from {$papers} papers; {$updated} questions updated.");
        $this->line("v2_questions with an answer: {$answered} / {$total}");

        return self::SUCCESS;
    }
}
