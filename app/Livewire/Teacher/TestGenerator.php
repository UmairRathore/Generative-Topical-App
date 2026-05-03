<?php

namespace App\Livewire\Teacher;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TestGenerator extends Component
{
    public int $step = 1;

    public ?int $subjectId = null;
    /** @var array<int, int> */
    public array $allocations = [];
    public string $testName = 'Random 40Q Mock';
    public int $duration = 60;
    public bool $shuffle = true;
    public bool $showAnswersAfter = false;

    public function mount(): void
    {
        $physics = Subject::where('slug', 'a-level-physics')->first();
        $this->subjectId = $physics?->id;
    }

    #[Computed]
    public function subjects()
    {
        return Subject::orderBy('name')->get();
    }

    #[Computed]
    public function topics()
    {
        if (! $this->subjectId) {
            return collect();
        }

        return Topic::query()
            ->withCount(['questions' => fn ($q) => $q->demoSafe()
                ->whereHas('paper', fn ($p) => $p->where('subject_id', $this->subjectId))])
            ->orderBy('name')
            ->get();
    }

    public function totalQuestions(): int
    {
        return array_sum($this->allocations);
    }

    public function toggleTopic(int $topicId): void
    {
        if (isset($this->allocations[$topicId])) {
            unset($this->allocations[$topicId]);
        } else {
            $this->allocations[$topicId] = 10;
        }
    }

    public function next(): void
    {
        if ($this->step < 5) $this->step++;
    }

    public function back(): void
    {
        if ($this->step > 1) $this->step--;
    }

    public function go(int $s): void
    {
        $this->step = max(1, min(5, $s));
    }

    public function generate(): void
    {
        // Phase 1: just flash a confirmation. Phase 2: persist as a real test.
        session()->flash('teacher.flash', "Generated test '{$this->testName}' with {$this->totalQuestions()} questions.");
    }

    public function render()
    {
        return view('livewire.teacher.test-generator')
            ->layout('components.layouts.dashboard');
    }
}
