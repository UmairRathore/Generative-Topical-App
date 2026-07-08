<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_ai_tutor_context_items - what grounded each assistant turn
|--------------------------------------------------------------------------
| One row per context ingredient (question stem, worked solution asset, ...)
| that Laravel sent to the AI for a given assistant message. Powers the
| "which assets does the AI actually use" analytics without parsing prompts.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_ai_tutor_context_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('v2_ai_tutor_chats')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('v2_ai_tutor_messages')->cascadeOnDelete();
            $table->string('item_type', 40); // question_stem|options|correct_answer|diagram_reference|worked_solution|option_explanation|flashcards|memcards|mermaid|interactive_widget|behaviour|recent_stats
            $table->string('ref_table', 60)->nullable(); // e.g. v2_question_learning_assets
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('chat_id');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_ai_tutor_context_items');
    }
};
