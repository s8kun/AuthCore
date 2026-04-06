<?php

namespace App\Models;

use Database\Factories\ApiRequestLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'endpoint',
    'route_name',
    'method',
    'email',
    'ip_address',
    'user_agent',
    'status_code',
    'success',
    'metadata',
    'created_at',
])]
class ApiRequestLog extends Model
{
    /** @use HasFactory<ApiRequestLogFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * Get the project that owns the API request log.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'success' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
