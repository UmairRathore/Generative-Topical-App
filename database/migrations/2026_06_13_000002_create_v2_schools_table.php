<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('campus_name')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_email');
            $table->string('contact_phone', 50)->nullable();
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->string('license_tier', 50)->default('standard');
            $table->integer('max_teachers')->default(30);
            $table->integer('max_students')->default(500);
            $table->enum('status', ['inactive', 'active', 'suspended', 'cancelled'])->default('inactive');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_schools');
    }
};
