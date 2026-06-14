<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->json('headers')->nullable();
            $table->json('rows')->nullable();
            $table->string('image_path')->nullable();
            $table->string('disk', 32)->default('public');
            $table->json('bbox')->nullable();
            $table->json('diagram_labels')->nullable();
            $table->boolean('use_fallback_image')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_tables');
    }
};
