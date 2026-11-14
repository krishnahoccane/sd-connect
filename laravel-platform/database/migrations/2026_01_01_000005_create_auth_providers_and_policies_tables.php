<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 64)->unique(); // email_password, email_otp, firebase_sms, whatsapp_otp, google, microsoft
            $table->string('name', 120);
            $table->enum('category', ['password', 'otp', 'federated'])->index();
            $table->boolean('is_globally_enabled')->default(false)->index();
            $table->boolean('supports_org_credentials')->default(false);
            $table->boolean('requires_external_health')->default(false);
            $table->enum('health_status', ['healthy', 'degraded', 'unconfigured', 'failing'])->default('unconfigured');
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('default_policy')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('scope', ['global', 'organisation'])->index();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->string('provider_code', 64);
            $table->boolean('is_active')->default(true);
            $table->boolean('use_platform_default')->default(true);
            $table->text('encrypted_credentials')->nullable(); // Laravel Crypt::encryptString JSON payload
            $table->json('public_settings')->nullable(); // Non-secret settings e.g. message templates, tenant ID mode
            $table->enum('health_status', ['healthy', 'unconfigured', 'failing'])->default('unconfigured');
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'organisation_id', 'provider_code'], 'uq_provider_cfg_scope_org');
        });

        Schema::create('organisation_auth_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('provider_code', 64);
            $table->boolean('is_enabled')->default(false);
            $table->boolean('require_as_mfa_second_stage')->default(false);
            $table->unsignedTinyInteger('priority')->default(10);
            $table->timestamps();

            $table->unique(['organisation_id', 'provider_code']);
        });

        Schema::create('email_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('scope', ['global', 'organisation'])->index();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->enum('driver', ['platform_google_workspace', 'org_google_workspace', 'org_smtp'])->default('platform_google_workspace');
            $table->string('sender_name', 160);
            $table->string('sender_email', 255);
            $table->text('encrypted_transport_secrets')->nullable(); // SMTP password or Google OAuth refresh token
            $table->json('smtp_settings')->nullable(); // host, port, encryption, username
            $table->text('otp_subject_template')->nullable();
            $table->text('otp_body_template')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_configurations');
        Schema::dropIfExists('organisation_auth_methods');
        Schema::dropIfExists('provider_configurations');
        Schema::dropIfExists('auth_providers');
    }
};
