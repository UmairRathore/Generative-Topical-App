<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Exam release window
|--------------------------------------------------------------------------
| A teacher-generated test is no longer instantly live. It is created as a
| draft and the teacher "releases" it — immediately or at a scheduled time —
| with an optional expiry. Students may only take it inside the window.
|
|   status = draft     -> created, not visible to students
|          = released  -> within [available_from, available_until] = live
|   released_at       when the teacher released it
|   available_from    access opens (release time, now or scheduled)
|   available_until   access closes (expiry); null = no expiry
|
| A student who never submits before available_until is "missed" — computed
| from the absence of a submitted attempt, no extra column needed.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_exams', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('published_at');
            $table->timestamp('available_from')->nullable()->after('released_at');
            $table->timestamp('available_until')->nullable()->after('available_from');
            $table->index('available_until');
        });

        // Existing exams were created live (status='published'). Convert them to
        // released + open-ended so the current demo keeps working unchanged.
        DB::table('v2_exams')->where('status', 'published')->update([
            'status'         => 'released',
            'released_at'    => DB::raw('COALESCE(published_at, created_at)'),
            'available_from' => DB::raw('COALESCE(published_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        DB::table('v2_exams')->where('status', 'released')->update(['status' => 'published']);
        Schema::table('v2_exams', function (Blueprint $table) {
            $table->dropIndex(['available_until']);
            $table->dropColumn(['released_at', 'available_from', 'available_until']);
        });
    }
};
