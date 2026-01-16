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
            // Update status enum to match new design
            $table->dropColumn('status');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['DRAFT', 'IN_PROGRESS', 'COMPLETED', 'VOIDED'])->default('DRAFT')->after('template_id');

            // Document type classification
            $table->enum('document_type', ['APPROVAL', 'ACKNOWLEDGEMENT', 'MEMO', 'FINANCIAL', 'POLICY', 'CONTRACT'])->default('APPROVAL')->after('title');

            // Financial amount for threshold enforcement
            $table->decimal('amount', 15, 2)->nullable()->after('document_type')->comment('Financial amount for threshold enforcement');

            // Workflow reference
            $table->uuid('workflow_id')->nullable()->after('template_id');

            // Signed document file
            $table->string('file_signed')->nullable()->after('file_path')->comment('Final signed PDF');

            // Void tracking
            $table->timestamp('voided_at')->nullable()->after('completed_at');
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by');

            $table->index('status');
            $table->index('document_type');
            $table->index('workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'document_type',
                'amount',
                'workflow_id',
                'file_signed',
                'voided_at',
                'voided_by',
                'void_reason'
            ]);
        });

        // Restore old status enum
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('status')->default('draft');
        });
    }
};
