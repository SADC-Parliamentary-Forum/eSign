<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users'); // Owner
            $table->string('title');
            $table->string('file_path');
            $table->string('file_hash')->unique(); // SHA-256
            $table->string('status')->default('draft'); // draft, pending, signed, rejected
            $table->string('mime_type');
            $table->bigInteger('size');
            $table->jsonb('metadata')->nullable(); // For Department, Value, Tags
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
