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
        Schema::table('document_fields', function (Blueprint $table) {
            $table->string('group_id')->nullable()->after('validation_rules');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_fields', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};
