<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Support\ProjectEmailTemplateDefaults;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['owner_id', 'name', 'slug', 'api_key', 'api_secret', 'status', 'rate_limit'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (blank($project->slug)) {
                $project->slug = static::generateSlug($project->name);
            }

            if (blank($project->api_key)) {
                $project->api_key = static::generateApiKey();
            }

            if (blank($project->api_secret)) {
                $project->api_secret = static::generateApiSecret();
            }

            if (blank($project->status)) {
                $project->status = ProjectStatus::Active->value;
            }
        });

        static::created(function (Project $project): void {
            $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());
            $project->mailSettings()->firstOrCreate([], ProjectMailSetting::defaults());

            foreach (ProjectEmailTemplateDefaults::for($project) as $template) {
                $project->emailTemplates()->firstOrCreate(
                    ['type' => $template['type']->value],
                    [
                        'subject' => $template['subject'],
                        'html_body' => $template['html_body'],
                        'text_body' => $template['text_body'],
                        'is_enabled' => $template['is_enabled'],
                    ],
                );
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
     * Get the auth settings for the project.
     */
    public function authSettings(): HasOne
    {
        return $this->hasOne(ProjectAuthSetting::class);
    }

    /**
     * Get the mail settings for the project.
     */
    public function mailSettings(): HasOne
    {
        return $this->hasOne(ProjectMailSetting::class);
    }

    /**
     * Get the email templates for the project.
     */
    public function emailTemplates(): HasMany
    {
        return $this->hasMany(ProjectEmailTemplate::class);
    }

    /**
     * Get the OTP records for the project.
     */
    public function otps(): HasMany
    {
        return $this->hasMany(ProjectOtp::class);
    }

    /**
     * Get the project password resets for the project.
     */
    public function passwordResets(): HasMany
    {
        return $this->hasMany(ProjectPasswordReset::class);
    }

    /**
     * Get the refresh tokens for the project.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * Get the auth event logs for the project.
     */
    public function authEventLogs(): HasMany
    {
        return $this->hasMany(AuthEventLog::class);
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
            'status' => ProjectStatus::class,
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

    /**
     * Generate a unique API secret for a project.
     */
    public static function generateApiSecret(): string
    {
        do {
            $apiSecret = Str::random(64);
        } while (static::query()->where('api_secret', $apiSecret)->exists());

        return $apiSecret;
    }

    /**
     * Generate a unique slug for a project.
     */
    public static function generateSlug(?string $name): string
    {
        $baseSlug = Str::slug((string) $name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : Str::lower(Str::random(12));
        $slug = $baseSlug;
        $counter = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
