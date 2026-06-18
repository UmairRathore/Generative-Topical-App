<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('generated_by')->nullable();   // branch admin id

            $table->string('period_key', 20);                          // monthly / quarter / year / all
            $table->string('period_label');
            $table->timestamp('range_from')->nullable();
            $table->timestamp('range_to')->nullable();

            // denormalised summary (so reports can be listed/queried without parsing JSON)
            $table->unsignedTinyInteger('overall_avg')->nullable();
            $table->unsignedSmallInteger('tests_count')->default(0);
            $table->string('source', 20)->default('template');        // openai / template / none

            // the report content (stats + narrative) — PDF is rendered from this at runtime
            $table->json('payload');

            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('v2_schools');
            $table->foreign('branch_id')->references('id')->on('v2_branches')->nullOnDelete();
            $table->foreign('student_id')->references('id')->on('v2_students')->cascadeOnDelete();
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_reports');
    }
};
