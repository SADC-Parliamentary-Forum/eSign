<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Explicitly drop the check constraint that might have been left behind
        // We use raw SQL because Schema builder doesn't support dropping constraints easily on all drivers
        // and we want to target this specific Postgres constraint.
        DB::statement('ALTER TABLE template_fields DROP CONSTRAINT IF EXISTS template_fields_type_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration generally necessary for this fix, 
        // but we could re-add it if we knew the exact definition.
        // For debugging/fixing bad state, it's safer to leave empty.
    }
};
