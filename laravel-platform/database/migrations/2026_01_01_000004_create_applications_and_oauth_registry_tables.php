<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 100);
            $table->string('app_url', 512);
            $table->text('description')->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->enum('application_type', ['web_confidential', 'spa_public', 'mobile_public', 'service'])->default('web_confidential');
            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            $table->enum('jit_provisioning_override', ['inherit', 'automatic', 'invitation_only', 'pre_approved'])->default('inherit');
            $table->boolean('require_pkce')->default(true);
            $table->json('allowed_scopes');
            $table->json('allowed_grant_types');
            $table->unsignedInteger('access_token_ttl_minutes')->default(15);
            $table->unsignedInteger('refresh_token_ttl_days')->default(30);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'slug']);
        });

        // Maps tenant applications to Laravel Passport's native oauth_clients without altering Passport tables
        Schema::create('oauth_client_registry', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('passport_client_id', 100)->unique();
            $table->enum('client_type', ['confidential', 'public'])->default('confidential');
            $table->boolean('pkce_required')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'application_id']);
        });

        Schema::create('oauth_redirect_uris', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->enum('uri_type', ['redirect', 'post_logout'])->default('redirect');
            $table->string('uri', 1024);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['application_id', 'uri_type', 'is_active']);
        });

        Schema::create('application_auth_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('provider_code', 64);
            $table->boolean('is_enabled')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->json('mandatory_stages')->nullable(); // e.g. ["email_password", "email_otp"]
            $table->timestamps();

            $table->unique(['application_id', 'provider_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_auth_methods');
        Schema::dropIfExists('oauth_redirect_uris');
        Schema::dropIfExists('oauth_client_registry');
        Schema::dropIfExists('applications');
    }
};
