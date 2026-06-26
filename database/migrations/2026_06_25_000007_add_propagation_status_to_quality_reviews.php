<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_quality_reviews.propagation_status
|--------------------------------------------------------------------------
| A material-error review is DECIDED in Phase 2, but its global historical
| propagation (voiding + recomputing every affected past exam) runs in Phase 3.
| propagation_status marks that hand-off, kept separate from `status`
| (open | decided) so a review can be fully decided while propagation is still
| queued:
|   null                 - nothing to propagate (correct / cosmetic).
|   propagation_pending  - confirmed material error awaiting the Phase 3 job.
|   propagated           - the Phase 3 job has run for this review.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_quality_reviews', function (Blueprint $table) {
            $table->string('propagation_status', 24)->nullable()->after('outcome');
            $table->index('propagation_status');
        });
    }

    public function down(): void
    {
        Schema::table('v2_quality_reviews', function (Blueprint $table) {
            $table->dropIndex(['propagation_status']);
            $table->dropColumn('propagation_status');
        });
    }
};
