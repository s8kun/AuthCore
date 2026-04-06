<?php

namespace App\Models;

use App\Enums\ProjectMailMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'mail_mode',
    'from_name',
    'from_email',
    'reply_to_email',
    'support_email',
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password_encrypted',
    'smtp_encryption',
    'smtp_timeout',
    'is_verified',
    'last_tested_at',
])]
#[Hidden(['smtp_password_encrypted'])]
class ProjectMailSetting extends Model
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
            'mail_mode' => ProjectMailMode::Platform,
            'from_name' => (string) config('mail.from.name', config('app.name')),
            'from_email' => (string) config('mail.from.address', 'hello@example.com'),
            'reply_to_email' => null,
            'support_email' => (string) config('mail.from.address', 'support@example.com'),
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password_encrypted' => null,
            'smtp_encryption' => null,
            'smtp_timeout' => null,
            'is_verified' => false,
            'last_tested_at' => null,
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
            'mail_mode' => ProjectMailMode::class,
            'smtp_password_encrypted' => 'encrypted',
            'smtp_port' => 'integer',
            'smtp_timeout' => 'integer',
            'is_verified' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
