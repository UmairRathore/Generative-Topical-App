<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_syllabus_sources - canonical, version-identified syllabus documents
|--------------------------------------------------------------------------
| One row per official syllabus publication (e.g. Cambridge O Level Physics
| 5054, exams 2023-2025). This is the ROOT of curriculum provenance: learning
| objectives and authoring policies hang off a source row, never off the
| mutable v2_topics/v2_subtopics skeleton, so canonical wording stays tied to
| the exact document (sha256-pinned PDF + derived text) it came from.
|
| structure_json preserves the official section map (topics, subtopics and
| the x.y.z third level) INCLUDING the 14 intermediate headers that the
| flattened v2_subtopics tree does not store (e.g. "1.5 Forces").
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_syllabus_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('v2_subjects')->cascadeOnDelete();

            $table->string('syllabus_code', 20);   // official code, e.g. '5054'
            $table->string('version_label', 40);   // exam window, e.g. '2023-2025'
            $table->string('title');               // official document title
            $table->json('exam_years')->nullable(); // e.g. [2023, 2024, 2025]

            // Canonical binary + derived text, both content-addressed. Paths are
            // repo-relative (storage/...); binaries stay on disk per storage convention.
            $table->string('source_pdf_path');
            $table->char('source_pdf_sha256', 64);
            $table->string('extracted_text_path')->nullable();
            $table->char('extracted_text_sha256', 64)->nullable();
            $table->string('extraction_tool')->nullable(); // e.g. 'pypdf 6.14.2 (tools/extract_pdf_text.py)'

            // Official structure map written by v2:extract-learning-objectives:
            // { topics: {1: title...}, sections: [{code, title, topic, leaf, lo_count}] }
            $table->json('structure_json')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_code', 'version_label'], 'v2_syl_src_code_version_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_syllabus_sources');
    }
};
