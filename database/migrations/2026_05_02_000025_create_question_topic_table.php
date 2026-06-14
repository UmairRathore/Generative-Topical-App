<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_topic', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('source', 32)->default('manual');
            $table->timestamps();

            $table->primary(['question_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_topic');
    }
};
