<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('otp_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32); // email_otp, whatsapp_otp
            $table->string('recipient_hash', 128)->index();
            $table->string('recipient_masked', 120);
            $table->string('otp_hash', 255);
            $table->string('purpose', 64)->default('signin'); // signin, identity_link, password_reset
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->enum('status', ['pending', 'verified', 'expired', 'exhausted', 'revoked'])->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('otp_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('otp_transaction_id')->constrained('otp_transactions')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('provider_code', 64);
            $table->enum('delivery_status', ['queued', 'sent', 'failed'])->index();
            $table->string('provider_message_id', 191)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('sanitized_response')->nullable(); // Never contains auth keys or plaintext OTP
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('otp_transaction_id')->constrained('otp_transactions')->cascadeOnDelete();
            $table->boolean('succeeded')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('attempted_at');
        });

        Schema::create('sso_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_token_hash', 128)->unique();
            $table->json('amr')->nullable(); // Authentication Methods References e.g. ["pwd","otp"]
            $table->timestamp('authenticated_at');
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sso_session_id')->constrained('sso_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('auth_provider', 64);
            $table->timestamp('started_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'user_id']);
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('identifier_masked', 160)->nullable();
            $table->string('provider_code', 64);
            $table->enum('outcome', ['success', 'failed', 'blocked', 'mfa_required'])->index();
            $table->string('failure_reason', 160)->nullable();
            $table->string('ip_address', 45)->index();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('attempted_at')->index();
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100)->index(); // refresh_token_reuse, cross_tenant_probe, lockout, key_rotation
            $table->enum('severity', ['info', 'warning', 'high', 'critical'])->default('info')->index();
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 100)->nullable();
            $table->string('action', 120)->index();
            $table->string('target_type', 120)->nullable();
            $table->string('target_uuid', 64)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('token_hash', 128)->unique();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->json('application_ids')->nullable();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'accepted', 'expired', 'revoked'])->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'email', 'status']);
        });

        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->json('granted_scopes');
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'application_id']);
        });

        Schema::create('provider_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('provider_code', 64);
            $table->string('external_event_id', 191)->nullable();
            $table->string('event_type', 120);
            $table->json('payload');
            $table->timestamp('received_at');
        });

        Schema::create('signing_key_metadata', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kid', 64)->unique();
            $table->string('algorithm', 16)->default('RS256');
            $table->text('public_key_pem');
            $table->text('encrypted_private_key_pem');
            $table->enum('status', ['active', 'retiring', 'retired'])->default('active')->index();
            $table->timestamp('activated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('configuration_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('scope', ['global', 'organisation', 'application'])->index();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->cascadeOnDelete();
            $table->string('config_key', 120);
            $table->json('previous_value')->nullable();
            $table->json('new_value')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_history');
        Schema::dropIfExists('signing_key_metadata');
        Schema::dropIfExists('provider_webhook_events');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('sso_sessions');
        Schema::dropIfExists('otp_attempts');
        Schema::dropIfExists('otp_delivery_logs');
        Schema::dropIfExists('otp_transactions');
    }
};
