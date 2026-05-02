<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->text('source_path');
            $table->string('source_file')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total_papers')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('imported_questions')->default(0);
            $table->unsignedInteger('skipped_questions')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
