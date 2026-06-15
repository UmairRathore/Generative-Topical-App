<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| v2:copy-images
|--------------------------------------------------------------------------
| Copies each paper's images_final/* into storage/app/public/v2/questions/<stem>/,
| matching the path scheme stored in v2_question_images (v2/questions/<stem>/<file>).
| DB is untouched. Run `php artisan storage:link` once so /storage serves them.
| Idempotent (skips files already copied unless --force).
*/
class CopyImages extends Command
{
    protected $signature = 'v2:copy-images
        {path? : Path to output/papers (default: storage/Generative-Topical/output/papers)}
        {--force : Overwrite files that already exist}';

    protected $description = 'Copy question images_final into public storage so the app can serve diagrams';

    public function handle(): int
    {
        $root = rtrim($this->argument('path') ?: storage_path('Generative-Topical/output/papers'), '/\\');
        if (! is_dir($root)) {
            $this->error("Papers directory not found: {$root}");
            return self::FAILURE;
        }

        $destBase = storage_path('app/public/v2/questions');
        $dirs = collect(File::directories($root))
            ->filter(fn ($d) => is_dir($d.'/images_final'))->sort()->values();

        $copied = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($dirs->count());
        $bar->start();

        foreach ($dirs as $dir) {
            $stem = basename($dir);
            $destDir = $destBase.'/'.$stem;
            File::ensureDirectoryExists($destDir);

            foreach (File::files($dir.'/images_final') as $file) {
                $dest = $destDir.'/'.$file->getFilename();
                if (! $this->option('force') && is_file($dest)) {
                    $skipped++;
                    continue;
                }
                File::copy($file->getPathname(), $dest);
                $copied++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Images copied: {$copied} (skipped existing: {$skipped}).");
        $this->line('Run `php artisan storage:link` if you have not already, so /storage/v2/questions/... resolves.');

        return self::SUCCESS;
    }
}
