<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained('papers')->cascadeOnDelete();
            $table->unsignedTinyInteger('question_number');
            $table->longText('question_text')->nullable();
            $table->longText('clean_question_text')->nullable();
            $table->longText('image_between_question_before_text')->nullable();
            $table->longText('image_between_question_after_text')->nullable();
            $table->char('correct_answer', 1)->nullable();
            $table->longText('explanation')->nullable();
            $table->unsignedSmallInteger('page_start')->nullable();
            $table->unsignedSmallInteger('page_end')->nullable();
            $table->string('layout_type', 40)->default('unknown');
            $table->string('qa_status', 32)->default('review');
            $table->string('review_status', 32)->default('review');
            $table->string('visibility', 32)->default('hidden');
            $table->boolean('needs_review')->default(false);
            $table->json('warnings')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['paper_id', 'question_number']);
            $table->index('layout_type');
            $table->index(['review_status', 'visibility']);
            $table->index(['qa_status', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
