<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_notes_imports - Save-to-Notes provenance ledger
|--------------------------------------------------------------------------
| One APPEND-ONLY row per successful import into a note. Saving the same
| source five times creates five rows (imports are never deduplicated).
|
| Identity is the EXACT source object, so the Save buttons can report "saved
| N times" per specific source and never mark a sibling from the same
| question as saved:
|   source = mistake       -> source_id = v2_student_mistakes.id
|   source = asset         -> source_id = v2_question_learning_assets.id
|   source = widget_state  -> source_id = the interactive_widget asset id
|   source = ai_answer     -> source_id = v2_ai_tutor_messages.id
| question_id is retained for grouping/indexing only. source_id has no FK
| (it points at several tables and the question bank stays immutable).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_notes_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('v2_students')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('v2_notes_pages')->cascadeOnDelete();
            $table->string('source', 20);              // mistake|asset|widget_state|ai_answer
            $table->unsignedBigInteger('source_id');   // the EXACT source object id
            $table->unsignedBigInteger('question_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'source', 'source_id']); // provenance lookup
            $table->index(['student_id', 'question_id']);         // grouping
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_notes_imports');
    }
};
