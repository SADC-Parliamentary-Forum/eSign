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
            $table->enum('signature_level', ['SIMPLE', 'ADVANCED', 'QUALIFIED'])
                ->default('SIMPLE')
                ->after('status');

            $table->string('evidence_package_path')->nullable()->after('file_hash');
            $table->timestamp('evidence_generated_at')->nullable()->after('evidence_package_path');
            $table->decimal('trust_score', 5, 2)->nullable()->after('evidence_generated_at');
            $table->json('trust_breakdown')->nullable()->after('trust_score');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('verification_method')->nullable()->after('declined_reason');
            $table->timestamp('verification_completed_at')->nullable()->after('verification_method');
            $table->json('verification_metadata')->nullable()->after('verification_completed_at');
            $table->string('ip_address', 45)->nullable()->after('verification_metadata');
            $table->json('device_fingerprint')->nullable()->after('ip_address');
            $table->json('geolocation')->nullable()->after('device_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'signature_level',
                'evidence_package_path',
                'evidence_generated_at',
                'trust_score',
                'trust_breakdown',
            ]);
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn([
                'verification_method',
                'verification_completed_at',
                'verification_metadata',
                'ip_address',
                'device_fingerprint',
                'geolocation',
            ]);
        });
    }
};
