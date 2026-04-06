<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['owner_id', 'name', 'api_key', 'rate_limit'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (blank($project->api_key)) {
                $project->api_key = static::generateApiKey();
            }
        });
    }

    /**
     * Get the platform owner that owns the project.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the project users for the project.
     */
    public function projectUsers(): HasMany
    {
        return $this->hasMany(ProjectUser::class);
    }

    /**
     * Get the API request logs for the project.
     */
    public function apiRequestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_limit' => 'integer',
        ];
    }

    /**
     * Generate a unique API key for a project.
     */
    public static function generateApiKey(): string
    {
        do {
            $apiKey = Str::lower(Str::random(40));
        } while (static::query()->where('api_key', $apiKey)->exists());

        return $apiKey;
    }
}
