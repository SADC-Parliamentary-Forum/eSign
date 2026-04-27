<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedTinyInteger('processing_progress')->default(0)->after('status');
            $table->string('processing_stage', 50)->nullable()->after('processing_progress');
            $table->text('processing_error')->nullable()->after('processing_stage');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['processing_progress', 'processing_stage', 'processing_error']);
        });
    }
};
