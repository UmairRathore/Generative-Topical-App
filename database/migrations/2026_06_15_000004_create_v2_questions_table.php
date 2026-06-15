<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_questions
|--------------------------------------------------------------------------
| One row per MCQ (4,320 for the demo corpus). Mirrors each question object
| in questions.json, with the test-generator filter columns denormalised
| (subject_id, year) and indexed.
|
| Notes from the source data:
|  - correct_answer is NULL in questions.json; it is populated at import time
|    by parsing storage/papers/marksheets/*.pdf.
|  - topic_id / subtopic_id are NULL until the topic-tagging pass runs.
|  - option_table holds the structured table ({headers, rows}) for the 575
|    "option_table" questions; the table IMAGE is stored in v2_question_images
|    with role = "table".
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained('v2_papers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('v2_subjects')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('v2_topics')->nullOnDelete();
            $table->foreignId('subtopic_id')->nullable()->constrained('v2_subtopics')->nullOnDelete();

            $table->unsignedSmallInteger('year')->nullable();      // denormalised for fast filtering
            $table->unsignedTinyInteger('question_number');
            $table->longText('question_text')->nullable();
            $table->string('layout_type', 48)->default('text_only');
            // text_only | question_diagram | option_table | option_images
            // | question_diagram_and_option_images | mixed

            $table->char('correct_answer', 1)->nullable();         // A/B/C/D (from mark scheme)
            $table->json('option_table')->nullable();              // {headers:[], rows:[[]]}
            $table->unsignedTinyInteger('marks')->default(1);
            $table->string('difficulty', 16)->nullable();          // easy|medium|hard (future)

            $table->unsignedSmallInteger('page_start')->nullable();
            $table->unsignedSmallInteger('page_end')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->json('warnings')->nullable();
            $table->string('source_paper')->nullable();            // "9702_m16_qp_12"
            $table->timestamps();

            $table->unique(['paper_id', 'question_number']);
            $table->index(['subject_id', 'topic_id']);
            $table->index(['subject_id', 'year']);
            $table->index('year');
            $table->index('layout_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_questions');
    }
};
