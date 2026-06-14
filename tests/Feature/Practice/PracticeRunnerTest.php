<?php

namespace Tests\Feature\Practice;

use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Livewire\Student\PracticeRunner;
use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PracticeRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_practice_and_null_answer_is_unscored(): void
    {
        $this->seed(CatalogSeeder::class);
        $subject = Subject::where('slug', 'a-level-physics')->first();
        $paper = Paper::create([
            'subject_id' => $subject->id,
            'source_file' => 'unit/runner.json',
            'paper_code' => '9702',
            'session' => 'march',
            'year' => 2024,
        ]);

        $q = Question::create([
            'paper_id' => $paper->id,
            'question_number' => 1,
            'question_text' => 'No-key question',
            'correct_answer' => null,
            'visibility' => QuestionVisibility::Public,
            'review_status' => QuestionReviewStatus::Pass,
            'qa_status' => QaStatus::Pass,
            'layout_type' => 'standard',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            QuestionOption::create([
                'question_id' => $q->id,
                'label' => $label,
                'option_text' => "opt {$label}",
                'sort_order' => $i + 1,
            ]);
        }

        Livewire::test(PracticeRunner::class, ['slug' => 'a-level-physics'])
            ->assertSet('selected', null)
            ->call('selectAnswer', 'B')
            ->assertSet('selected', 'B')
            ->call('submit')
            ->assertSet('submitted', true);

        $attemptRow = \App\Models\QuestionAttempt::first();
        $this->assertNotNull($attemptRow);
        $this->assertSame('B', $attemptRow->selected_answer);
        $this->assertNull($attemptRow->is_correct, 'null answer must remain unscored');
    }

    public function test_blocker_questions_are_never_served(): void
    {
        $this->seed(CatalogSeeder::class);
        $subject = Subject::where('slug', 'a-level-physics')->first();
        $paper = Paper::create([
            'subject_id' => $subject->id,
            'source_file' => 'unit/blocker.json',
            'paper_code' => '9702',
            'session' => 'march',
            'year' => 2024,
        ]);

        Question::create([
            'paper_id' => $paper->id,
            'question_number' => 1,
            'question_text' => 'BLOCKER',
            'visibility' => QuestionVisibility::Hidden,
            'review_status' => QuestionReviewStatus::Rejected,
            'qa_status' => QaStatus::Blocker,
            'layout_type' => 'standard',
        ]);

        Livewire::test(PracticeRunner::class, ['slug' => 'a-level-physics'])
            ->assertSet('questionId', null);
    }
}
