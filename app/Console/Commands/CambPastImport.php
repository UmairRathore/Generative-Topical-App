<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Import\CambPastImportService;
use Illuminate\Console\Command;

class CambPastImport extends Command
{
    protected $signature = 'cambpast:import
        {path? : Optional path to a questions.json file or output root. Defaults to storage/output.}
        {--copy-assets : Reserved (assets are always copied for local public disk).}';

    protected $description = 'Import extractor output (questions.json + assets) into the CambPast database.';

    public function handle(CambPastImportService $service): int
    {
        $path = $this->argument('path');
        $defaultRoot = storage_path('output');
        $target = $path !== null ? $this->normalize($path) : $defaultRoot;

        if (! file_exists($target)) {
            $this->error("Path does not exist: {$target}");
            return self::FAILURE;
        }

        [$jsonPaths, $outputRoot] = $this->discoverJsonPaths($target, $defaultRoot);

        if (empty($jsonPaths)) {
            $this->warn("No questions.json files found under: {$target}");
            return self::SUCCESS;
        }

        $blockerMap = $this->loadBlockerMap($outputRoot);

        $batch = ImportBatch::create([
            'source_path' => $target,
            'source_file' => count($jsonPaths) === 1 ? $jsonPaths[0] : null,
            'status' => 'running',
            'total_papers' => count($jsonPaths),
            'started_at' => now(),
        ]);

        $totalImported = 0;
        $totalSkipped = 0;
        $totalQuestions = 0;
        $allErrors = [];

        foreach ($jsonPaths as $jsonPath) {
            $this->info("Importing: {$jsonPath}");
            $stem = basename(dirname($jsonPath));
            $blockers = $blockerMap[$stem] ?? [];

            $result = $service->importPaperFromJson($jsonPath, $outputRoot, $batch, $blockers);

            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
            $totalQuestions += $result['imported'] + $result['skipped'];

            $this->line(sprintf(
                '  imported=%d skipped=%d demo_safe=%d blockers=%d',
                $result['imported'], $result['skipped'], $result['demo_safe'], count($blockers),
            ));

            foreach ($result['errors'] as $err) {
                $this->warn('  '.$err);
                $allErrors[] = "[{$stem}] ".$err;
            }
        }

        $batch->forceFill([
            'status' => empty($allErrors) ? 'completed' : 'completed_with_errors',
            'total_questions' => $totalQuestions,
            'imported_questions' => $totalImported,
            'skipped_questions' => $totalSkipped,
            'errors' => $allErrors ?: null,
            'finished_at' => now(),
        ])->save();

        $this->info("Done. papers={$batch->total_papers} imported={$totalImported} skipped={$totalSkipped}");

        return self::SUCCESS;
    }

    /**
     * Returns [paper_stem => [question_number, ...]] from qa/blocker_worklist.json if present.
     *
     * @return array<string, array<int,int>>
     */
    private function loadBlockerMap(string $outputRoot): array
    {
        $file = $outputRoot.DIRECTORY_SEPARATOR.'qa'.DIRECTORY_SEPARATOR.'blocker_worklist.json';
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data) || ! is_array($data['blockers'] ?? null)) {
            return [];
        }

        $out = [];
        foreach ($data['blockers'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $source = $row['source_file'] ?? null;
            $qNum = $row['question_number'] ?? null;
            if (! is_string($source) || ! is_int($qNum)) {
                continue;
            }
            $stem = preg_replace('/\.pdf$/i', '', $source);
            $out[$stem][] = $qNum;
        }

        return $out;
    }

    /**
     * @return array{0: array<int,string>, 1: string}
     */
    private function discoverJsonPaths(string $target, string $defaultRoot): array
    {
        if (is_file($target) && str_ends_with($target, '.json')) {
            // .../storage/output/papers/{stem}/questions.json → outputRoot = .../storage/output
            $outputRoot = dirname($target, 3);
            return [[$target], $outputRoot];
        }

        $papersDir = $target.DIRECTORY_SEPARATOR.'papers';
        $scanRoot = is_dir($papersDir) ? $papersDir : $target;

        $matches = glob($scanRoot.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'questions.json') ?: [];
        sort($matches);

        $outputRoot = is_dir($papersDir) ? $target : (is_dir($defaultRoot) ? $defaultRoot : $target);

        return [$matches, $outputRoot];
    }

    private function normalize(string $path): string
    {
        if (preg_match('#^([a-zA-Z]:[\\\\/]|/)#', $path) !== 1) {
            $path = base_path($path);
        }
        return rtrim($path, "/\\");
    }
}
