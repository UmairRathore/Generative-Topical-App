<?php

namespace Tests\Feature\Admin;

use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Enums\UserRole;
use App\Livewire\Admin\PaperForm;
use App\Livewire\Admin\PaperIndex;
use App\Livewire\Admin\QuestionForm;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\Rendering\QuestionRenderDataFactory;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaperManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_guest_cannot_access_paper_index(): void
    {
        $this->get('/admin/papers')->assertRedirect('/login');
    }

    public function test_admin_can_access_paper_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get('/admin/papers')->assertOk();
    }

    public function test_non_admin_cannot_access_paper_index(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $this->actingAs($student)->get('/admin/papers')->assertForbidden();
    }

    public function test_admin_can_create_a_manual_paper(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $subject = Subject::where('slug', 'a-level-physics')->first();

        Livewire::actingAs($admin)
            ->test(PaperForm::class)
            ->set('subject_id', $subject->id)
            ->set('paper_code', '9702')
            ->set('session', 'march')
            ->set('session_code', 'm')
            ->set('year', 2026)
            ->set('paper_number', 1)
            ->set('variant', '2')
            ->call('save');

        $this->assertDatabaseHas('papers', [
            'subject_id' => $subject->id,
            'paper_code' => '9702',
            'session' => 'march',
            'year' => 2026,
        ]);

        $paper = Paper::first();
        $this->assertStringStartsWith('manual_', $paper->source_file);
        $this->assertSame('manual_admin', $paper->raw_meta['source'] ?? null);
    }

    public function test_admin_can_create_a_manual_question_with_options(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $subject = Subject::where('slug', 'a-level-physics')->first();
        $paper = Paper::create([
            'subject_id' => $subject->id,
            'source_file' => 'manual_test.json',
            'paper_code' => '9702',
            'session' => 'march',
            'year' => 2026,
        ]);

        Livewire::actingAs($admin)
            ->test(QuestionForm::class, ['paper' => $paper])
            ->set('question_number', 1)
            ->set('question_text', 'A force of 10 N acts on a 2 kg mass.')
            ->set('layout_type', 'standard')
            ->set('correct_answer', 'C')
            ->set('qa_status', QaStatus::Pass->value)
            ->set('review_status', QuestionReviewStatus::Pass->value)
            ->set('visibility', QuestionVisibility::Public->value)
            ->set('options.A.text', '2 m/s²')
            ->set('options.B.text', '4 m/s²')
            ->set('options.C.text', '5 m/s²')
            ->set('options.D.text', '20 m/s²')
            ->call('save');

        $this->assertSame(1, Question::count());
        $q = Question::first();
        $this->assertSame('C', $q->correct_answer);
        $this->assertSame(4, $q->options()->count());
        $this->assertSame('5 m/s²', $q->options()->where('label', 'C')->value('option_text'));
        $this->assertSame('manual_admin', $q->raw_payload['source'] ?? null);
    }

    public function test_manual_public_question_renders_via_existing_factory(): void
    {
        $subject = Subject::where('slug', 'a-level-physics')->first();
        $paper = Paper::create([
            'subject_id' => $subject->id,
            'source_file' => 'manual_render.json',
            'paper_code' => '9702',
            'session' => 'march',
            'year' => 2026,
        ]);
        $q = Question::create([
            'paper_id' => $paper->id,
            'question_number' => 1,
            'question_text' => 'What is 2+2?',
            'visibility' => QuestionVisibility::Public,
            'review_status' => QuestionReviewStatus::Pass,
            'qa_status' => QaStatus::Pass,
            'layout_type' => 'standard',
            'correct_answer' => 'B',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            $q->options()->create([
                'label' => $label,
                'option_text' => "opt {$label}",
                'sort_order' => $i + 1,
            ]);
        }

        $factory = app(QuestionRenderDataFactory::class);
        $data = $factory->make($q->fresh(['options.assets', 'assets', 'optionTable']));

        $this->assertSame('What is 2+2?', $data['stem']);
        $this->assertCount(4, $data['options']);
        $this->assertSame('B', $data['correct_answer']);
        $this->assertTrue($data['has_correct_answer']);
    }
}
