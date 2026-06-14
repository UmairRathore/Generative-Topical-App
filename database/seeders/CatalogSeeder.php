<?php

namespace Database\Seeders;

use App\Models\ExamBoard;
use App\Models\Qualification;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $board = ExamBoard::updateOrCreate(
            ['slug' => 'cambridge'],
            ['name' => 'Cambridge Assessment International Education'],
        );

        $qualification = Qualification::updateOrCreate(
            ['exam_board_id' => $board->id, 'slug' => 'a-level'],
            ['name' => 'A Level', 'level_order' => 30],
        );

        Subject::updateOrCreate(
            ['qualification_id' => $qualification->id, 'slug' => 'a-level-physics'],
            [
                'name' => 'Physics',
                'code' => '9702',
                'description' => 'Cambridge International AS & A Level Physics (9702).',
            ],
        );
    }
}
