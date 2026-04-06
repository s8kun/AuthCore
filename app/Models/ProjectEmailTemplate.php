<?php

namespace App\Models;

use App\Enums\ProjectEmailTemplateType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'type', 'subject', 'html_body', 'text_body', 'is_enabled'])]
class ProjectEmailTemplate extends Model
{
    use HasUuids;

    /**
     * Get the project that owns the template.
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
            'type' => ProjectEmailTemplateType::class,
            'is_enabled' => 'boolean',
        ];
    }
}
