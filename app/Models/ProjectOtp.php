<?php

namespace App\Models;

use App\Enums\ProjectOtpPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'project_user_id',
    'email',
    'purpose',
    'code_hash',
    'expires_at',
    'attempts',
    'max_attempts',
    'resend_count',
    'last_sent_at',
    'consumed_at',
    'meta',
])]
class ProjectOtp extends Model
{
    use HasUuids;

    /**
     * Get the project that owns the OTP.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user related to the OTP.
     */
    public function projectUser(): BelongsTo
    {
        return $this->belongsTo(ProjectUser::class);
    }

    /**
     * Determine whether the OTP can still be used.
     */
    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->attempts < $this->max_attempts;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => ProjectOtpPurpose::class,
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'consumed_at' => 'datetime',
            'meta' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'resend_count' => 'integer',
        ];
    }
}
