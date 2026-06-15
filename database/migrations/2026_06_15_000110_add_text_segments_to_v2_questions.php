<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Add text segments to v2_questions
|--------------------------------------------------------------------------
| Some questions place a diagram BETWEEN parts of the stem. The source splits
| the text into the part before the in-between diagram and the part after it.
| Storing both lets the app render: text_before -> between images -> text_after
| -> after images, instead of dumping all diagrams after the full text.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->longText('text_before')->nullable()->after('question_text');
            $table->longText('text_after')->nullable()->after('text_before');
        });
    }

    public function down(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->dropColumn(['text_before', 'text_after']);
        });
    }
};
