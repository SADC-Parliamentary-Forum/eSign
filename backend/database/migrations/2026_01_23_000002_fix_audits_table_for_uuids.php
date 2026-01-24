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
        Schema::table('audits', function (Blueprint $table) {
            // Change user_id to string to support UUIDs
            $table->string('user_id')->nullable()->change();

            // Change auditable_id to string to support UUIDs
            $table->string('auditable_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            // Revert back (this might fail if there are non-numeric UUIDs in data, but ok for down)
            // We use explicit text casting in raw sql if needed, but for Schema builder:
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('auditable_id')->change();
        });
    }
};
