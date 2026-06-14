<?php

namespace App\Livewire\Student;

use App\Models\TestSession;
use Livewire\Component;

class ResultSummary extends Component
{
    public TestSession $session;

    public function mount(TestSession $session): void
    {
        $this->session = $session->loadMissing(['attempts.question', 'paper', 'subject']);
    }

    public function render()
    {
        $attempts = $this->session->attempts;
        $total = $attempts->count();
        $correct = $attempts->where('is_correct', true)->count();
        $incorrect = $attempts->where('is_correct', false)->count();
        $unscored = $attempts->whereNull('is_correct')->count();

        return view('livewire.student.result-summary', [
            'attempts' => $attempts,
            'total' => $total,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'unscored' => $unscored,
        ])->layout('components.layouts.public');
    }
}
