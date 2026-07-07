<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Subject-aware Notes hierarchy
|--------------------------------------------------------------------------
| Notebook = subject-level container (Physics, Chemistry, ...); Section =
| chapter/topic. Both links are nullable so free-form notebooks ("My Notes")
| keep working; imports use them to suggest/provision the right home.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_notes_notebooks', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('school_id')
                ->constrained('v2_subjects')->nullOnDelete();
            $table->index(['student_id', 'subject_id'], 'v2_notes_nb_student_subject_idx');
        });

        Schema::table('v2_notes_sections', function (Blueprint $table) {
            $table->foreignId('topic_id')->nullable()->after('school_id')
                ->constrained('v2_topics')->nullOnDelete();
            $table->index(['notebook_id', 'topic_id'], 'v2_notes_sec_notebook_topic_idx');
        });
    }

    public function down(): void
    {
        Schema::table('v2_notes_sections', function (Blueprint $table) {
            $table->dropIndex('v2_notes_sec_notebook_topic_idx');
            $table->dropConstrainedForeignId('topic_id');
        });

        Schema::table('v2_notes_notebooks', function (Blueprint $table) {
            $table->dropIndex('v2_notes_nb_student_subject_idx');
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
