<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_student_mistakes - the Mistake Bank (Learning Hub)
|--------------------------------------------------------------------------
| ONE persistent row per (student, question). A wrong answer creates it; a
| repeat wrong answer increments mistake_count (never a duplicate row). Rows
| are never deleted - mastered/archived stay for history. Subject/topic/etc.
| are denormalized at capture so the grouped list + analytics avoid joins.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_student_mistakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('v2_schools')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('v2_questions')->cascadeOnDelete();

            // Denormalized-at-capture for fast grouping/filtering.
            $table->foreignId('subject_id')->constrained('v2_subjects')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('v2_topics')->nullOnDelete();
            $table->foreignId('subtopic_id')->nullable()->constrained('v2_subtopics')->nullOnDelete();
            $table->string('difficulty', 16)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('source_paper')->nullable();

            // Provenance.
            $table->foreignId('first_wrong_attempt_id')->nullable()->constrained('v2_exam_attempts')->nullOnDelete();
            $table->foreignId('latest_wrong_attempt_id')->nullable()->constrained('v2_exam_attempts')->nullOnDelete();
            $table->foreignId('latest_exam_id')->nullable()->constrained('v2_exams')->nullOnDelete();

            $table->char('selected_option', 1)->nullable();
            $table->char('correct_option', 1)->nullable();

            $table->unsignedInteger('mistake_count')->default(1);
            $table->timestamp('first_wrong_at')->nullable();
            $table->timestamp('last_wrong_at')->nullable();

            // Mastery lifecycle: new -> reviewed -> practiced -> mastered -> archived.
            $table->string('status', 16)->default('new');
            $table->unsignedInteger('review_count')->default(0);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable(); // 0-100
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('mastered_at')->nullable();

            $table->timestamps();

            $table->unique(['student_id', 'question_id']);
            $table->index(['student_id', 'status']);
            $table->index(['student_id', 'subject_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_student_mistakes');
    }
};
