<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Category for template organization
            $table->string('category')->nullable()->after('description');
            
            // Bulk signing support
            $table->boolean('is_bulk_enabled')->default(true)->after('is_public');
            
            // Lock field layout to prevent accidental edits
            $table->boolean('is_field_locked')->default(false)->after('is_bulk_enabled');
            
            // Default signer role (Self, External, Mixed)
            $table->string('default_signer_role')->nullable()->after('is_field_locked');
            
            // Usage tracking
            $table->unsignedInteger('usage_count')->default(0)->after('default_signer_role');
            $table->timestamp('last_used_at')->nullable()->after('usage_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'is_bulk_enabled',
                'is_field_locked',
                'default_signer_role',
                'usage_count',
                'last_used_at',
            ]);
        });
    }
};
