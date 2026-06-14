<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('school_id');
            $table->enum('status', ['active', 'inactive', 'transferred'])->default('active');
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('v2_students');
            $table->foreign('class_id')->references('id')->on('v2_classes');
            $table->foreign('school_id')->references('id')->on('v2_schools');
            $table->unique(['student_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_student_enrollments');
    }
};
