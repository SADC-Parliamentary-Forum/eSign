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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_signer_id')->constrained()->onDelete('cascade');
            $table->enum('certificate_type', ['SELF_SIGNED', 'CA_ISSUED', 'QUALIFIED'])->default('SELF_SIGNED');
            $table->string('serial_number')->unique();
            $table->string('issuer');
            $table->string('subject');
            $table->text('public_key');
            $table->text('private_key')->nullable(); // Encrypted
            $table->timestamp('valid_from');
            $table->timestamp('valid_to');
            $table->timestamp('revoked_at')->nullable();
            $table->text('certificate_pem');
            $table->string('thumbprint')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('serial_number');
            $table->index(['document_signer_id', 'certificate_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
