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
        Schema::table('document_signers', function (Blueprint $table) {
            if (!Schema::hasColumn('document_signers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('viewed_at');
            }
            if (!Schema::hasColumn('document_signers', 'verification_method')) {
                $table->string('verification_method')->nullable()->after('verified_at'); // EMAIL, OTP, etc.
            }
            if (!Schema::hasColumn('document_signers', 'verification_data')) {
                $table->json('verification_data')->nullable()->after('verification_method'); // Snapshot of verification proofs
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'verification_method', 'verification_data']);
        });
    }
};
