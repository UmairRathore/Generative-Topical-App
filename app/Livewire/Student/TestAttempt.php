<?php

namespace App\Livewire\Student;

use Livewire\Attributes\Layout;
use Livewire\Component;

class TestAttempt extends Component
{
    public int $current = 14;
    public int $total = 40;
    public array $answers = [
        1=>'B', 2=>'D', 3=>'A', 4=>'C', 5=>'B', 6=>'A', 7=>'D', 8=>'C',
        9=>'B', 10=>'A', 11=>'C', 12=>'D', 13=>'A',
    ];
    public array $flagged = [7, 11];
    public int $remainingSeconds = 2364;

    public function mount(int $id = 1): void
    {
        // Phase 1: static. In Phase 2 wire to App\Models\TestSession.
    }

    public function answer(int $n, string $label): void
    {
        $this->answers[$n] = $label;
    }

    public function go(int $n): void
    {
        $this->current = max(1, min($this->total, $n));
    }

    public function flag(int $n): void
    {
        if (in_array($n, $this->flagged)) {
            $this->flagged = array_values(array_diff($this->flagged, [$n]));
        } else {
            $this->flagged[] = $n;
        }
    }

    public function render()
    {
        $q = [
            'topic' => "Mechanics · Newton's Second Law",
            'stem'  => 'A car of mass 1200 kg accelerates from rest to 25 m s⁻¹ in 8.0 seconds along a straight horizontal road.',
            'prompt'=> 'What is the magnitude of the resultant force acting on the car during this acceleration?',
            'options' => [
                ['l' => 'A', 'v' => '150 N'],
                ['l' => 'B', 'v' => '1500 N'],
                ['l' => 'C', 'v' => '3750 N'],
                ['l' => 'D', 'v' => '9600 N'],
            ],
        ];

        return view('livewire.student.test-attempt', ['q' => $q])
            ->layout('components.layouts.test-shell');
    }
}
