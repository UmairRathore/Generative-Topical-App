<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_exam_attempts
|--------------------------------------------------------------------------
| One student's attempt at one exam. score = number correct (auto-marked
| on submit). One attempt per student per exam.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('v2_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('v2_schools')->cascadeOnDelete();

            $table->string('status', 16)->default('in_progress'); // in_progress|submitted
            $table->unsignedSmallInteger('score')->nullable();      // correct count
            $table->unsignedSmallInteger('total_questions');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index('school_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_exam_attempts');
    }
};
