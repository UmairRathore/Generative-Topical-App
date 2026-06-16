<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Add actual pixel dimensions to v2_question_images
|--------------------------------------------------------------------------
| The stored bbox (PDF points) is reliable for most figures but is wrong for a
| minority of crops, which then render far too small. The crop's real pixel
| width is the dependable basis for on-screen sizing, so we cache it here
| (populated on import and by v2:backfill-image-dimensions).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_question_images', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('bbox');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('v2_question_images', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
    }
};
