<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->string('address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->foreign('school_id')->references('id')->on('v2_schools');
        });

        Schema::create('v2_branch_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('must_change_password')->default(true);
            $table->unsignedInteger('session_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('school_id')->references('id')->on('v2_schools');
            $table->foreign('branch_id')->references('id')->on('v2_branches');
        });

        // Nullable branch_id on the tenant tables (existing rows stay valid;
        // the seeder backfills them). Branch admin is scoped on these.
        foreach (['v2_classes', 'v2_teachers', 'v2_students', 'v2_student_enrollments'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('school_id');
                $table->foreign('branch_id')->references('id')->on('v2_branches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['v2_classes', 'v2_teachers', 'v2_students', 'v2_student_enrollments'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        Schema::dropIfExists('v2_branch_admins');
        Schema::dropIfExists('v2_branches');
    }
};
