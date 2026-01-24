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
        // Drop the existing check constraint
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");

        // Add the new check constraint with updated values
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status::text = ANY (ARRAY['ACTIVE'::character varying, 'INVITED'::character varying, 'DISABLED'::character varying, 'INACTIVE'::character varying, 'PENDING'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status::text = ANY (ARRAY['ACTIVE'::character varying, 'INVITED'::character varying, 'DISABLED'::character varying]::text[]))");
    }
};
