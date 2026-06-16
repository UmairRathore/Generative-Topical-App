<?php

namespace App\Console\Commands\V2;

use App\Models\V2\QuestionImage;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:backfill-image-dimensions
|--------------------------------------------------------------------------
| Reads each crop's real pixel width/height (getimagesize) and caches it on the
| row. Used by QuestionImage::displayWidth so diagrams size off their true
| dimensions instead of the bbox, which is wrong for a minority of crops and
| made them render far too small. Idempotent; --force re-reads rows already set.
*/
class BackfillImageDimensions extends Command
{
    protected $signature = 'v2:backfill-image-dimensions {--force : Re-read rows that already have dimensions}';

    protected $description = 'Cache real pixel width/height for question images';

    public function handle(): int
    {
        $disk = storage_path('app/public/');
        $updated = 0;
        $missing = 0;

        $query = QuestionImage::query();
        if (! $this->option('force')) {
            $query->whereNull('width');
        }

        $total = (clone $query)->count();
        $this->info("Reading dimensions for {$total} image(s)…");
        $bar = $this->output->createProgressBar($total);

        $query->chunkById(500, function ($images) use ($disk, &$updated, &$missing, $bar) {
            foreach ($images as $image) {
                $file = $disk.$image->image_path;
                $size = is_file($file) ? @getimagesize($file) : false;
                if ($size) {
                    $image->update(['width' => $size[0], 'height' => $size[1]]);
                    $updated++;
                } else {
                    $missing++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Updated {$updated} image(s).".($missing ? " {$missing} file(s) missing on disk." : ''));

        return self::SUCCESS;
    }
}
