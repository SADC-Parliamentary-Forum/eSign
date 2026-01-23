<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Departments table
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // "Finance", "ICT", "Human Resources"
            $table->string('code')->unique(); // "FIN", "ICT", "HR"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Organizational Roles table
        Schema::create('organizational_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // "Secretary General", "Director Finance"
            $table->string('code')->unique(); // "SG", "DIR_FIN"
            $table->integer('level')->default(0); // Hierarchy: 1 = highest
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Template Roles - links templates to organizational roles with signing order
        // If table exists, add new column; otherwise create table
        if (Schema::hasTable('template_roles')) {
            Schema::table('template_roles', function (Blueprint $table) {
                if (!Schema::hasColumn('template_roles', 'organizational_role_id')) {
                    $table->uuid('organizational_role_id')->nullable()->after('template_id');
                    $table->foreign('organizational_role_id')->references('id')->on('organizational_roles')->onDelete('set null');
                }
                if (!Schema::hasColumn('template_roles', 'is_required')) {
                    $table->boolean('is_required')->default(true)->after('signing_order');
                }
            });
        } else {
            Schema::create('template_roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('template_id');
                $table->uuid('organizational_role_id')->nullable();
                $table->string('role')->nullable(); // Legacy
                $table->string('action')->nullable(); // Legacy
                $table->integer('signing_order')->default(1);
                $table->boolean('is_required')->default(true);
                $table->boolean('required')->default(true); // Legacy
                $table->timestamps();

                $table->foreign('template_id')->references('id')->on('templates')->onDelete('cascade');
                $table->foreign('organizational_role_id')->references('id')->on('organizational_roles')->onDelete('set null');
            });
        }

        // Update template_fields to link to organizational roles
        Schema::table('template_fields', function (Blueprint $table) {
            $table->uuid('organizational_role_id')->nullable()->after('signer_email');
            $table->enum('fill_mode', ['PRE_FILL', 'SIGNER_FILL'])->default('SIGNER_FILL')->after('required');

            $table->foreign('organizational_role_id')->references('id')->on('organizational_roles')->onDelete('set null');
        });

        // Add department to users (optional - for filtering)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->uuid('department_id')->nullable()->after('email');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
        });

        Schema::table('template_fields', function (Blueprint $table) {
            $table->dropForeign(['organizational_role_id']);
            $table->dropColumn(['organizational_role_id', 'fill_mode']);
        });

        Schema::dropIfExists('template_roles');
        Schema::dropIfExists('organizational_roles');
        Schema::dropIfExists('departments');
    }
};
