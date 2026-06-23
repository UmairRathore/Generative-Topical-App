<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Question bank questions are never destroyed — the "delete" action soft-deletes
 * (recoverable from the Trash view). This adds the deleted_at column SoftDeletes needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
