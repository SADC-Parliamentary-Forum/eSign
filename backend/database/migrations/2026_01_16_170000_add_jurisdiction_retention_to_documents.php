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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('jurisdiction')->nullable()->default('SADC'); // e.g. "Botswana", "SADC", "EU"
            $table->integer('retention_period_days')->nullable()->default(3650); // 10 years default
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->integer('default_retention_days')->nullable()->default(3650);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['jurisdiction', 'retention_period_days']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['default_retention_days']);
        });
    }
};
