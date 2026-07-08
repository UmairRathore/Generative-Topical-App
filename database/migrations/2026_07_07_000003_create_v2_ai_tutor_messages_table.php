<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_ai_tutor_messages - turns inside an AI Tutor chat
|--------------------------------------------------------------------------
| Failed assistant turns are NOT stored here (only in v2_ai_interaction_logs);
| the user message is kept so a retry can re-send it.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_ai_tutor_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('v2_ai_tutor_chats')->cascadeOnDelete();
            $table->string('role', 20); // user|assistant
            $table->text('content');    // markdown
            $table->string('model', 80)->nullable();               // assistant rows only
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_ai_tutor_messages');
    }
};
