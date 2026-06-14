<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_topic_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('syllabus_topic_id')->constrained('syllabus_topics')->cascadeOnDelete();
            $table->foreignId('syllabus_subtopic_id')->nullable()->constrained('syllabus_subtopics')->nullOnDelete();
            $table->foreignId('syllabus_learning_objective_id')->nullable()->constrained('syllabus_learning_objectives')->nullOnDelete();
            $table->string('relevance', 8);
            $table->decimal('score', 4, 3);
            $table->text('reason')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('classifier_version', 32)->nullable();
            $table->timestamps();

            $table->unique(
                ['question_id', 'syllabus_topic_id', 'syllabus_subtopic_id', 'syllabus_learning_objective_id'],
                'qtt_unique_path'
            );
            $table->index(['question_id', 'relevance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_topic_tags');
    }
};
