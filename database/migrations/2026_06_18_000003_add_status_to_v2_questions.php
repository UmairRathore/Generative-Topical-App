<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_questions.status
|--------------------------------------------------------------------------
| Lifecycle flag for the global question pool, managed from the Super Admin
| question-bank CRUD:
|   active   - live; eligible to be drawn into generated tests (default).
|   draft    - being authored / reviewed; never used in a test.
|   archived - retired; kept for records, never used in a test.
|
| Every imported question defaults to 'active' so existing behaviour is
| unchanged. ExamService only ever selects 'active' questions.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('difficulty')->index();
        });
    }

    public function down(): void
    {
        Schema::table('v2_questions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
