<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_topics
|--------------------------------------------------------------------------
| Syllabus topics, sourced from
|   storage/syllabus/physics/Alevels/subject_content_a_level_physics.json
| 25 topics for CAIE A Level Physics 9702. Scoped to a v2_subjects row.
| external_id mirrors the syllabus topic id ("1".."25").
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('v2_subjects')->cascadeOnDelete();
            $table->string('external_id', 16);          // syllabus id: "1".."25"
            $table->string('title');                    // "Kinematics"
            $table->string('level', 16)->nullable();    // "AS" / "A Level"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['subject_id', 'external_id']);
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_topics');
    }
};
