<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_evidence_compatibilities - scoped prior-cycle evidence decisions
|--------------------------------------------------------------------------
| One row per explicit decision that ASSESSMENT EVIDENCE (never curriculum
| truth) from an older syllabus cycle may be supplied to authoring packets of
| a newer cycle, for ONE leaf section only. Grounded in a version-gate diff
| artifact (path + sha256): if that basis file changes or disappears, the
| record is stale and the selector refuses it. No global year overrides -
| compatibility is source-pair x section specific, reviewable, and never
| implies the old questions are current-cycle papers.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_evidence_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('old_syllabus_source_id')->constrained('v2_syllabus_sources')->cascadeOnDelete();
            $table->foreignId('new_syllabus_source_id')->constrained('v2_syllabus_sources')->cascadeOnDelete();
            $table->string('section_code', 16); // leaf scope, e.g. '1.2'

            $table->json('basis');                    // reviewable compatibility reasons
            $table->string('diff_artifact_path');     // version-gate diff this rests on
            $table->char('diff_sha256', 64);          // hash of that artifact at decision time

            $table->string('status', 16)->default('proposed'); // proposed -> active
            $table->string('reviewed_by');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['old_syllabus_source_id', 'new_syllabus_source_id', 'section_code'], 'v2_evcompat_pair_section_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_evidence_compatibilities');
    }
};
