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
        Schema::table('templates', function (Blueprint $table) {
            // Governance lifecycle status
            $table->enum('status', ['DRAFT', 'REVIEW', 'APPROVED', 'ACTIVE', 'ARCHIVED'])->default('DRAFT')->after('description');

            // Document fingerprint for AI matching
            $table->string('document_fingerprint', 64)->nullable()->after('file_hash')->comment('Structure hash for AI matching');

            // Workflow configuration
            $table->enum('workflow_type', ['SEQUENTIAL', 'PARALLEL', 'MIXED'])->default('SEQUENTIAL')->after('document_fingerprint');

            // Versioning
            $table->integer('version')->default(1)->after('workflow_type');

            // Governance tracking
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->after('version');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('approved_by')->nullable()->constrained('users')->after('reviewed_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // Financial threshold requirement
            $table->boolean('amount_required')->default(false)->after('approved_at')->comment('Does this template require financial amount?');

            $table->index('status');
            $table->index('document_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['approved_by']);

            $table->dropColumn([
                'status',
                'document_fingerprint',
                'workflow_type',
                'version',
                'reviewed_by',
                'reviewed_at',
                'approved_by',
                'approved_at',
                'amount_required'
            ]);
        });
    }
};
