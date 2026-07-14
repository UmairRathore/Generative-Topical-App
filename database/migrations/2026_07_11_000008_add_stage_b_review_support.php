<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Stage B review support (additive)
|--------------------------------------------------------------------------
| 1. v2_review_signoffs.lesson_sha256 - a Stage B approval binds the EXACT
|    lesson artifact hash on top of the existing plan/foundation bindings.
| 2. v2_authoring_review_feedback - structured "Needs Revision" feedback from
|    the Super Admin visual review. Additive records only: feedback never
|    mutates the lesson artifact, and history is never overwritten.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_review_signoffs', function (Blueprint $table) {
            $table->string('lesson_sha256', 64)->nullable()->after('plan_sha256');
        });

        Schema::create('v2_authoring_review_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('artifact_id');              // safe catalog slug, not a path
            $table->string('lesson_sha256', 64);        // the exact artifact the feedback reviewed
            $table->string('scope');                    // same scope family as review signoffs
            $table->string('reviewer');                 // authenticated super admin identity
            $table->string('severity');                 // minor | major
            $table->text('overall_note');
            $table->json('items');                      // [{severity, category, phase_id, block_id, note}]
            $table->timestamps();

            $table->index(['artifact_id', 'created_at'], 'v2_arf_artifact_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_authoring_review_feedback');
        Schema::table('v2_review_signoffs', function (Blueprint $table) {
            $table->dropColumn('lesson_sha256');
        });
    }
};
