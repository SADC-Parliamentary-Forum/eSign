<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status::text = ANY (ARRAY['DRAFT'::character varying, 'IN_PROGRESS'::character varying, 'COMPLETED'::character varying, 'VOIDED'::character varying, 'FAILED'::character varying]::text[]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status::text = ANY (ARRAY['DRAFT'::character varying, 'IN_PROGRESS'::character varying, 'COMPLETED'::character varying, 'VOIDED'::character varying]::text[]))");
    }
};
