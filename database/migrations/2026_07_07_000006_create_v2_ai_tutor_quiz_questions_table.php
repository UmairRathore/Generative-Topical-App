<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_ai_tutor_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('v2_ai_tutor_quizzes')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->text('stem');
            $table->json('options'); // [{"label":"A","text":"..."}, ...]
            $table->string('correct_option', 5);
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_ai_tutor_quiz_questions');
    }
};
