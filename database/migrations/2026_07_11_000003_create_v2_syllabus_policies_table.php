<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_syllabus_policies - syllabus-level authoring language & policy
|--------------------------------------------------------------------------
| The official syllabus defines more than the topic hierarchy: command-word
| semantics, Language of Measurement terms, symbols/units for physical
| quantities, mathematical requirements, graph/data-presentation conventions,
| nomenclature rules and the assessment model. One row per policy_type per
| syllabus source; content is structured JSON curated FROM the source pages
| (provenance records which pages).
|
| Same review lifecycle as learning objectives: rows land 'extracted'
| (untrusted) and only 'validated' rows are served as canonical authoring
| context. These policies are constraints on HOW lessons may be written -
| they are never student-facing content themselves.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_syllabus_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_source_id')->constrained('v2_syllabus_sources')->cascadeOnDelete();

            // command_words | symbols_units | math_requirements | measurement_language
            // | graph_data_conventions | nomenclature_conventions | assessment_model
            // (validated in the model)
            $table->string('policy_type', 40);
            $table->string('title');
            $table->json('content');              // structured per policy_type
            $table->json('provenance')->nullable(); // {pdf_pages: [...], curated_from: 'file@sha256'}

            $table->string('status', 16)->default('extracted'); // extracted -> validated | rejected
            $table->text('review_note')->nullable();
            $table->string('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_source_id', 'policy_type'], 'v2_syl_policy_src_type_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_syllabus_policies');
    }
};
