<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Exam results release
|--------------------------------------------------------------------------
| Submitting a test no longer reveals the score/answers to the student. The
| teacher releases results separately — either at the same time as releasing
| the test, or later from the exam's manage page. Until then a student sees
| only "submitted, awaiting results".
|
|   results_released_at  null = hidden; set = students may see their result.
|
| Existing exams are backfilled so their already-visible results stay visible.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_exams', function (Blueprint $table) {
            $table->timestamp('results_released_at')->nullable()->after('available_until');
        });

        // Preserve current behaviour for exams that already exist (results were
        // instantly visible): mark their results as released.
        DB::table('v2_exams')->whereNotNull('released_at')
            ->update(['results_released_at' => DB::raw('released_at')]);
    }

    public function down(): void
    {
        Schema::table('v2_exams', function (Blueprint $table) {
            $table->dropColumn('results_released_at');
        });
    }
};
