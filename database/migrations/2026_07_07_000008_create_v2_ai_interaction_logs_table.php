<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_ai_interaction_logs - one row per AI call (ok, fallback or error)
|--------------------------------------------------------------------------
| Cost / debug / audit trail for every AI interaction, mirroring the
| v2_audit_logs shape (docs/ai-context/ai/04, rule 10). request_json is a
| REDACTED summary (never the full context; first-name-only PII standard);
| response_json is truncated.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_ai_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 50);      // class_basename, e.g. Student
            $table->unsignedBigInteger('actor_id');
            $table->string('role', 30);            // guard, e.g. v2_student
            $table->string('feature', 40);         // tutor_chat|tutor_quiz
            $table->string('source_context_type', 40)->nullable(); // question|mistake
            $table->unsignedBigInteger('source_context_id')->nullable();
            $table->string('provider', 30)->nullable();  // openai
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('status', 15);          // ok|fallback|error
            $table->text('error')->nullable();
            $table->json('request_json')->nullable();
            $table->json('response_json')->nullable();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_type', 'actor_id']);
            $table->index(['feature', 'created_at']);
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_ai_interaction_logs');
    }
};
