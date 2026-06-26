<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Student question flags (tier below the teacher)
|--------------------------------------------------------------------------
| Students can now report a question they hit during an exam (or while reviewing
| their result). Unlike a teacher flag, a student flag does NOT pull the question
| from the pool - it is a soft signal routed to that exam's teacher, who makes the
| call. We reuse v2_question_flags and tag each row with its `level`:
|   - teacher : the existing behaviour (auto-hide + super-admin queue)
|   - student : pending the teacher's review; scoped to the exam it was raised on
| `exam_id` ties a student flag to the specific paper so the teacher sees it in
| context (and a future void only affects that exam's marksheets).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_question_flags', function (Blueprint $table) {
            $table->string('level', 16)->default('teacher')->after('flagged_by_teacher_id');
            $table->unsignedBigInteger('flagged_by_student_id')->nullable()->after('level');
            $table->unsignedBigInteger('exam_id')->nullable()->after('flagged_by_student_id');

            $table->foreign('flagged_by_student_id')->references('id')->on('v2_students')->nullOnDelete();
            $table->foreign('exam_id')->references('id')->on('v2_exams')->nullOnDelete();

            $table->index(['level', 'status', 'exam_id']);
            $table->index(['exam_id', 'question_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('v2_question_flags', function (Blueprint $table) {
            $table->dropForeign(['flagged_by_student_id']);
            $table->dropForeign(['exam_id']);
            $table->dropIndex(['level', 'status', 'exam_id']);
            $table->dropIndex(['exam_id', 'question_id', 'status']);
            $table->dropColumn(['level', 'flagged_by_student_id', 'exam_id']);
        });
    }
};
