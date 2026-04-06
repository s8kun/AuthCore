<?php

namespace App\Models;

use App\Enums\AuthEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'project_user_id',
    'email',
    'event_type',
    'endpoint',
    'method',
    'ip_address',
    'success',
    'metadata',
    'created_at',
])]
class AuthEventLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * Get the project that owns the log entry.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user related to the log entry.
     */
    public function projectUser(): BelongsTo
    {
        return $this->belongsTo(ProjectUser::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => AuthEventType::class,
            'success' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
