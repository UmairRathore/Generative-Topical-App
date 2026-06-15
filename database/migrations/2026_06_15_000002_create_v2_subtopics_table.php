<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_subtopics
|--------------------------------------------------------------------------
| Syllabus subtopics under each topic (e.g. "2.1"). external_id mirrors the
| syllabus subtopic id. Learning objectives are intentionally not stored as
| a separate table for the demo (the test generator filters by topic /
| subtopic only); the syllabus PDF/JSON remains the source of record.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_subtopics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('v2_topics')->cascadeOnDelete();
            $table->string('external_id', 16);          // syllabus id: "1.1"
            $table->string('title');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['topic_id', 'external_id']);
            $table->index('topic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_subtopics');
    }
};
