<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_question_options
|--------------------------------------------------------------------------
| The A/B/C/D options for each question (~4 per question). Mirrors the
| questions.json "options" array ({label, text, images}).
|  - text may be "" for option_images questions (the option IS an image).
|  - has_image flags options whose figure lives in v2_question_images
|    (role = "option_image", matching option_label).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('v2_questions')->cascadeOnDelete();
            $table->char('label', 1);                    // A/B/C/D
            $table->text('text')->nullable();
            $table->boolean('has_image')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['question_id', 'label']);
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_question_options');
    }
};
