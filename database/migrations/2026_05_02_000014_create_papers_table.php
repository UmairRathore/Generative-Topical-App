<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('source_file')->unique();
            $table->string('paper_code');
            $table->unsignedTinyInteger('paper_number')->nullable();
            $table->string('variant', 8)->nullable();
            $table->string('session', 32);
            $table->string('session_code', 8)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->unsignedSmallInteger('imported_questions_count')->default(0);
            $table->unsignedSmallInteger('demo_safe_questions_count')->default(0);
            $table->json('raw_meta')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'paper_code', 'session', 'year', 'variant'], 'papers_subject_paper_session_unique');
            $table->index(['subject_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papers');
    }
};
