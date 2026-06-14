<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('grade_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('section', 10)->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('v2_schools');
            $table->foreign('grade_id')->references('id')->on('v2_grades');
            $table->foreign('subject_id')->references('id')->on('v2_subjects');
            $table->unique(['school_id', 'grade_id', 'subject_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_classes');
    }
};
