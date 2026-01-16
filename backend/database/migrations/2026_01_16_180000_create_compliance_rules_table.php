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
        Schema::create('compliance_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions'); // Criteria: {"amount_gt": X, "jurisdiction": "Botswana"}
            $table->json('actions'); // Actions: {"retention_days": Y}
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_legal_hold')->default(false);
            $table->text('legal_hold_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_rules');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['is_legal_hold', 'legal_hold_reason', 'archived_at']);
        });
    }
};
