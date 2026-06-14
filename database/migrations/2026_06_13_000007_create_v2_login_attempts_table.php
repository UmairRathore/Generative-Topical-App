<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_address', 45);
            $table->string('guard', 50);
            $table->boolean('successful')->default(false);
            $table->timestamp('attempted_at')->useCurrent();
            $table->index(['email', 'ip_address']);
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_login_attempts');
    }
};
