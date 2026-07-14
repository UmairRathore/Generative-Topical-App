<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| v2_learning_objective_references - LO <-> canonical reference mappings
|--------------------------------------------------------------------------
| Many-to-many with role semantics: one LO uses several references (1.2 #4
| defines acceleration AND requires a = Δv/Δt), one reference serves many LOs
| (1.2 #13 reuses quantity:acceleration without owning a duplicate definition).
|
| A mapping row targets EITHER a v2_syllabus_references row (canonical
| academic fact) OR a keyed item inside a validated syllabus policy
| (policy_type + policy_item_key, e.g. graph_data_conventions/g_gradient_triangle)
| - exactly one target, enforced in the model/import. Policy items stay in
| their policy row (single source of truth); this table only records
| deterministic RELEVANCE so the resolver can slice policies per LO instead
| of dumping the whole syllabus at an authoring system.
|
| Roles are a deliberately small vocabulary (defines/introduces/requires/
| uses/calculates_with/interprets/applies) - practical resolution, not an
| ontology. Rows are curation output (imported with the references), so they
| carry no separate review state: they are only resolved through validated
| LOs and validated references/policies.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_learning_objective_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_objective_id')->constrained('v2_learning_objectives')->cascadeOnDelete();

            // Target A: a canonical reference row.
            $table->foreignId('syllabus_reference_id')->nullable()->constrained('v2_syllabus_references')->cascadeOnDelete();
            // Target B: a keyed item of a syllabus policy (no FK - policies are
            // keyed by (source, policy_type) and items by content key).
            $table->string('policy_type', 40)->nullable();
            $table->string('policy_item_key', 64)->nullable();

            // defines | introduces | requires | uses | calculates_with | interprets | applies
            $table->string('role', 24);
            $table->string('note')->nullable(); // optional curator remark
            $table->timestamps();

            // Explicit names: the table name is long enough that Laravel's
            // auto-generated composite-index name would exceed MySQL's 64-char limit.
            $table->index(['learning_objective_id', 'role'], 'v2_lo_ref_lo_role_idx');
            $table->index('syllabus_reference_id', 'v2_lo_ref_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_learning_objective_references');
    }
};
