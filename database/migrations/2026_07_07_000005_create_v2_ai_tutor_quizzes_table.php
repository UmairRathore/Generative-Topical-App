<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_ai_tutor_quizzes - AI-generated mini quizzes inside a tutor chat
|--------------------------------------------------------------------------
| Temporary tutor practice content. NEVER part of the official question bank
| and never written to any v2_exam_* table. question_id has no FK on purpose
| (bank immutability - see v2_ai_tutor_chats).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_ai_tutor_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('v2_ai_tutor_chats')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('question_id')->nullable()->index();
            $table->foreignId('student_mistake_id')->nullable()->constrained('v2_student_mistakes')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('status', 20)->default('ready'); // ready|attempted
            $table->string('model', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_ai_tutor_quizzes');
    }
};
