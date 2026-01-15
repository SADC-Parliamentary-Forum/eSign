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
            // Add template reference
            $table->foreignUuid('template_id')->nullable()->after('user_id')
                ->constrained('templates')->nullOnDelete();

            // Add workflow fields
            $table->boolean('sequential_signing')->default(false)->after('status');
            $table->integer('current_signing_order')->default(1)->after('sequential_signing');
            $table->timestamp('expires_at')->nullable()->after('current_signing_order');
            $table->timestamp('sent_at')->nullable()->after('expires_at');
            $table->timestamp('completed_at')->nullable()->after('sent_at');

            // Add index for status queries
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn([
                'template_id',
                'sequential_signing',
                'current_signing_order',
                'expires_at',
                'sent_at',
                'completed_at',
            ]);
        });
    }
};
