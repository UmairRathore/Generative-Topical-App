<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_exam_questions
|--------------------------------------------------------------------------
| Frozen snapshot of which questions make up an exam (random selection is
| captured here at generation time, so the exam is stable thereafter).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('v2_exams')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('v2_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('marks')->default(1);
            $table->timestamps();

            $table->unique(['exam_id', 'question_id']);
            $table->index('exam_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_exam_questions');
    }
};
