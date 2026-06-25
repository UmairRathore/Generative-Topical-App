<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Question voiding (the marksheet engine)
|--------------------------------------------------------------------------
| When a teacher confirms a flagged question is bad, they VOID it for that exam.
| The pivot (v2_exam_questions) is the single source of truth: the question stays
| attached (audit + the student can see it was excluded) but is_voided = true
| removes it from every score, percentage, topic/subtopic stat and ranking.
|
| The "until results released" rule:
|   - voided BEFORE results released → official: attempt score/total recomputed.
|   - voided AFTER results released  → original marksheet preserved; adjusted_*
|     holds the advisory recompute, applied to score/total only if the school
|     opted in (apply_retro_void). No answer rows are ever touched.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_exam_questions', function (Blueprint $table) {
            $table->boolean('is_voided')->default(false)->after('marks');
            $table->string('void_reason', 60)->nullable()->after('is_voided');
            $table->unsignedBigInteger('voided_by')->nullable()->after('void_reason'); // teacher
            $table->timestamp('voided_at')->nullable()->after('voided_by');

            $table->foreign('voided_by')->references('id')->on('v2_teachers')->nullOnDelete();
            $table->index(['exam_id', 'is_voided']);
        });

        Schema::table('v2_exam_attempts', function (Blueprint $table) {
            // Advisory recompute kept when the official marksheet is preserved
            // (results already released and the school hasn't opted into retro changes).
            $table->unsignedInteger('adjusted_score')->nullable()->after('total_questions');
            $table->unsignedInteger('adjusted_total')->nullable()->after('adjusted_score');
            $table->timestamp('adjusted_at')->nullable()->after('adjusted_total');
        });

        Schema::table('v2_schools', function (Blueprint $table) {
            // Opt-in: apply post-release voids to the visible marksheet (default: preserve).
            $table->boolean('apply_retro_void')->default(false)->after('license_tier');
        });
    }

    public function down(): void
    {
        Schema::table('v2_exam_questions', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropIndex(['exam_id', 'is_voided']);
            $table->dropColumn(['is_voided', 'void_reason', 'voided_by', 'voided_at']);
        });
        Schema::table('v2_exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['adjusted_score', 'adjusted_total', 'adjusted_at']);
        });
        Schema::table('v2_schools', function (Blueprint $table) {
            $table->dropColumn('apply_retro_void');
        });
    }
};
