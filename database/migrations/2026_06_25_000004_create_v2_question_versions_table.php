<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_question_versions
|--------------------------------------------------------------------------
| Immutable history of a question's content. Every save in Super Admin
| Question Management creates a new version. `snapshot` is a SELF-CONTAINED JSON
| of the question content + its options + its images metadata (image_path, role,
| option_label, bbox, dims, caption …) - enough to faithfully re-render the exact
| state a student saw. v2_questions.current_version_id points to the live version;
| v2_exam_questions.question_version_id pins the version each exam froze.
| Old image FILES are retained forever so historical snapshots still render.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_question_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->unsignedInteger('version_number');

            $table->json('snapshot');                              // {question:{…}, options:[…], images:[…]}
            $table->char('correct_answer', 1)->nullable();         // denormalised for cheap marking
            $table->text('change_summary')->nullable();
            $table->string('review_reason', 60)->nullable();

            $table->unsignedBigInteger('quality_review_id')->nullable(); // the review that produced this version
            $table->unsignedBigInteger('created_by')->nullable();        // super admin (null for the v1 backfill)

            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('v2_questions')->cascadeOnDelete();
            $table->foreign('quality_review_id')->references('id')->on('v2_quality_reviews')->nullOnDelete();
            $table->unique(['question_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_question_versions');
    }
};
