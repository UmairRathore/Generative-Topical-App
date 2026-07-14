<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_review_signoffs - additive, hash-bound human sign-off records
|--------------------------------------------------------------------------
| One row per human review of a SCOPED content foundation (e.g. the academic
| foundation of one Stage A lesson). Additive by design: row-level
| reviewed_by/validated_by provenance (machine extraction, Fable curation)
| is NEVER rewritten - this table records that a named human reviewed an
| exact content state, identified by a deterministic fingerprint of the
| canonical-truth sections of the compiled packet.
|
| If any underlying canonical entry later changes, a recompiled packet
| yields a different fingerprint, so verification reports the sign-off as
| STALE rather than silently remaining valid. Scope is explicit; signing
| one lesson's foundation never approves unrelated content.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_review_signoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_source_id')->constrained('v2_syllabus_sources')->cascadeOnDelete();

            // e.g. "stage-a-foundation:5054@2023-2025:1.2:7,8,9,11,12,13"
            $table->string('scope');
            $table->string('reviewer');
            $table->string('artifact_path')->nullable();     // reviewed artifact dir/pack
            $table->char('packet_sha256', 64);               // whole packet reviewed
            $table->char('foundation_fingerprint', 64);      // canonical-truth subset hash
            $table->char('plan_sha256', 64)->nullable();     // authored plan, when reviewed too
            $table->text('notes')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index(['scope', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_review_signoffs');
    }
};
