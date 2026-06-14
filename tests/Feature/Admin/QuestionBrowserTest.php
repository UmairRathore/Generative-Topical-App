<?php

namespace Tests\Feature\Admin;

use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Enums\UserRole;
use App\Livewire\Admin\QuestionBrowser;
use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionBrowserTest extends TestCase
{
    use RefreshDatabase;

    private function makePaper(): Paper
    {
        $this->seed(CatalogSeeder::class);
        $subject = Subject::where('slug', 'a-level-physics')->first();

        return Paper::create([
            'subject_id' => $subject->id,
            'source_file' => 'unit/browser.json',
            'paper_code' => '9702',
            'paper_number' => 1,
            'variant' => '2',
            'session' => 'march',
            'session_code' => 'm',
            'year' => 2024,
        ]);
    }

    private function makeQuestion(Paper $paper, int $n, array $overrides = []): Question
    {
        $q = Question::create(array_merge([
            'paper_id' => $paper->id,
            'question_number' => $n,
            'question_text' => "Question {$n} about Newton's laws",
            'visibility' => QuestionVisibility::Public,
            'review_status' => QuestionReviewStatus::Pass,
            'qa_status' => QaStatus::Pass,
            'layout_type' => 'standard',
            'needs_review' => false,
        ], $overrides));

        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            QuestionOption::create([
                'question_id' => $q->id,
                'label' => $label,
                'option_text' => "option {$label}",
                'sort_order' => $i + 1,
            ]);
        }

        return $q;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/questions')->assertRedirect('/login');
    }

    public function test_non_admin_gets_403(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $this->actingAs($student)->get('/admin/questions')->assertForbidden();
    }

    public function test_admin_can_view_questions_including_hidden(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $paper = $this->makePaper();
        $public = $this->makeQuestion($paper, 1);
        $hidden = $this->makeQuestion($paper, 2, [
            'visibility' => QuestionVisibility::Hidden,
            'review_status' => QuestionReviewStatus::Rejected,
            'qa_status' => QaStatus::Blocker,
        ]);

        $this->actingAs($admin)
            ->get('/admin/questions')
            ->assertOk()
            ->assertSee("Question {$public->question_number}")
            ->assertSee("Question {$hidden->question_number}")
            ->assertSee('hidden')
            ->assertSee('blocker');
    }

    public function test_filters_narrow_results(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $paper = $this->makePaper();
        $this->makeQuestion($paper, 1, ['question_text' => 'A box on a frictionless ramp']);
        $this->makeQuestion($paper, 2, ['question_text' => 'Refraction of light through glass']);
        $blocker = $this->makeQuestion($paper, 3, [
            'visibility' => QuestionVisibility::Hidden,
            'qa_status' => QaStatus::Blocker,
            'review_status' => QuestionReviewStatus::Rejected,
            'question_text' => 'Quantum behavior of electrons',
        ]);

        Livewire::actingAs($admin)
            ->test(QuestionBrowser::class)
            ->set('search', 'refraction')
            ->assertSee('Refraction of light through glass')
            ->assertDontSee('A box on a frictionless ramp')
            ->set('search', '')
            ->set('visibility', 'hidden')
            ->assertSee('Quantum behavior of electrons')
            ->assertDontSee('A box on a frictionless ramp');
    }
}
