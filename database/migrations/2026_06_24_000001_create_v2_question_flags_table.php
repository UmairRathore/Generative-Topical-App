<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_question_flags
|--------------------------------------------------------------------------
| Teacher-submitted "this question looks wrong" reports. A flag pulls the
| question out of the live pool (status -> under_review) immediately and lands
| here for a super-admin to fix or dismiss in the backend. Both the note and
| screenshot are optional (encouraged). One open flag per (question, teacher);
| re-flagging updates the existing open row rather than duplicating.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_question_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('school_id')->nullable();           // reporting teacher's school, for context
            $table->unsignedBigInteger('flagged_by_teacher_id')->nullable();

            $table->string('reason', 40);                                  // incomplete_text | image_issue | wrong_answer | formatting | other
            $table->text('note')->nullable();                              // optional free-text detail
            $table->string('screenshot_path')->nullable();                 // optional uploaded image (public disk)

            $table->string('status', 16)->default('open');                 // open | resolved | dismissed
            $table->unsignedBigInteger('resolved_by')->nullable();         // super-admin who closed it
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('v2_questions')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('v2_schools')->nullOnDelete();
            $table->foreign('flagged_by_teacher_id')->references('id')->on('v2_teachers')->nullOnDelete();

            $table->index(['status', 'question_id']);
            $table->index(['question_id', 'flagged_by_teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_question_flags');
    }
};
