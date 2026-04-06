<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'project_user_id',
    'email',
    'token_hash',
    'expires_at',
    'used_at',
    'requested_ip',
])]
class ProjectPasswordReset extends Model
{
    use HasUuids;

    /**
     * Get the project that owns the reset token.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user that owns the reset token.
     */
    public function projectUser(): BelongsTo
    {
        return $this->belongsTo(ProjectUser::class);
    }

    /**
     * Determine whether the token can still be used.
     */
    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
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
            'used_at' => 'datetime',
        ];
    }
}
