<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_student_mistake_events - append-only history for the Mistake Bank
|--------------------------------------------------------------------------
| One row per meaningful event on a mistake. Keeps the parent v2_student_mistakes
| row a single rolled-up record while preserving full history separately.
| event_type: wrong | reviewed | asset_viewed | marked_mastered | reopened | confidence_updated
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_student_mistake_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_mistake_id')->constrained('v2_student_mistakes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('v2_questions')->cascadeOnDelete();
            $table->foreignId('attempt_id')->nullable()->constrained('v2_exam_attempts')->nullOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained('v2_exams')->nullOnDelete();

            $table->string('event_type', 24);
            $table->char('selected_option', 1)->nullable();
            $table->char('correct_option', 1)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['student_mistake_id', 'event_type']);
            // Backs the "did this attempt already record this mistake?" idempotency check.
            $table->index(['student_mistake_id', 'attempt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_student_mistake_events');
    }
};
