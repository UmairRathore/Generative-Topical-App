<?php

use App\Models\V2\QualityReview;
use App\Models\V2\Question;
use Illuminate\Database\Migrations\Migration;

/*
|--------------------------------------------------------------------------
| Backfill: one open quality review per under-review question
|--------------------------------------------------------------------------
| Every question currently sitting in `under_review` needs an open
| v2_quality_reviews row so it surfaces in the Phase 2 review queue, with its
| still-unresolved flags attached as reports. Idempotent — QualityReview::openFor
| firstOrCreates the review and only attaches not-yet-linked open/escalated flags,
| so re-running is a no-op. Runs without global scopes (no auth guard in CLI).
*/
return new class extends Migration
{
    public function up(): void
    {
        Question::withoutGlobalScopes()
            ->where('status', 'under_review')
            ->orderBy('id')
            ->chunkById(500, function ($questions) {
                foreach ($questions as $question) {
                    QualityReview::openFor($question->id);
                }
            });
    }

    public function down(): void
    {
        // Reviews are never destroyed on rollback — they may already hold decisions
        // or be linked to corrected versions.
    }
};
