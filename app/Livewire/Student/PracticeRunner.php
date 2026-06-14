<?php

namespace App\Livewire\Student;

use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Subject;
use App\Models\TestSession;
use App\Services\Rendering\QuestionRenderDataFactory;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PracticeRunner extends Component
{
    public Subject $subject;
    public ?TestSession $session = null;
    public ?int $questionId = null;
    public ?string $selected = null;
    public bool $submitted = false;

    public function mount(string $slug): void
    {
        $this->subject = Subject::where('slug', $slug)->firstOrFail();

        $this->session = TestSession::create([
            'user_id' => auth()->id(),
            'subject_id' => $this->subject->id,
            'mode' => 'random',
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
            'correct_count' => 0,
        ]);

        $this->loadNextQuestion();
    }

    #[Computed]
    public function question(): ?Question
    {
        return $this->questionId
            ? Question::with(['options.assets', 'assets', 'optionTable'])->find($this->questionId)
            : null;
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
        if ($this->submitted || $this->selected === null || $this->question === null) {
            return;
        }

        $question = $this->question;
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
        $this->loadNextQuestion();
    }

    public function finish()
    {
        $this->session->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();

        return redirect()->route('practice.result', $this->session);
    }

    private function loadNextQuestion(): void
    {
        $answered = QuestionAttempt::where('test_session_id', $this->session->id)
            ->pluck('question_id')
            ->all();

        $next = Question::query()
            ->demoSafe()
            ->whereHas('paper', fn ($q) => $q->where('subject_id', $this->subject->id))
            ->whereNotIn('id', $answered)
            ->inRandomOrder()
            ->first();

        $this->questionId = $next?->id;
    }

    public function render(QuestionRenderDataFactory $factory)
    {
        $data = $this->question ? $factory->make($this->question) : null;

        return view('livewire.student.practice-runner', [
            'data' => $data,
        ])->layout('components.layouts.public');
    }
}
