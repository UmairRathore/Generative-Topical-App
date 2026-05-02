<?php

namespace App\Livewire\Admin;

use App\Enums\LayoutType;
use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Topic;
use App\Services\Rendering\QuestionRenderDataFactory;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class QuestionBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $year = null;

    /** Cambridge stem session code: m, s, w. */
    #[Url(as: 'sess')]
    public ?string $sessionCode = null;

    #[Url]
    public ?int $paperNumber = null;

    #[Url]
    public ?string $variant = null;

    #[Url]
    public ?string $layoutType = null;

    #[Url]
    public ?string $visibility = null;

    #[Url(as: 'review')]
    public ?string $reviewStatus = null;

    #[Url(as: 'qa')]
    public ?string $qaStatus = null;

    /** all | yes | no */
    #[Url(as: 'nr')]
    public string $needsReview = 'all';

    /** all | yes | no */
    #[Url(as: 'qi')]
    public string $hasQuestionImages = 'all';

    /** all | yes | no */
    #[Url(as: 'oi')]
    public string $hasOptionImages = 'all';

    /** all | yes | no */
    #[Url(as: 'ot')]
    public string $hasOptionTable = 'all';

    /** integer topic id, "untagged", or null */
    #[Url]
    public ?string $topic = null;

    #[Url]
    public int $perPage = 25;

    /** id => bool */
    public array $expandedRaw = [];
    public array $expandedWarnings = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updating($name): void
    {
        if (in_array($name, [
            'year', 'sessionCode', 'paperNumber', 'variant', 'layoutType',
            'visibility', 'reviewStatus', 'qaStatus', 'needsReview',
            'hasQuestionImages', 'hasOptionImages', 'hasOptionTable',
            'topic', 'perPage',
        ], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'year', 'sessionCode', 'paperNumber', 'variant', 'layoutType',
            'visibility', 'reviewStatus', 'qaStatus',
        ]);
        $this->needsReview = 'all';
        $this->hasQuestionImages = 'all';
        $this->hasOptionImages = 'all';
        $this->hasOptionTable = 'all';
        $this->topic = null;
        $this->perPage = 25;
        $this->resetPage();
    }

    public function toggleRaw(int $id): void
    {
        $this->expandedRaw[$id] = ! ($this->expandedRaw[$id] ?? false);
    }

    public function toggleWarnings(int $id): void
    {
        $this->expandedWarnings[$id] = ! ($this->expandedWarnings[$id] ?? false);
    }

    public function render(QuestionRenderDataFactory $factory)
    {
        $perPage = in_array($this->perPage, [10, 25, 50], true) ? $this->perPage : 25;

        $query = Question::query()
            ->with([
                'paper:id,paper_code,session,session_code,year,paper_number,variant,source_file',
                'options.assets',
                'assets',
                'optionTable',
                'topics:id,name',
            ]);

        $this->applyFilters($query);

        $paginator = $query
            ->orderBy('paper_id')
            ->orderBy('question_number')
            ->paginate($perPage);

        $renderable = collect($paginator->items())->mapWithKeys(
            fn (Question $q) => [$q->id => $factory->make($q)],
        );

        return view('livewire.admin.question-browser', [
            'questions' => $paginator,
            'renderable' => $renderable,
            'years' => $this->yearOptions(),
            'paperNumbers' => $this->paperNumberOptions(),
            'variants' => $this->variantOptions(),
            'topics' => Topic::orderBy('name')->get(['id', 'name']),
            'layoutTypes' => array_map(fn ($c) => $c->value, LayoutType::cases()),
            'visibilities' => array_map(fn ($c) => $c->value, QuestionVisibility::cases()),
            'reviewStatuses' => array_map(fn ($c) => $c->value, QuestionReviewStatus::cases()),
            'qaStatuses' => array_map(fn ($c) => $c->value, QaStatus::cases()),
        ])->layout('components.layouts.dashboard');
    }

    private function applyFilters($query): void
    {
        if ($this->search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';
            $query->where(function ($w) use ($term) {
                $w->where('question_text', 'like', $term)
                    ->orWhere('clean_question_text', 'like', $term)
                    ->orWhereHas('options', fn ($o) => $o->where('option_text', 'like', $term))
                    ->orWhereHas('paper', function ($p) use ($term) {
                        $p->where('source_file', 'like', $term)
                            ->orWhere('paper_code', 'like', $term)
                            ->orWhere('session', 'like', $term);
                    })
                    ->orWhereHas('assets', fn ($a) => $a->where('caption', 'like', $term));
            });
        }

        if ($this->year !== null) {
            $query->whereHas('paper', fn ($p) => $p->where('year', $this->year));
        }
        if ($this->sessionCode !== null && $this->sessionCode !== '') {
            $query->whereHas('paper', fn ($p) => $p->where('session_code', $this->sessionCode));
        }
        if ($this->paperNumber !== null) {
            $query->whereHas('paper', fn ($p) => $p->where('paper_number', $this->paperNumber));
        }
        if ($this->variant !== null && $this->variant !== '') {
            $query->whereHas('paper', fn ($p) => $p->where('variant', $this->variant));
        }
        if ($this->layoutType !== null && $this->layoutType !== '') {
            $query->where('layout_type', $this->layoutType);
        }
        if ($this->visibility !== null && $this->visibility !== '') {
            $query->where('visibility', $this->visibility);
        }
        if ($this->reviewStatus !== null && $this->reviewStatus !== '') {
            $query->where('review_status', $this->reviewStatus);
        }
        if ($this->qaStatus !== null && $this->qaStatus !== '') {
            $query->where('qa_status', $this->qaStatus);
        }

        if ($this->needsReview === 'yes') {
            $query->where('needs_review', true);
        } elseif ($this->needsReview === 'no') {
            $query->where('needs_review', false);
        }

        $questionImageRoles = ['question_diagram', 'question_image_between_text', 'question_image_after_text', 'question_extra'];
        if ($this->hasQuestionImages === 'yes') {
            $query->whereHas('assets', fn ($a) => $a->whereIn('role', $questionImageRoles));
        } elseif ($this->hasQuestionImages === 'no') {
            $query->whereDoesntHave('assets', fn ($a) => $a->whereIn('role', $questionImageRoles));
        }

        if ($this->hasOptionImages === 'yes') {
            $query->whereHas('assets', fn ($a) => $a->where('role', 'option_image'));
        } elseif ($this->hasOptionImages === 'no') {
            $query->whereDoesntHave('assets', fn ($a) => $a->where('role', 'option_image'));
        }

        if ($this->hasOptionTable === 'yes') {
            $query->has('optionTable');
        } elseif ($this->hasOptionTable === 'no') {
            $query->doesntHave('optionTable');
        }

        if ($this->topic === 'untagged') {
            $query->doesntHave('topics');
        } elseif ($this->topic !== null && $this->topic !== '') {
            $topicId = (int) $this->topic;
            $query->whereHas('topics', fn ($t) => $t->where('topics.id', $topicId));
        }
    }

    /** @return array<int,int> */
    private function yearOptions(): array
    {
        return Paper::query()
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();
    }

    /** @return array<int,int> */
    private function paperNumberOptions(): array
    {
        return Paper::query()
            ->whereNotNull('paper_number')
            ->distinct()
            ->orderBy('paper_number')
            ->pluck('paper_number')
            ->all();
    }

    /** @return array<int,string> */
    private function variantOptions(): array
    {
        return Paper::query()
            ->whereNotNull('variant')
            ->distinct()
            ->orderBy('variant')
            ->pluck('variant')
            ->all();
    }
}
