<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_notes_page_versions - lightweight page history (last N snapshots)
|--------------------------------------------------------------------------
| A snapshot is a straight copy of the page's title + document_json at save
| time. Restore copies a snapshot back (after snapshotting the current
| state). Pruned to the newest 20 per page; created_at only, never updated.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_notes_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('v2_notes_pages')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();

            $table->string('title', 200);
            $table->longText('document_json')->nullable();
            $table->unsignedInteger('content_version');

            $table->timestamp('created_at')->nullable();

            $table->index(['page_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_notes_page_versions');
    }
};
