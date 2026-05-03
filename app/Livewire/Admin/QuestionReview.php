<?php

namespace App\Livewire\Admin;

use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Models\Question;
use App\Services\Rendering\QuestionRenderDataFactory;
use Livewire\Component;

class QuestionReview extends Component
{
    public Question $question;

    public function mount(Question $question): void
    {
        $this->question = $question->load(['paper', 'options.assets', 'assets', 'optionTable', 'topics']);
    }

    public function approve(): void
    {
        $this->question->update([
            'review_status' => QuestionReviewStatus::Approved->value,
            'visibility' => QuestionVisibility::Public->value,
            'needs_review' => false,
        ]);
        session()->flash('admin.review.flash', 'Question approved and made public.');
        $this->refreshQuestion();
    }

    public function markNeedsReview(): void
    {
        $this->question->update([
            'review_status' => QuestionReviewStatus::Review->value,
            'needs_review' => true,
        ]);
        session()->flash('admin.review.flash', 'Marked for further review.');
        $this->refreshQuestion();
    }

    public function useFallback(): void
    {
        $this->question->update([
            'review_status' => QuestionReviewStatus::AcceptableFallback->value,
            'needs_review' => false,
        ]);
        session()->flash('admin.review.flash', 'Switched to fallback crop.');
        $this->refreshQuestion();
    }

    public function hide(): void
    {
        $this->question->update([
            'visibility' => QuestionVisibility::Hidden->value,
        ]);
        session()->flash('admin.review.flash', 'Hidden from publish.');
        $this->refreshQuestion();
    }

    private function refreshQuestion(): void
    {
        $this->question = $this->question->fresh(['paper', 'options.assets', 'assets', 'optionTable', 'topics']);
    }

    public function render(QuestionRenderDataFactory $factory)
    {
        $data = $factory->make($this->question);

        return view('livewire.admin.question-review', [
            'data' => $data,
        ])->layout('components.layouts.dashboard');
    }
}
