<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_question_learning_assets - reusable per-question learning content
|--------------------------------------------------------------------------
| Attached to the CANONICAL question_id (never per student) so an asset is
| authored/generated once and shared by everyone. Phase 1 only DISPLAYS assets
| that already exist with a visible status; generation (manual or AI) lands here
| in a later phase. asset_key keeps worked_solution a singleton ('') while future
| flashcard/memcard "sets" store one row per item.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_question_learning_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('v2_questions')->cascadeOnDelete();

            // worked_solution | option_explanation | flashcards | memcards | mermaid
            // | interactive_widget | revision_notes | common_mistakes  (validated in the model/service)
            $table->string('asset_type', 32);
            // '' for singletons (e.g. worked_solution); an item key for sets (e.g. flashcards).
            $table->string('asset_key', 64)->default('');

            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('format', 16)->nullable(); // markdown | html | json | mermaid ...

            // draft (hidden) -> generated | reviewed | approved (visible).
            $table->string('status', 16)->default('draft');

            $table->string('source_hash', 64)->nullable();
            // Flexible provenance for Phase 2 imports: 'manual' | 'claude' | 'fable' | 'import' | a user ref.
            $table->string('generated_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'asset_type', 'asset_key'], 'v2_qla_qid_type_key_unq');
            $table->index(['question_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_question_learning_assets');
    }
};
