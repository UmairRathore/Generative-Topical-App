<?php

namespace App\Livewire\Teacher;

use App\Models\Question;
use App\Models\Topic;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class QuestionPicker extends Component
{
    use WithPagination;

    public string $search = '';
    public string $topicSlug = '';
    /** @var array<int, int> */
    public array $picked = [];

    public function updating($field): void
    {
        if (in_array($field, ['search', 'topicSlug'])) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        if (in_array($id, $this->picked, true)) {
            $this->picked = array_values(array_diff($this->picked, [$id]));
        } else {
            $this->picked[] = $id;
        }
    }

    #[Computed]
    public function topics()
    {
        return Topic::orderBy('name')->get();
    }

    public function render()
    {
        $rows = Question::query()
            ->demoSafe()
            ->with(['paper', 'options'])
            ->when($this->search, fn ($q) => $q->where(function ($qq) {
                $qq->where('clean_question_text', 'like', "%{$this->search}%")
                   ->orWhere('question_text', 'like', "%{$this->search}%");
            }))
            ->when($this->topicSlug, fn ($q) => $q->whereHas(
                'topics', fn ($t) => $t->where('slug', $this->topicSlug)
            ))
            ->orderByDesc('id')
            ->paginate(15);

        $pickedQuestions = ! empty($this->picked)
            ? Question::with('paper')->whereIn('id', $this->picked)->get()
            : collect();

        return view('livewire.teacher.question-picker', [
            'rows' => $rows,
            'pickedQuestions' => $pickedQuestions,
        ])->layout('components.layouts.dashboard');
    }
}
