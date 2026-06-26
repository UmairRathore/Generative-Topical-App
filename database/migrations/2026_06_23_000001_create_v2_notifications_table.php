<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_notifications
|--------------------------------------------------------------------------
| In-app notifications for V2 roles. Polymorphic recipient (notifiable_*) so the
| same table can later serve students/admins; for now only Teacher rows are
| written. Two categories with different lifecycles:
|   - update    : transient operational alerts (exam today, results due) - age out.
|   - attention : persistent "student needs attention" cases - created when a weak
|                 topic is detected, updated in place each daily run, and
|                 auto-resolved (resolved_at) when the student improves.
| dedupe_key makes the daily generator idempotent (updateOrCreate, no re-spam).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');

            // Polymorphic recipient. Teacher today; Student/BranchAdmin later.
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');

            $table->string('category', 20);                 // update | attention
            $table->string('type', 40);                     // student_attention | exam_today | results_due | submissions

            // Attention context (null for operational updates).
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Stable key for updateOrCreate so re-runs update one row instead of duplicating.
            $table->string('dedupe_key')->nullable();

            // Display + trend payload (title, body, topic, flagged/current %, target, subtopics[], url).
            $table->json('data');

            $table->timestamp('read_at')->nullable();       // bell unread state
            $table->timestamp('resolved_at')->nullable();   // attention auto-resolve on improvement
            $table->timestamp('snoozed_until')->nullable(); // snooze

            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('v2_schools')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('v2_students')->nullOnDelete();
            $table->foreign('subject_id')->references('id')->on('v2_subjects')->nullOnDelete();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->index(['notifiable_type', 'notifiable_id', 'resolved_at']);
            $table->unique(['notifiable_type', 'notifiable_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_notifications');
    }
};
