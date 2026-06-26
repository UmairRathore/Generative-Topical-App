<?php

namespace Database\Seeders;

use App\Services\Syllabus\SyllabusLoader;
use Illuminate\Database\Seeder;

class SyllabusPhysicsAlevelSeeder extends Seeder
{
    public function run(SyllabusLoader $loader): void
    {
        $path = $loader->resolvePath(SyllabusLoader::DEFAULT_PATH);
        $stats = $loader->sync($path, SyllabusLoader::DEFAULT_CODE);

        $this->command?->info(sprintf(
            'Synced syllabus from %s - topics=%d subtopics=%d objectives=%d',
            $path, $stats['topics'], $stats['subtopics'], $stats['objectives']
        ));
    }
}
