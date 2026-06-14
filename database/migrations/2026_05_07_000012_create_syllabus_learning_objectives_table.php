<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_subtopic_id')->constrained('syllabus_subtopics')->cascadeOnDelete();
            $table->unsignedSmallInteger('objective_number');
            $table->text('text');
            $table->json('sub_items')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_subtopic_id', 'objective_number'], 'slo_subtopic_objective_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus_learning_objectives');
    }
};
