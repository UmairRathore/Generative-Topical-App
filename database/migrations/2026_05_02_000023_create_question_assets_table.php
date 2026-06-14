<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained('question_options')->cascadeOnDelete();
            $table->string('role', 40)->index();
            $table->string('image_path');
            $table->string('disk', 32)->default('public');
            $table->unsignedSmallInteger('page')->nullable();
            $table->json('bbox')->nullable();
            $table->text('caption')->nullable();
            $table->text('ocr_text')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('diagram_labels')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['question_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_assets');
    }
};
