<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_school_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone', 50)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('must_change_password')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('school_id')->references('id')->on('v2_schools');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_school_admins');
    }
};
