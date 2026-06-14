<?php

namespace App\Livewire\Student;

use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\TestSession;
use App\Services\Rendering\QuestionRenderDataFactory;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PaperRunner extends Component
{
    public Paper $paper;
    public ?TestSession $session = null;
    public int $index = 0;
    public ?string $selected = null;
    public bool $submitted = false;

    public function mount(Paper $paper): void
    {
        $this->paper = $paper;

        $this->session = TestSession::create([
            'user_id' => auth()->id(),
            'paper_id' => $paper->id,
            'subject_id' => $paper->subject_id,
            'mode' => 'paper',
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
            'correct_count' => 0,
        ]);
    }

    #[Computed]
    public function questions()
    {
        return Question::query()
            ->where('paper_id', $this->paper->id)
            ->demoSafe()
            ->orderBy('question_number')
            ->with(['options.assets', 'assets', 'optionTable'])
            ->get();
    }

    #[Computed]
    public function currentQuestion(): ?Question
    {
        return $this->questions[$this->index] ?? null;
    }

    public function selectAnswer(string $label): void
    {
        if ($this->submitted) {
            return;
        }
        if (in_array($label, ['A', 'B', 'C', 'D'], true)) {
            $this->selected = $label;
        }
    }

    public function submit(): void
    {
        if ($this->submitted || $this->selected === null || ! $this->currentQuestion) {
            return;
        }

        $question = $this->currentQuestion;
        $correct = $question->correct_answer;
        $isCorrect = $correct !== null ? $this->selected === $correct : null;

        QuestionAttempt::updateOrCreate(
            [
                'test_session_id' => $this->session->id,
                'question_id' => $question->id,
            ],
            [
                'user_id' => auth()->id(),
                'selected_answer' => $this->selected,
                'correct_answer' => $correct,
                'is_correct' => $isCorrect,
                'answered_at' => now(),
            ],
        );

        $this->session->increment('total_questions');
        if ($isCorrect === true) {
            $this->session->increment('correct_count');
        }

        $this->submitted = true;
    }

    public function next(): void
    {
        $this->selected = null;
        $this->submitted = false;
        $this->index++;
    }

    public function finish()
    {
        $this->session->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();

        return redirect()->route('practice.result', $this->session);
    }

    public function render(QuestionRenderDataFactory $factory)
    {
        $current = $this->currentQuestion;
        $data = $current ? $factory->make($current) : null;

        return view('livewire.student.paper-runner', [
            'data' => $data,
            'total' => $this->questions->count(),
        ])->layout('components.layouts.public');
    }
}
