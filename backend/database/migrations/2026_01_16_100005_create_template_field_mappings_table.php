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
        Schema::create('template_field_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->onDelete('cascade');
            $table->string('role')->comment('Role this field is for');
            $table->integer('page')->default(1);
            $table->decimal('x', 5, 4)->comment('X position as percentage (0-1)');
            $table->decimal('y', 5, 4)->comment('Y position as percentage (0-1)');
            $table->decimal('width', 5, 4)->comment('Width as percentage (0-1)');
            $table->decimal('height', 5, 4)->comment('Height as percentage (0-1)');
            $table->timestamps();

            $table->index(['template_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_field_mappings');
    }
};
