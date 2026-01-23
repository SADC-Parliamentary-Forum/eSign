<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add organizational_role_id and fill_mode to document_fields
        Schema::table('document_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('document_fields', 'organizational_role_id')) {
                $table->uuid('organizational_role_id')->nullable()->after('signer_email');
                $table->foreign('organizational_role_id')->references('id')->on('organizational_roles')->onDelete('set null');
            }
            if (!Schema::hasColumn('document_fields', 'fill_mode')) {
                $table->enum('fill_mode', ['PRE_FILL', 'SIGNER_FILL'])->default('SIGNER_FILL')->after('required');
            }
        });

        // Add organizational_role_id to document_signers
        Schema::table('document_signers', function (Blueprint $table) {
            if (!Schema::hasColumn('document_signers', 'organizational_role_id')) {
                $table->uuid('organizational_role_id')->nullable()->after('role');
                $table->foreign('organizational_role_id')->references('id')->on('organizational_roles')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            if (Schema::hasColumn('document_signers', 'organizational_role_id')) {
                $table->dropForeign(['organizational_role_id']);
                $table->dropColumn('organizational_role_id');
            }
        });

        Schema::table('document_fields', function (Blueprint $table) {
            if (Schema::hasColumn('document_fields', 'organizational_role_id')) {
                $table->dropForeign(['organizational_role_id']);
                $table->dropColumn('organizational_role_id');
            }
            if (Schema::hasColumn('document_fields', 'fill_mode')) {
                $table->dropColumn('fill_mode');
            }
        });
    }
};
