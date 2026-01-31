<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Security: Track magic link usage to prevent reuse attacks
     */
    public function up(): void
    {
        Schema::create('magic_link_uses', function (Blueprint $table) {
            $table->id();
            $table->string('signature_hash', 64)->unique(); // SHA-256 hash of the signed URL
            $table->string('link_type')->default('auth'); // auth, email_verify, etc.
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('used_at');
            $table->timestamp('expires_at')->nullable();

            $table->index(['signature_hash', 'used_at']);
            $table->index('expires_at'); // For cleanup job
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('magic_link_uses');
    }
};
