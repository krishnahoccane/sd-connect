<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('primary_email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('primary_phone', 32)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->enum('status', ['active', 'suspended', 'locked', 'pending'])->default('active')->index();
            $table->boolean('is_super_admin')->default(false)->index();
            $table->boolean('mfa_enabled')->default(false);
            $table->unsignedTinyInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 160);
            $table->string('given_name', 80)->nullable();
            $table->string('family_name', 80)->nullable();
            $table->string('avatar_url', 512)->nullable();
            $table->string('locale', 16)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider_code', 64); // google, microsoft, firebase_sms, whatsapp_otp, email_otp, email_password
            $table->string('provider_subject', 255); // Immutable provider subject (sub / oid / firebase_uid / verified identifier)
            $table->foreignId('organisation_id')->nullable()->index(); // Null = global identity; set if scoped to org credential issuer
            $table->string('email_at_provider')->nullable();
            $table->string('phone_at_provider', 32)->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('provider_claims')->nullable();
            $table->timestamps();

            $table->unique(['provider_code', 'provider_subject', 'organisation_id'], 'uq_provider_subject_org');
            $table->index(['user_id', 'provider_code']);
        });

        Schema::create('identity_linking_challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable();
            $table->string('provider_code', 64);
            $table->string('pending_provider_subject', 255);
            $table->string('proof_method', 64); // password_reauth, otp_reauth, signed_nonce
            $table->string('challenge_token_hash', 128)->unique();
            $table->enum('status', ['pending', 'verified', 'expired', 'rejected'])->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_linking_challenges');
        Schema::dropIfExists('user_identities');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};
