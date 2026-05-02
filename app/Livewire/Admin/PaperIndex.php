<?php

namespace App\Livewire\Admin;

use App\Enums\QuestionVisibility;
use App\Models\Paper;
use App\Models\Question;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PaperIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Paper::query()->with('subject');

        if ($this->search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';
            $query->where(function ($w) use ($term) {
                $w->where('source_file', 'like', $term)
                    ->orWhere('paper_code', 'like', $term)
                    ->orWhere('session', 'like', $term)
                    ->orWhere('year', 'like', $term);
            });
        }

        $papers = $query
            ->orderByDesc('year')
            ->orderBy('paper_number')
            ->orderBy('variant')
            ->paginate(25);

        $paperIds = collect($papers->items())->pluck('id');
        $statusCounts = Question::query()
            ->selectRaw('paper_id, visibility, needs_review, COUNT(*) as c')
            ->whereIn('paper_id', $paperIds)
            ->groupBy('paper_id', 'visibility', 'needs_review')
            ->get()
            ->groupBy('paper_id');

        return view('livewire.admin.paper-index', [
            'papers' => $papers,
            'statusCounts' => $statusCounts,
        ])->layout('components.layouts.dashboard');
    }
}
