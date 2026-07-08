<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_ai_tutor_quiz_attempts - the student's answers to a tutor mini quiz
|--------------------------------------------------------------------------
| Learning signal only. Completely separate from official exam results
| (v2_exam_attempts / v2_exam_answers are never touched by the tutor).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_ai_tutor_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('v2_ai_tutor_quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->unsignedBigInteger('school_id')->index();
            $table->json('answers'); // {"<quiz_question_id>":"B", ...}
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_ai_tutor_quiz_attempts');
    }
};
