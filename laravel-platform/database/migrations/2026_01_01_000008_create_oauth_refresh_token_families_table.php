<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('oauth_refresh_token_families', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('family_uuid')->index();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('passport_client_id', 100)->index();
            $table->string('passport_token_id', 100)->nullable()->index();
            $table->string('token_hash', 128)->unique();
            $table->json('scopes');
            $table->json('amr')->nullable();
            $table->enum('status', ['active', 'rotated', 'revoked', 'compromised'])->default('active')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_token_families');
    }
};
