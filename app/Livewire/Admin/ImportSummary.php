<?php

namespace App\Livewire\Admin;

use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Models\ImportBatch;
use App\Models\Paper;
use App\Models\Question;
use Livewire\Component;

class ImportSummary extends Component
{
    public function render()
    {
        $batches = ImportBatch::orderByDesc('id')->limit(10)->get();

        $stats = [
            'papers' => Paper::count(),
            'questions' => Question::count(),
            'demo_safe' => Question::demoSafe()->count(),
            'hidden' => Question::where('visibility', QuestionVisibility::Hidden->value)->count(),
            'admin_only' => Question::where('visibility', QuestionVisibility::AdminOnly->value)->count(),
            'review' => Question::where('review_status', QuestionReviewStatus::Review->value)->count(),
            'rejected' => Question::where('review_status', QuestionReviewStatus::Rejected->value)->count(),
            'blocker' => Question::where('qa_status', QaStatus::Blocker->value)->count(),
            'failed' => Question::where('qa_status', QaStatus::Failed->value)->count(),
            'needs_review' => Question::where('needs_review', true)->count(),
        ];

        return view('livewire.admin.import-summary', [
            'batches' => $batches,
            'stats' => $stats,
        ])->layout('components.layouts.site');
    }
}
