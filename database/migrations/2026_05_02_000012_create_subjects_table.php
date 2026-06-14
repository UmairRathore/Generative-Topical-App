<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qualification_id')->constrained('qualifications')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['qualification_id', 'slug']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
