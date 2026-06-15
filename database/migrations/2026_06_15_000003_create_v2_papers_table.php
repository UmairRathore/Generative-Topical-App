<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_papers
|--------------------------------------------------------------------------
| One row per past paper (108 for the demo corpus). Derived from each
| questions.json "paper" object plus the folder name (9702_m16_qp_12):
|   subject_code = 9702, session_code = m|s|w, year = 20YY, variant = 11..14
| year / session_code / variant are stored explicitly so the test generator
| can filter by year range and variant without parsing strings at query time.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('v2_subjects')->cascadeOnDelete();
            $table->string('source_file')->unique();    // "9702_m16_qp_12.pdf"
            $table->string('source_paper')->index();     // folder key "9702_m16_qp_12"
            $table->string('subject_code', 16);          // "9702"
            $table->string('paper_code', 16);            // "9702/12"
            $table->unsignedTinyInteger('paper_number')->nullable(); // 1 (Paper 1 / MCQ)
            $table->string('variant', 8)->nullable();    // "11".."14"
            $table->string('session_code', 8)->nullable(); // "m" | "s" | "w"
            $table->string('session_label')->nullable(); // "February/March 2016"
            $table->unsignedSmallInteger('year')->nullable(); // 2016..2025
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->timestamps();

            $table->index(['subject_id', 'year']);
            $table->index(['year', 'session_code', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_papers');
    }
};
