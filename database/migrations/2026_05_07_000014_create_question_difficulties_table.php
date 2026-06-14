<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_difficulties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->unique()->constrained('questions')->cascadeOnDelete();
            $table->string('overall', 16);
            $table->decimal('score', 4, 3);
            $table->decimal('reasoning_complexity', 4, 3)->default(0);
            $table->decimal('calculation_complexity', 4, 3)->default(0);
            $table->decimal('conceptual_depth', 4, 3)->default(0);
            $table->decimal('multi_topic_dependency', 4, 3)->default(0);
            $table->decimal('visual_interpretation', 4, 3)->default(0);
            $table->decimal('trap_probability', 4, 3)->default(0);
            $table->unsignedSmallInteger('estimated_time_seconds')->default(60);
            $table->string('primary_difficulty_driver', 40);
            $table->json('difficulty_reason')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('classifier_version', 32)->nullable();
            $table->timestamps();

            $table->index('overall');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_difficulties');
    }
};
