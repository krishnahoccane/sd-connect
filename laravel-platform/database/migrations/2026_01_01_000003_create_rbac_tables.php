<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('scope', ['platform', 'organisation', 'application'])->index();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['scope', 'organisation_id', 'slug'], 'uq_role_scope_org_slug');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('scope', ['platform', 'organisation', 'application'])->index();
            $table->string('slug', 120)->unique();
            $table->string('name', 150);
            $table->string('module', 64)->index();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->unsignedBigInteger('application_id')->nullable()->index();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'organisation_id', 'application_id'], 'uq_user_role_context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
