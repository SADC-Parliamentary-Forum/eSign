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
        Schema::table('user_signatures', function (Blueprint $table) {
            $table->enum('method', ['DRAWN', 'UPLOADED', 'TYPED'])->default('DRAWN')->after('type');
            $table->string('file_url')->nullable()->after('image_data');
            $table->string('hash', 64)->nullable()->after('file_url')->comment('SHA-256 hash of signature');
            $table->boolean('is_immutable')->default(false)->after('is_default')->comment('Locked after first use');

            $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_signatures', function (Blueprint $table) {
            $table->dropColumn(['method', 'file_url', 'hash', 'is_immutable']);
        });
    }
};
