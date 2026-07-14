<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_learning_objectives - canonical syllabus coverage atoms
|--------------------------------------------------------------------------
| One row per numbered learning objective of a syllabus source (Cambridge's
| own term and numbering: objectives restart at 1 within each objective-
| carrying leaf section, so canonical identity is
| (syllabus_source_id, section_code, objective_number), e.g. 5054/2023-2025
| section '1.2' objective 7.
|
| subtopic_id is the APPLICATION mapping onto the existing (unchanged)
| v2_subtopics leaf row and is deliberately nullable + nullOnDelete: canonical
| syllabus data must survive taxonomy edits; section_code preserves identity.
|
| Deliberately NO question FK here and no pivot yet: questions stay classified
| by topic/subtopic; a question may exercise several objectives, so any future
| question<->LO evidence linkage will be a separate many-to-many table.
|
| Review lifecycle (mirrors v2_question_learning_assets): machine extraction
| lands as 'extracted' (UNTRUSTED - never fed to authoring); a human-reviewed
| overlay promotes a row to 'validated' (canonical authoring context) or
| 'rejected'. raw_extract keeps the untouched parser output for audit.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_source_id')->constrained('v2_syllabus_sources')->cascadeOnDelete();
            $table->foreignId('subtopic_id')->nullable()->constrained('v2_subtopics')->nullOnDelete();

            $table->unsignedTinyInteger('topic_number');    // official top-level topic, e.g. 1
            $table->string('section_code', 16);             // official leaf section, e.g. '1.2' or '1.5.1'
            $table->string('section_title');                // official leaf section title
            $table->unsignedSmallInteger('objective_number'); // restarts at 1 per leaf section

            $table->text('text');                 // canonical official wording
            $table->json('sub_items')->nullable(); // [{key: 'a', text: ...}, ...] for (a)(b)(c) parts
            // [{kind: 'word'|'symbol'|'constant', text: 'v = s / t', note?: ...}]
            $table->json('equations')->nullable();
            // [{type: 'exclusion'|'depth'|'condition', text: 'the structure of an oscilloscope is not required'}]
            $table->json('constraints')->nullable();

            $table->json('raw_extract')->nullable(); // verbatim parser output (audit trail)
            $table->json('provenance')->nullable();  // {pdf_pages: [...], parser: 'v1', overlay: 'file@sha256'}

            // extracted (untrusted) -> validated (canonical) | rejected.
            $table->string('status', 16)->default('extracted');
            $table->text('review_note')->nullable();
            $table->string('validated_by')->nullable(); // reviewer ref recorded by the overlay
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_source_id', 'section_code', 'objective_number'], 'v2_lo_src_section_num_unq');
            $table->index(['subtopic_id', 'status']);       // context builder: validated LOs of a leaf
            $table->index(['syllabus_source_id', 'status']); // review progress per source
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_learning_objectives');
    }
};
