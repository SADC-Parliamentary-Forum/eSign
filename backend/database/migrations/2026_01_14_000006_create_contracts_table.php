<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents'); // The signed contract file
            $table->string('reference_number')->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->string('counterparty_name');
            $table->string('counterparty_email')->nullable();
            $table->text('renewal_terms')->nullable();
            $table->string('status')->default('active'); // active, expired, terminated, drafted
            $table->timestamps();
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('file_path'); // Path to .docx template
            $table->jsonb('placeholders')->nullable(); // ['{{name}}', '{{date}}']
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('contracts');
    }
};
