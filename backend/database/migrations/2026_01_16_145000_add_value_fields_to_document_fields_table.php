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
            $table->text('text_value')->nullable()->after('validation_rules');
            $table->foreignUuid('signature_id')->nullable()->constrained('signatures')->onDelete('set null')->after('text_value');
            $table->timestamp('signed_at')->nullable()->after('signature_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_fields', function (Blueprint $table) {
            $table->dropForeign(['signature_id']);
            $table->dropColumn(['text_value', 'signature_id', 'signed_at']);
        });
    }
};
