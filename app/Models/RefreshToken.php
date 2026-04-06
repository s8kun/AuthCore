<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'project_user_id',
    'token_hash',
    'expires_at',
    'revoked_at',
    'last_used_at',
    'replaced_by_token_id',
    'user_agent',
    'ip_address',
])]
class RefreshToken extends Model
{
    use HasUuids;

    /**
     * Get the project that owns the refresh token.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user that owns the refresh token.
     */
    public function projectUser(): BelongsTo
    {
        return $this->belongsTo(ProjectUser::class);
    }

    /**
     * Get the replacement refresh token.
     */
    public function replacedByToken(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_token_id');
    }

    /**
     * Determine whether the refresh token can still be used.
     */
    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
