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
        Schema::create('template_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->onDelete('cascade');
            $table->string('role')->comment('FINANCE, HOD, SG, EXCO, or custom role');
            $table->enum('action', ['SIGN', 'APPROVE', 'ACKNOWLEDGE', 'REVIEW'])->default('SIGN');
            $table->boolean('required')->default(true);
            $table->integer('signing_order')->default(1);
            $table->timestamps();

            $table->index(['template_id', 'signing_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_roles');
    }
};
