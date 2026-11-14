<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('twsd_subdomain', 120)->nullable()->unique()->after('slug');
            $table->string('custom_domain', 255)->nullable()->unique()->after('twsd_subdomain');
            $table->boolean('custom_domain_verified')->default(false)->after('custom_domain');
            $table->json('access_policy')->nullable()->after('refresh_token_ttl_days');
            $table->json('branding_overrides')->nullable()->after('access_policy');
            $table->timestamp('archived_at')->nullable()->after('status');
        });

        Schema::table('user_identities', function (Blueprint $table) {
            $table->foreign('organisation_id', 'fk_user_identities_org')
                ->references('id')
                ->on('organisations')
                ->cascadeOnDelete();
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->foreign('application_id', 'fk_user_roles_app')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();
        });

        Schema::table('organisation_branding', function (Blueprint $table) {
            $table->foreign('published_version_id', 'fk_org_branding_pub')
                ->references('id')
                ->on('branding_versions')
                ->nullOnDelete();
            $table->foreign('draft_version_id', 'fk_org_branding_draft')
                ->references('id')
                ->on('branding_versions')
                ->nullOnDelete();
        });

        Schema::create('oauth_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('passport_client_id', 100)->index();
            $table->string('redirect_uri', 1024);
            $table->string('state', 512)->nullable();
            $table->string('nonce', 512)->nullable();
            $table->string('code_challenge', 255)->nullable();
            $table->string('code_challenge_method', 16)->default('S256');
            $table->json('requested_scopes');
            $table->foreignId('authenticated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('completed_stages')->nullable();
            $table->json('amr')->nullable();
            $table->string('authorization_code_hash', 128)->nullable()->unique();
            $table->enum('status', ['pending', 'authenticated', 'consented', 'code_issued', 'exchanged', 'expired', 'revoked'])->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('exchanged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_transactions');

        Schema::table('organisation_branding', function (Blueprint $table) {
            $table->dropForeign('fk_org_branding_pub');
            $table->dropForeign('fk_org_branding_draft');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropForeign('fk_user_roles_app');
        });

        Schema::table('user_identities', function (Blueprint $table) {
            $table->dropForeign('fk_user_identities_org');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'twsd_subdomain',
                'custom_domain',
                'custom_domain_verified',
                'access_policy',
                'branding_overrides',
                'archived_at',
            ]);
        });
    }
};
