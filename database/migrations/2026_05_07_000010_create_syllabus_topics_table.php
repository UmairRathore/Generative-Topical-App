<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_topics', function (Blueprint $table) {
            $table->id();
            $table->string('syllabus_code', 32)->index();
            $table->string('external_id', 16);
            $table->string('title', 255);
            $table->string('level', 8);
            $table->text('intro_note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_code', 'external_id']);
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus_topics');
    }
};
