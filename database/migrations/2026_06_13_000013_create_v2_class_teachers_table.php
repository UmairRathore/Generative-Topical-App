<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_class_teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('school_id');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->foreign('class_id')->references('id')->on('v2_classes');
            $table->foreign('teacher_id')->references('id')->on('v2_teachers');
            $table->foreign('school_id')->references('id')->on('v2_schools');
            $table->unique(['class_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_class_teachers');
    }
};
