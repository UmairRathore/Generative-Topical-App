<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_exams
|--------------------------------------------------------------------------
| A teacher-generated test. Bound to a class (which fixes grade + subject)
| and optionally a topic. Students enrolled in the class attempt it.
| topic_id NULL = mixed (all topics in the subject).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('v2_schools')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('v2_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('v2_subjects')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('v2_topics')->nullOnDelete();
            $table->foreignId('created_by')->constrained('v2_teachers')->cascadeOnDelete();

            $table->string('title');
            $table->unsignedTinyInteger('question_count');     // 1..40
            $table->unsignedSmallInteger('total_marks')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable(); // optional timer
            $table->unsignedSmallInteger('year_from')->nullable();        // optional generation filter
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->boolean('shuffle')->default(true);
            $table->string('status', 16)->default('published'); // draft|published|closed
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'class_id']);
            $table->index('created_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_exams');
    }
};
