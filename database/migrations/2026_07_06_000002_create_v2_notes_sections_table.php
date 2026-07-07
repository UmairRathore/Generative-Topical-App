<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_notes_sections - middle level of the Notes tree
|--------------------------------------------------------------------------
| A section groups pages inside a notebook ("Electricity" in "O Level
| Physics"). student_id/school_id are denormalized for scoping + queries.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_notes_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained('v2_notes_notebooks')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('v2_schools')->cascadeOnDelete();

            $table->string('title', 160);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);

            $table->timestamps();

            $table->index(['notebook_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_notes_sections');
    }
};
