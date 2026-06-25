<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_quality_reviews
|--------------------------------------------------------------------------
| One Support Team review per question-under-review. The teacher/student
| v2_question_flags rows are the "reports" that feed it (linked via
| v2_question_flags.quality_review_id). The review records the outcome and
| links to the corrected version (resulting_version_id). The 4-outcome decision
| UI + propagation audit columns are added in later phases — this is the entity.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');

            $table->string('status', 16)->default('open');        // open | decided
            $table->string('outcome', 20)->nullable();            // correct | cosmetic | material (Phase 2)
            $table->string('reason_category', 40)->nullable();
            $table->json('source_checked')->nullable();           // checklist of official sources verified

            $table->unsignedBigInteger('reviewed_by')->nullable(); // super admin
            $table->timestamp('reviewed_at')->nullable();

            // The corrected version this review produced (plain column — versions are
            // created after this table, so no hard FK to avoid a circular dependency).
            $table->unsignedBigInteger('resulting_version_id')->nullable();

            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('v2_questions')->cascadeOnDelete();
            $table->index(['question_id', 'status']);
            $table->index('resulting_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_quality_reviews');
    }
};
