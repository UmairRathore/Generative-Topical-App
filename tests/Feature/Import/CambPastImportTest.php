<?php

namespace Tests\Feature\Import;

use App\Enums\QaStatus;
use App\Enums\QuestionVisibility;
use App\Models\OptionTable;
use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionAsset;
use App\Models\QuestionOption;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CambPastImportTest extends TestCase
{
    use RefreshDatabase;

    private string $outputRoot;
    private string $paperFolder;
    private string $paperStem = '9702_m24_qp_12';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CatalogSeeder::class);

        $this->outputRoot = storage_path('framework/testing/cambpast-output-'.uniqid());
        $this->paperFolder = $this->outputRoot.DIRECTORY_SEPARATOR.'papers'.DIRECTORY_SEPARATOR.$this->paperStem;
        File::ensureDirectoryExists($this->paperFolder.DIRECTORY_SEPARATOR.'images');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputRoot)) {
            File::deleteDirectory($this->outputRoot);
        }
        parent::tearDown();
    }

    public function test_imports_a_paper_with_questions_options_assets_and_table(): void
    {
        $diagram = 'papers/'.$this->paperStem.'/images/q002_question_diagram_01.png';
        $optionImg = 'papers/'.$this->paperStem.'/images/q003_option_A.png';
        $tableImg = 'papers/'.$this->paperStem.'/images/q001_table_01.png';

        File::put($this->outputRoot.'/'.$diagram, 'PNG-DIAGRAM');
        File::put($this->outputRoot.'/'.$optionImg, 'PNG-OPT');
        File::put($this->outputRoot.'/'.$tableImg, 'PNG-TABLE');

        $payload = [
            'paper' => [
                'paper_code' => '9702/12',
                'session' => 'February/March 2024',
                'total_questions' => 5,
            ],
            'questions' => [
                // Q1 - option_table with rows AND fallback image (rows present → table renders, not fallback)
                [
                    'question_number' => 1,
                    'question_text' => 'Which row shows units?',
                    'image_between_question_before_text' => null,
                    'image_between_question_after_text' => null,
                    'options' => [
                        ['label' => 'A', 'text' => 'current | A', 'images' => []],
                        ['label' => 'B', 'text' => 'force | N', 'images' => []],
                        ['label' => 'C', 'text' => 'mass | g', 'images' => []],
                        ['label' => 'D', 'text' => 'temperature | C', 'images' => []],
                    ],
                    'option_table' => [
                        'headers' => ['', 'q', 'unit'],
                        'rows' => [['A', 'current', 'A'], ['B', 'force', 'N']],
                        'image_path' => $tableImg,
                    ],
                    'assets' => [],
                    'correct_answer' => null,
                    'layout_type' => 'option_table',
                ],
                // Q2 - split text with embedded diagram (assets[] is the source)
                [
                    'question_number' => 2,
                    'question_text' => 'A car... <full text>',
                    'image_between_question_before_text' => 'A car of mass 850 kg is travelling.',
                    'image_between_question_after_text' => 'What is the magnitude of the acceleration?',
                    'options' => [
                        ['label' => 'A', 'text' => '0.47 m/s²', 'images' => []],
                        ['label' => 'B', 'text' => '1.4 m/s²', 'images' => []],
                        ['label' => 'C', 'text' => '1.9 m/s²', 'images' => []],
                        ['label' => 'D', 'text' => '3.3 m/s²', 'images' => []],
                    ],
                    'option_table' => null,
                    'assets' => [
                        ['image_path' => $diagram, 'role' => 'question_image_between_text', 'page' => 3, 'caption' => 'forces'],
                    ],
                    'correct_answer' => null,
                    'layout_type' => 'question_diagram',
                ],
                // Q3 - option_images with one option having an image
                [
                    'question_number' => 3,
                    'question_text' => 'Which graph?',
                    'options' => [
                        ['label' => 'A', 'text' => null, 'images' => [['image_path' => $optionImg]]],
                        ['label' => 'B', 'text' => null, 'images' => []],
                        ['label' => 'C', 'text' => null, 'images' => []],
                        ['label' => 'D', 'text' => null, 'images' => []],
                    ],
                    'option_table' => null,
                    'assets' => [],
                    'correct_answer' => null,
                    'layout_type' => 'option_images',
                ],
                // Q4 - pure text
                [
                    'question_number' => 4,
                    'question_text' => 'Plain text question',
                    'options' => [
                        ['label' => 'A', 'text' => 'a', 'images' => []],
                        ['label' => 'B', 'text' => 'b', 'images' => []],
                        ['label' => 'C', 'text' => 'c', 'images' => []],
                        ['label' => 'D', 'text' => 'd', 'images' => []],
                    ],
                    'option_table' => null,
                    'assets' => [],
                    'correct_answer' => 'B',
                    'layout_type' => 'text_only',
                ],
                // Q5 - flagged as blocker via worklist (passed via $blockerNumbers)
                [
                    'question_number' => 5,
                    'question_text' => 'Empty options question',
                    'options' => [
                        ['label' => 'A', 'text' => '', 'images' => []],
                        ['label' => 'B', 'text' => '', 'images' => []],
                        ['label' => 'C', 'text' => '', 'images' => []],
                        ['label' => 'D', 'text' => '', 'images' => []],
                    ],
                    'option_table' => null,
                    'assets' => [],
                    'correct_answer' => null,
                    'layout_type' => 'question_diagram',
                ],
            ],
        ];

        File::put($this->paperFolder.DIRECTORY_SEPARATOR.'questions.json', json_encode($payload));

        // Drop a qa worklist that flags Q5 as a blocker.
        File::ensureDirectoryExists($this->outputRoot.DIRECTORY_SEPARATOR.'qa');
        File::put($this->outputRoot.'/qa/blocker_worklist.json', json_encode([
            'blockers' => [
                ['source_file' => $this->paperStem.'.pdf', 'question_number' => 5],
            ],
        ]));

        $exit = $this->artisan('cambpast:import', ['path' => $this->outputRoot])->run();
        $this->assertSame(0, $exit);

        $this->assertSame(1, Paper::count());
        $paper = Paper::first();
        $this->assertSame('9702', $paper->paper_code, 'paper_code must be split from "9702/12"');
        $this->assertSame(2024, $paper->year);
        $this->assertSame('march', $paper->session);
        $this->assertSame(1, $paper->paper_number);
        $this->assertSame('2', $paper->variant);

        $this->assertSame(5, Question::count());
        $this->assertSame(20, QuestionOption::count());

        // Q1 - option table with rows
        $q1 = Question::where('question_number', 1)->first();
        $optTable1 = OptionTable::where('question_id', $q1->id)->first();
        $this->assertNotNull($optTable1);
        $this->assertFalse((bool) $optTable1->use_fallback_image, 'rows present → render HTML table');
        $this->assertNotNull($optTable1->image_path);

        // Q2 - split text + 1 between-text diagram
        $q2 = Question::where('question_number', 2)->first();
        $this->assertSame('A car of mass 850 kg is travelling.', $q2->image_between_question_before_text);
        $this->assertSame('What is the magnitude of the acceleration?', $q2->image_between_question_after_text);
        $this->assertSame(1, QuestionAsset::where('question_id', $q2->id)
            ->where('role', 'question_image_between_text')->count());

        // Q3 - one option image attached to A
        $q3 = Question::where('question_number', 3)->first();
        $optionA = QuestionOption::where('question_id', $q3->id)->where('label', 'A')->first();
        $this->assertSame(1, QuestionAsset::where('question_option_id', $optionA->id)->count());

        // Q4 - has correct answer
        $q4 = Question::where('question_number', 4)->first();
        $this->assertSame('B', $q4->correct_answer);
        $this->assertSame(QuestionVisibility::Public, $q4->visibility);

        // Q5 - forced blocker via qa worklist
        $q5 = Question::where('question_number', 5)->first();
        $this->assertSame(QaStatus::Blocker, $q5->qa_status);
        $this->assertSame(QuestionVisibility::Hidden, $q5->visibility);

        // Public-disk asset must exist
        Storage::disk('public')->assertExists($q2->assets()->first()->image_path);
    }

    public function test_re_running_importer_is_idempotent(): void
    {
        $payload = [
            'paper' => ['paper_code' => '9702/12'],
            'questions' => [
                [
                    'question_number' => 1,
                    'question_text' => 'Stem',
                    'options' => [
                        ['label' => 'A', 'text' => 'a', 'images' => []],
                        ['label' => 'B', 'text' => 'b', 'images' => []],
                        ['label' => 'C', 'text' => 'c', 'images' => []],
                        ['label' => 'D', 'text' => 'd', 'images' => []],
                    ],
                    'option_table' => null,
                    'assets' => [],
                    'correct_answer' => 'A',
                    'layout_type' => 'text_only',
                ],
            ],
        ];
        File::put($this->paperFolder.DIRECTORY_SEPARATOR.'questions.json', json_encode($payload));

        $this->artisan('cambpast:import', ['path' => $this->outputRoot])->assertExitCode(0);
        $this->artisan('cambpast:import', ['path' => $this->outputRoot])->assertExitCode(0);

        $this->assertSame(1, Paper::count());
        $this->assertSame(1, Question::count());
        $this->assertSame(4, QuestionOption::count());
    }
}
