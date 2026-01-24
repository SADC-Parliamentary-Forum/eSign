<?php
/**
 * Migration to fix template_fields type check violation
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('template_fields', function (Blueprint $table) {
            // Changing from enum to string to support more types and easier normalization
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('template_fields', function (Blueprint $table) {
            // Reverting to enum might be tricky if data has changed, so we leave as string or specify original enum
            $table->enum('type', ['signature', 'initials', 'date', 'text'])->change();
        });
    }
};
