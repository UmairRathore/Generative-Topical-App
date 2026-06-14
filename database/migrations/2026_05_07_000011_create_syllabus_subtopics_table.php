<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabus_subtopics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_topic_id')->constrained('syllabus_topics')->cascadeOnDelete();
            $table->string('external_id', 16);
            $table->string('title', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->unique(['syllabus_topic_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabus_subtopics');
    }
};
