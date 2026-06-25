<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 — material-error propagation audit
|--------------------------------------------------------------------------
| One decision row per propagation run (the confirmed faulty version_ids are the
| job's single source of truth), plus a full audit: every exam-question pivot it
| considered and every attempt's before/after score. The composite uniques make
| the queued job safe to retry (per-pivot / per-attempt writes are insert-or-ignore
| keyed on them); the unique idempotency_key stops a double-confirm.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_quality_propagations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quality_review_id');
            $table->unsignedBigInteger('question_id');
            $table->json('version_ids');                              // confirmed faulty versions — job source of truth
            $table->unsignedBigInteger('confirmed_by')->nullable();   // super admin
            $table->timestamp('confirmed_at')->nullable();

            $table->string('status', 16)->default('pending');         // pending | running | completed | failed

            // Blast-radius snapshot taken at confirm time (preview figures).
            $table->unsignedInteger('schools_count')->default(0);
            $table->unsignedInteger('branches_count')->default(0);
            $table->unsignedInteger('teachers_count')->default(0);
            $table->unsignedInteger('exams_count')->default(0);
            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('notifications_expected')->default(0);
            $table->unsignedInteger('notifications_sent')->default(0);

            $table->string('idempotency_key', 64);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('quality_review_id')->references('id')->on('v2_quality_reviews')->cascadeOnDelete();
            $table->unique('idempotency_key');
            $table->index(['quality_review_id', 'status']);
        });

        Schema::create('v2_quality_propagation_pivots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('propagation_id');
            $table->unsignedBigInteger('exam_question_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('question_version_id')->nullable();
            // voided | already_voided_teacher_local | already_voided_quality | skipped_future_version | skipped_no_version
            $table->string('action', 32);
            $table->timestamp('processed_at')->nullable();

            $table->foreign('propagation_id')->references('id')->on('v2_quality_propagations')->cascadeOnDelete();
            $table->unique(['propagation_id', 'exam_question_id'], 'qpp_prop_eq_unique');
            $table->index('exam_id');
        });

        Schema::create('v2_quality_propagation_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('propagation_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedInteger('score_before')->nullable();
            $table->unsignedInteger('total_before')->nullable();
            $table->unsignedInteger('score_after')->nullable();
            $table->unsignedInteger('total_after')->nullable();
            $table->boolean('released')->default(false);
            $table->boolean('notified')->default(false);

            $table->foreign('propagation_id')->references('id')->on('v2_quality_propagations')->cascadeOnDelete();
            $table->unique(['propagation_id', 'attempt_id'], 'qpa_prop_att_unique');
            $table->index('exam_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_quality_propagation_attempts');
        Schema::dropIfExists('v2_quality_propagation_pivots');
        Schema::dropIfExists('v2_quality_propagations');
    }
};
