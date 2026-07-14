<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_syllabus_references - the Canonical Syllabus Reference Library
|--------------------------------------------------------------------------
| Reusable, syllabus-source-scoped academic facts: terms, physical quantities,
| definitions, formal relationships (word + symbolic forms of ONE canonical
| relationship share ONE row) and syllabus-prescribed constants. One canonical
| understanding of "acceleration" per syllabus version instead of ten copies
| across lessons/tutor/quizzes.
|
| Deliberately NOT a universal physics ontology: every row is scoped to a
| syllabus_source (version identity) - "acceleration" for 5054@2023-2025 is a
| different row from any future 9702 entry, because depth, notation and
| exclusions differ between qualifications. payload is type-shaped JSON;
| provenance chains to validated LO wording / validated policy rows, which
| themselves chain to the sha256-pinned source document.
|
| Exclusions ("F = mv²/r is not required") are NEVER reference rows - they
| stay LO-scoped constraints so their curriculum-context meaning survives.
|
| Same review lifecycle as the rest of the foundation: rows land untrusted
| ('extracted') and only human-signed imports promote to 'validated'.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_syllabus_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_source_id')->constrained('v2_syllabus_sources')->cascadeOnDelete();

            // term | quantity | definition | relationship | constant (validated in the model)
            $table->string('reference_type', 16);
            // Stable slug within (source, type), e.g. 'acceleration', 'speed_distance_time'.
            // The same key may exist under several types (quantity:speed vs definition:speed).
            $table->string('key', 64);
            $table->string('title'); // display name / canonical term

            // Type-shaped content:
            //  quantity     {name, symbols[], units[], note?}
            //  definition   {term, text}
            //  relationship {word_form?, symbol_form?, quantities[] (quantity keys)}
            //  constant     {statement, symbol_form?, quantities[]}
            //  term         {term, related_terms[]?, note?}
            $table->json('payload');
            $table->json('provenance')->nullable(); // {learning_objective?, policy?, pdf_pages?, file?}

            $table->string('status', 16)->default('extracted'); // extracted -> validated | rejected
            $table->text('review_note')->nullable();
            $table->string('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_source_id', 'reference_type', 'key'], 'v2_syl_ref_src_type_key_unq');
            $table->index(['syllabus_source_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_syllabus_references');
    }
};
