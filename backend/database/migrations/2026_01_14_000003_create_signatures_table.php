<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('signature_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->onDelete('cascade');
            $table->foreignUuid('assigned_role_id')->nullable()->constrained('roles'); // If assigned to a generic role
            $table->foreignId('assigned_user_id')->nullable()->constrained('users'); // If assigned to specific person
            $table->string('type')->default('signature'); // signature, initial, date
            $table->integer('page_number');
            $table->float('x_position');
            $table->float('y_position');
            $table->float('width');
            $table->float('height');
            $table->boolean('required')->default(true);
            $table->timestamps();
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignUuid('signature_field_id')->nullable()->constrained('signature_fields');
            $table->text('signature_data')->nullable(); // Base64 image or encrypted payload
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->string('certificate_hash')->nullable(); // For future PKI
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('signature_fields');
    }
};
