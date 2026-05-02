<?php

namespace App\Livewire\Student;

use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use Livewire\Component;

class SubjectShow extends Component
{
    public Subject $subject;

    public function mount(string $slug): void
    {
        $this->subject = Subject::where('slug', $slug)->firstOrFail();
    }

    public function render()
    {
        $papers = Paper::where('subject_id', $this->subject->id)
            ->orderByDesc('year')
            ->orderBy('paper_number')
            ->orderBy('variant')
            ->get();

        $totalDemoSafe = Question::query()
            ->whereIn('paper_id', $papers->pluck('id'))
            ->demoSafe()
            ->count();

        return view('livewire.student.subject-show', [
            'papers' => $papers,
            'totalDemoSafe' => $totalDemoSafe,
        ])->layout('components.layouts.site');
    }
}
