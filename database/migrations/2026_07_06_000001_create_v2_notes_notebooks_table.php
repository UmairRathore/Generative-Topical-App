<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_notes_notebooks - top level of the student Notes tree (Learning Hub)
|--------------------------------------------------------------------------
| Notebook -> Section -> Page. A notebook is a student-owned container
| ("O Level Physics"); archiving hides it without deleting pages.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_notes_notebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('v2_schools')->cascadeOnDelete();

            $table->string('title', 160);
            $table->string('emoji', 16)->nullable();
            $table->string('color', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);

            $table->timestamps();

            $table->index(['student_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_notes_notebooks');
    }
};
