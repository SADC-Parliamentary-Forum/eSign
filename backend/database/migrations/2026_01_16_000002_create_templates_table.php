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
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users'); // Creator
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path'); // Template PDF
            $table->string('file_hash')->nullable(); // SHA-256 of template file
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('is_public');
        });

        Schema::create('template_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->onDelete('cascade');
            $table->enum('type', ['signature', 'initials', 'date', 'text'])->default('signature');
            $table->string('signer_role')->nullable(); // "Signer 1", "Signer 2", etc.
            $table->integer('page_number');
            $table->float('x_position');
            $table->float('y_position');
            $table->float('width');
            $table->float('height');
            $table->boolean('required')->default(true);
            $table->string('label')->nullable(); // Field label for text fields
            $table->timestamps();

            $table->index('template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_fields');
        Schema::dropIfExists('templates');
    }
};
