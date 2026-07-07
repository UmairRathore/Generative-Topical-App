<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_notes_pages - one BlockNote document per page
|--------------------------------------------------------------------------
| document_json holds the editor's native block array verbatim (no per-block
| rows). plain_text + block_types_json are denormalized on every save so
| search ("find all flashcard blocks") never has to parse the document.
| content_version is an optimistic-concurrency counter: a stale autosave is
| rejected with 409 instead of silently clobbering a newer document.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_notes_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('v2_notes_sections')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('v2_schools')->cascadeOnDelete();

            $table->string('title', 200)->default('Untitled');
            $table->string('icon', 16)->nullable();

            $table->longText('document_json')->nullable();   // BlockNote top-level block array
            $table->longText('plain_text')->nullable();      // flattened text of all blocks (search)
            $table->json('block_types_json')->nullable();    // distinct custom block types present

            // Curriculum tags (mirror v2_student_mistakes denormalization).
            $table->foreignId('subject_id')->nullable()->constrained('v2_subjects')->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('v2_topics')->nullOnDelete();
            $table->foreignId('subtopic_id')->nullable()->constrained('v2_subtopics')->nullOnDelete();

            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('save_status', 16)->default('saved'); // saved | saving | error (server last-known)
            $table->unsignedInteger('content_version')->default(1);
            $table->timestamp('last_edited_at')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'is_archived']);
            $table->index(['student_id', 'subject_id', 'topic_id']);
            $table->index(['section_id', 'sort_order']);
            // Shipped now so the LIKE -> FULLTEXT search upgrade is a one-query
            // change. MySQL only - sqlite (the test driver) has no FULLTEXT.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['title', 'plain_text']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_notes_pages');
    }
};
