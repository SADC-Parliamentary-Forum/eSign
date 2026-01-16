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
        Schema::create('document_signers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->onDelete('cascade');
            $table->foreignUuid('user_id')->nullable()->constrained('users'); // Null if guest signer
            $table->string('email'); // For notifications
            $table->string('name');
            $table->integer('signing_order')->default(1); // For sequential signing
            $table->enum('status', ['pending', 'notified', 'viewed', 'signed', 'declined'])->default('pending');
            $table->string('access_token', 64)->unique(); // For guest access
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->string('ip_address')->nullable(); // Captured when signed
            $table->string('user_agent')->nullable(); // Captured when signed
            $table->timestamps();

            $table->index(['document_id', 'signing_order']);
            $table->index('access_token');
            $table->index('email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signers');
    }
};
