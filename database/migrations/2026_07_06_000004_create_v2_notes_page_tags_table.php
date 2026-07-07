<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_notes_page_tags - free-text tags on a notes page
|--------------------------------------------------------------------------
| Curriculum tags (subject/topic/subtopic) live as columns on the page;
| this table is only the student's own labels ("exam-tip", "formula").
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_notes_page_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('v2_notes_pages')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->string('tag', 48);

            $table->timestamps();

            $table->unique(['page_id', 'tag']);
            $table->index(['student_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_notes_page_tags');
    }
};
