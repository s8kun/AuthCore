<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_auth_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete()->unique();
            $table->string('auth_mode')->default('standard');
            $table->unsignedInteger('access_token_ttl_minutes')->default(60);
            $table->unsignedInteger('refresh_token_ttl_days')->default(30);
            $table->boolean('otp_enabled')->default(true);
            $table->unsignedTinyInteger('otp_length')->default(6);
            $table->unsignedInteger('otp_ttl_minutes')->default(10);
            $table->unsignedTinyInteger('otp_max_attempts')->default(5);
            $table->unsignedInteger('otp_resend_cooldown_seconds')->default(60);
            $table->unsignedInteger('otp_daily_limit_per_email')->default(10);
            $table->boolean('forgot_password_enabled')->default(true);
            $table->unsignedInteger('reset_password_ttl_minutes')->default(60);
            $table->unsignedInteger('forgot_password_requests_per_hour')->default(5);
            $table->boolean('email_verification_enabled')->default(false);
            $table->boolean('ghost_accounts_enabled')->default(true);
            $table->unsignedInteger('max_ghost_accounts_per_email')->nullable();
            $table->boolean('magic_link_enabled')->default(false);
            $table->string('login_identifier_mode')->default('email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_auth_settings');
    }
};
