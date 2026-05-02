<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_board_id')->constrained('exam_boards')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('level_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_board_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualifications');
    }
};
