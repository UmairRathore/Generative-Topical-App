<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_exam_answers
|--------------------------------------------------------------------------
| A student's chosen option for one question in their attempt. The correct
| option is snapshotted at submit time so results stay stable. Per-topic
| stats come from joining question_id -> v2_questions.topic_id.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('v2_exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('v2_questions')->cascadeOnDelete();
            $table->char('selected_option', 1)->nullable(); // A/B/C/D or null (skipped)
            $table->char('correct_option', 1)->nullable();  // snapshot at submit
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
            $table->index('attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_exam_answers');
    }
};
