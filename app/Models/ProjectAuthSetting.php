<?php

namespace App\Models;

use App\Enums\ProjectAuthMode;
use App\Enums\ProjectLoginIdentifierMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'auth_mode',
    'access_token_ttl_minutes',
    'refresh_token_ttl_days',
    'otp_enabled',
    'otp_length',
    'otp_ttl_minutes',
    'otp_max_attempts',
    'otp_resend_cooldown_seconds',
    'otp_daily_limit_per_email',
    'forgot_password_enabled',
    'reset_password_ttl_minutes',
    'forgot_password_requests_per_hour',
    'email_verification_enabled',
    'ghost_accounts_enabled',
    'max_ghost_accounts_per_email',
    'magic_link_enabled',
    'login_identifier_mode',
])]
class ProjectAuthSetting extends Model
{
    use HasUuids;

    /**
     * Get the project that owns the settings.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the default settings payload.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'auth_mode' => ProjectAuthMode::Standard,
            'access_token_ttl_minutes' => 60,
            'refresh_token_ttl_days' => 30,
            'otp_enabled' => true,
            'otp_length' => 6,
            'otp_ttl_minutes' => 10,
            'otp_max_attempts' => 5,
            'otp_resend_cooldown_seconds' => 60,
            'otp_daily_limit_per_email' => 10,
            'forgot_password_enabled' => true,
            'reset_password_ttl_minutes' => 60,
            'forgot_password_requests_per_hour' => 5,
            'email_verification_enabled' => false,
            'ghost_accounts_enabled' => false,
            'max_ghost_accounts_per_email' => null,
            'magic_link_enabled' => false,
            'login_identifier_mode' => ProjectLoginIdentifierMode::Email,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auth_mode' => ProjectAuthMode::class,
            'login_identifier_mode' => ProjectLoginIdentifierMode::class,
            'access_token_ttl_minutes' => 'integer',
            'refresh_token_ttl_days' => 'integer',
            'otp_enabled' => 'boolean',
            'otp_length' => 'integer',
            'otp_ttl_minutes' => 'integer',
            'otp_max_attempts' => 'integer',
            'otp_resend_cooldown_seconds' => 'integer',
            'otp_daily_limit_per_email' => 'integer',
            'forgot_password_enabled' => 'boolean',
            'reset_password_ttl_minutes' => 'integer',
            'forgot_password_requests_per_hour' => 'integer',
            'email_verification_enabled' => 'boolean',
            'ghost_accounts_enabled' => 'boolean',
            'max_ghost_accounts_per_email' => 'integer',
            'magic_link_enabled' => 'boolean',
        ];
    }
}
