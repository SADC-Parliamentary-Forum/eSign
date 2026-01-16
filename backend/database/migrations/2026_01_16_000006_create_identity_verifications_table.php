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
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_signer_id')->constrained()->onDelete('cascade');
            $table->enum('verification_type', ['EMAIL', 'SMS', 'OTP', 'DEVICE', 'ID_DOCUMENT']);
            $table->enum('status', ['PENDING', 'VERIFIED', 'FAILED', 'EXPIRED'])->default('PENDING');
            $table->string('verification_token')->nullable();
            $table->string('verification_code', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('device_fingerprint')->nullable();
            $table->json('geolocation')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_signer_id', 'verification_type']);
            $table->index('verification_token');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
