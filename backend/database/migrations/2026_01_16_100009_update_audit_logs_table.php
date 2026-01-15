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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('metadata')->comment('IPv4 or IPv6 address');
            $table->enum('device_type', ['WEB', 'MOBILE'])->default('WEB')->after('ip_address');
            $table->text('user_agent')->nullable()->after('device_type');

            $table->index('ip_address');
            $table->index('device_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'device_type', 'user_agent']);
        });
    }
};
