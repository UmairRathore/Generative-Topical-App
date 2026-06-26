<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_question_images
|--------------------------------------------------------------------------
| Flat, authoritative image list per question (~4,747 rows). Built from:
|   - questions.json "assets" (roles: question_image_between_text,
|     question_image_after_text, option_image), PLUS
|   - the "option_table.image_path" image (role: table) for the 575
|     option_table questions, which is NOT present in "assets".
|
| RULE: image_path always points inside images_final/ - the only image
| folder the application ever reads. Stored relative; the app resolves it
| to the storage disk / R2 URL at render time.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_question_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('v2_questions')->cascadeOnDelete();
            $table->string('external_id')->nullable();   // "q002_question_diagram_01" / "q007_option_A"
            $table->string('image_path');                // "papers/.../images_final/q002_..._01.png"
            $table->string('role', 40);                  // question_image_between_text
            // | question_image_after_text | option_image | table
            $table->char('option_label', 1)->nullable(); // A/B/C/D for option_image rows
            $table->unsignedSmallInteger('page')->nullable();
            $table->json('bbox')->nullable();
            $table->longText('caption')->nullable();     // caption_short / reconstruction text
            $table->longText('ocr_text')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('diagram_labels')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'role']);
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_question_images');
    }
};
