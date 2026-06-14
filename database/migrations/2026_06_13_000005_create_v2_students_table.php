<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('roll_number', 100)->nullable();
            $table->string('grade', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['roll_number', 'school_id']);
            $table->foreign('school_id')->references('id')->on('v2_schools');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_students');
    }
};
