<?php

namespace App\Livewire\Admin;

use App\Models\Paper;
use App\Models\Question;
use App\Services\Rendering\QuestionRenderDataFactory;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PaperQuestions extends Component
{
    public Paper $paper;
    public ?int $previewId = null;

    public function mount(Paper $paper): void
    {
        $this->paper = $paper;
    }

    public function preview(int $id): void
    {
        $this->previewId = $this->previewId === $id ? null : $id;
    }

    public function deleteQuestion(int $id): void
    {
        $q = Question::where('paper_id', $this->paper->id)->find($id);
        $q?->delete();
        if ($this->previewId === $id) {
            $this->previewId = null;
        }
    }

    public function render(QuestionRenderDataFactory $factory)
    {
        $questions = Question::query()
            ->with(['options', 'assets', 'optionTable'])
            ->where('paper_id', $this->paper->id)
            ->orderBy('question_number')
            ->get();

        $stats = [
            'total' => $questions->count(),
            'public' => $questions->where('visibility.value', 'public')->count(),
            'admin_only' => $questions->where('visibility.value', 'admin_only')->count(),
            'hidden' => $questions->where('visibility.value', 'hidden')->count(),
            'needs_review' => $questions->where('needs_review', true)->count(),
        ];

        $previewData = null;
        if ($this->previewId) {
            $q = $questions->firstWhere('id', $this->previewId);
            if ($q) {
                $previewData = $factory->make($q);
            }
        }

        return view('livewire.admin.paper-questions', [
            'questions' => $questions,
            'stats' => $stats,
            'previewData' => $previewData,
        ])->layout('components.layouts.dashboard');
    }
}
