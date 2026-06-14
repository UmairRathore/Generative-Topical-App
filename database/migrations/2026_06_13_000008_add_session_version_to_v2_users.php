<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_school_admins', function (Blueprint $table) {
            $table->integer('session_version')->default(1)->after('last_login_ip');
        });

        Schema::table('v2_teachers', function (Blueprint $table) {
            $table->integer('session_version')->default(1)->after('last_login_ip');
        });

        Schema::table('v2_students', function (Blueprint $table) {
            $table->integer('session_version')->default(1)->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('v2_school_admins', fn($t) => $t->dropColumn('session_version'));
        Schema::table('v2_teachers',      fn($t) => $t->dropColumn('session_version'));
        Schema::table('v2_students',      fn($t) => $t->dropColumn('session_version'));
    }
};
