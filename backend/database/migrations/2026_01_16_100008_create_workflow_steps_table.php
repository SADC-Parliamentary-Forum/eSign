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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained('workflows')->onDelete('cascade');
            $table->string('role')->comment('Role name for this step');
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('signing_order')->default(1);
            $table->enum('status', ['PENDING', 'SIGNED', 'DECLINED'])->default('PENDING');
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'signing_order']);
            $table->index(['assigned_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
