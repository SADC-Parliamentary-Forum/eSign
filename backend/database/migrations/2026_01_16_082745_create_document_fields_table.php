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
        Schema::create('document_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->onDelete('cascade');
            $table->string('type'); // SIGNATURE, INITIALS, DATE, TEXT, CHECKBOX
            $table->integer('page_number');
            $table->float('x');
            $table->float('y');
            $table->float('width');
            $table->float('height');
            $table->foreignUuid('document_signer_id')->nullable()->constrained('document_signers')->onDelete('set null');
            $table->string('signer_email')->nullable(); // Fallback if no specific signer ID yet
            $table->boolean('required')->default(true);
            $table->string('label')->nullable();
            $table->json('validation_rules')->nullable();
            $table->timestamps();

            $table->index('document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_fields');
    }
};
