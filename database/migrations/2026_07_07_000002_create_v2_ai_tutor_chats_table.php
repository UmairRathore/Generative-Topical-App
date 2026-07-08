<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_ai_tutor_chats - Student AI Tutor conversations
|--------------------------------------------------------------------------
| One chat per (student, mistake, source_type, active). source_type supports
| question|mistake|subtopic|topic|subject, but V1 activates only question and
| mistake - both authorized through the owned, released StudentMistake.
| question_id deliberately has NO foreign key: the question bank is immutable
| and a chat must never cascade into it.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_ai_tutor_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('v2_schools')->cascadeOnDelete();

            $table->string('source_type', 20); // question|mistake|subtopic|topic|subject
            $table->unsignedBigInteger('question_id')->nullable()->index();
            $table->foreignId('student_mistake_id')->nullable()->constrained('v2_student_mistakes')->cascadeOnDelete();

            // Denormalized from the mistake for analytics without joins.
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->unsignedBigInteger('subtopic_id')->nullable();

            $table->string('status', 20)->default('active'); // active|archived
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // Named explicitly: the auto-generated name exceeds MySQL's 64-char limit.
            $table->index(['student_id', 'student_mistake_id', 'source_type'], 'v2_ai_tutor_chats_student_mistake_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_ai_tutor_chats');
    }
};
