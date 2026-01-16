<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event'); // e.g., 'login', 'document_viewed', 'contract_created'
            $table->string('resource_type')->nullable(); // 'document', 'user'
            $table->uuid('resource_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->jsonb('details')->nullable(); // Old values, new values, etc.
            $table->timestamp('created_at')->useCurrent();

            // No updated_at or deleted_at - Audit logs are immutable
            $table->index(['resource_type', 'resource_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
