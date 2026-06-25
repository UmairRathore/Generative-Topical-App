<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Versioning pointers
|--------------------------------------------------------------------------
|   v2_questions.current_version_id        → the live/active content version
|   v2_exam_questions.question_version_id  → the exact version an exam froze
|   v2_question_flags.quality_review_id    → the review a report is attached to
| All nullable until the backfill runs; plain indexed columns (no hard FK) to
| keep the cross-table version pointers simple and avoid circular constraints.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('current_version_id')->nullable()->after('status');
            $table->index('current_version_id');
        });

        Schema::table('v2_exam_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('question_version_id')->nullable()->after('question_id');
            $table->index('question_version_id');
        });

        Schema::table('v2_question_flags', function (Blueprint $table) {
            $table->unsignedBigInteger('quality_review_id')->nullable()->after('exam_id');
            $table->index('quality_review_id');
        });
    }

    public function down(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->dropIndex(['current_version_id']);
            $table->dropColumn('current_version_id');
        });
        Schema::table('v2_exam_questions', function (Blueprint $table) {
            $table->dropIndex(['question_version_id']);
            $table->dropColumn('question_version_id');
        });
        Schema::table('v2_question_flags', function (Blueprint $table) {
            $table->dropIndex(['quality_review_id']);
            $table->dropColumn('quality_review_id');
        });
    }
};
