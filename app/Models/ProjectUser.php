<?php

namespace App\Models;

use Database\Factories\ProjectUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'project_id',
    'email',
    'password',
    'first_name',
    'last_name',
    'phone',
    'role',
    'email_verified_at',
    'last_login_at',
    'is_active',
    'is_ghost',
    'claimed_at',
    'invited_at',
    'ghost_source',
    'must_set_password',
    'must_verify_email',
])]
#[Hidden(['password'])]
class ProjectUser extends Authenticatable
{
    /** @use HasFactory<ProjectUserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * Get the project that owns the project user.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the OTP records for the user.
     */
    public function otps(): HasMany
    {
        return $this->hasMany(ProjectOtp::class);
    }

    /**
     * Get the refresh tokens for the user.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * Get the auth event logs for the user.
     */
    public function authEventLogs(): HasMany
    {
        return $this->hasMany(AuthEventLog::class);
    }

    /**
     * Determine whether the account is pending email verification.
     */
    public function isPendingEmailVerification(): bool
    {
        return $this->must_verify_email && $this->email_verified_at === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'claimed_at' => 'datetime',
            'invited_at' => 'datetime',
            'is_active' => 'boolean',
            'is_ghost' => 'boolean',
            'must_set_password' => 'boolean',
            'must_verify_email' => 'boolean',
        ];
    }
}
