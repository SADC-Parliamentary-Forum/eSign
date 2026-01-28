<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_docs_uid_status');
            $table->index('created_at', 'idx_docs_created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role_id', 'idx_users_role_id');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->index(['document_id', 'status'], 'idx_ds_did_status');
            $table->index('email', 'idx_ds_email');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->index(['document_id', 'user_id'], 'idx_sig_did_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_docs_uid_status');
            $table->dropIndex('idx_docs_created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_id');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropIndex('idx_ds_did_status');
            $table->dropIndex('idx_ds_email');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->dropIndex('idx_sig_did_uid');
        });
    }
};
