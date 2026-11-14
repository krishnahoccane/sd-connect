<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('slug', 100)->unique();
            $table->enum('status', ['active', 'suspended', 'archived'])->default('active')->index();
            $table->enum('jit_provisioning_mode', ['automatic', 'invitation_only', 'pre_approved'])->default('invitation_only');
            $table->boolean('mfa_required')->default(false);
            $table->unsignedInteger('session_lifetime_minutes')->default(480);
            $table->json('password_policy')->nullable();
            $table->json('otp_policy')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organisation_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('domain', 255);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('verification_token', 128)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'domain']);
            $table->index('domain');
        });

        Schema::create('organisation_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('membership_status', ['active', 'suspended', 'invited', 'revoked'])->default('active')->index();
            $table->string('employee_reference', 100)->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'user_id']);
            $table->index(['user_id', 'membership_status']);
        });

        Schema::create('organisation_branding', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->unique()->constrained('organisations')->cascadeOnDelete();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedBigInteger('draft_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('branding_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('display_name', 160);
            $table->string('logo_path', 512)->nullable();
            $table->string('background_image_path', 512)->nullable();
            $table->string('primary_color', 16)->default('#0F172A');
            $table->string('secondary_color', 16)->default('#2563EB');
            $table->string('footer_text', 500)->nullable();
            $table->string('support_email', 255)->nullable();
            $table->string('terms_url', 512)->nullable();
            $table->string('privacy_url', 512)->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_versions');
        Schema::dropIfExists('organisation_branding');
        Schema::dropIfExists('organisation_users');
        Schema::dropIfExists('organisation_domains');
        Schema::dropIfExists('organisations');
    }
};
