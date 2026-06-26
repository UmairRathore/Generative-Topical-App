<?php

use App\Models\V2\Question;
use App\Models\V2\QuestionVersion;
use App\Services\V2\QuestionSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Backfill: version #1 for every question, stamp every exam-question pivot
|--------------------------------------------------------------------------
| Idempotent + chunked. Gives every existing question an immutable v1 snapshot
| and a current_version_id, then points every existing v2_exam_questions row at
| that v1 (the content it froze). Safe to re-run - already-backfilled rows are
| skipped by the NULL filters.
*/
return new class extends Migration
{
    public function up(): void
    {
        // 1. One v1 version per question (incl. soft-deleted - frozen exams may reference them).
        Question::withTrashed()->whereNull('current_version_id')
            ->with(['options', 'images'])
            ->chunkById(200, function ($questions) {
                foreach ($questions as $q) {
                    $version = QuestionVersion::create([
                        'question_id'    => $q->id,
                        'version_number' => 1,
                        'snapshot'       => QuestionSnapshot::capture($q),
                        'correct_answer' => $q->correct_answer,
                        'change_summary' => 'Initial version (backfill).',
                        'created_by'     => null,
                    ]);

                    DB::table('v2_questions')->where('id', $q->id)
                        ->update(['current_version_id' => $version->id]);
                }
            });

        // 2. Stamp every exam-question pivot with its question's current (v1) version.
        DB::table('v2_exam_questions')->whereNull('question_version_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $vid = DB::table('v2_questions')->where('id', $row->question_id)->value('current_version_id');
                    if ($vid) {
                        DB::table('v2_exam_questions')->where('id', $row->id)
                            ->update(['question_version_id' => $vid]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('v2_exam_questions')->update(['question_version_id' => null]);
        DB::table('v2_questions')->update(['current_version_id' => null]);
        DB::table('v2_question_versions')->truncate();
    }
};
