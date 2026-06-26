<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_exam_questions: void provenance
|--------------------------------------------------------------------------
| is_voided stays the single source of truth for marks/analytics. void_source
| records WHO/WHY a pivot was excluded so a teacher's deliberate local void is
| never confused with a Support quality-confirmed propagation:
|   teacher        - a teacher voided it for their own exam (existing engine).
|   quality_review - the Phase 3 material-error propagation voided it globally;
|                    void_quality_review_id links the originating review.
| The column default makes every existing (teacher-voided) pivot 'teacher', so no
| separate data backfill is needed.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_exam_questions', function (Blueprint $table) {
            $table->enum('void_source', ['teacher', 'quality_review'])->default('teacher')->after('voided_at');
            $table->unsignedBigInteger('void_quality_review_id')->nullable()->after('void_source');
            $table->index('void_quality_review_id');
        });
    }

    public function down(): void
    {
        Schema::table('v2_exam_questions', function (Blueprint $table) {
            $table->dropIndex(['void_quality_review_id']);
            $table->dropColumn(['void_source', 'void_quality_review_id']);
        });
    }
};
