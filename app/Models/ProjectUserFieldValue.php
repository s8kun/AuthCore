<?php

namespace App\Models;

use App\Enums\ProjectUserFieldType;
use Database\Factories\ProjectUserFieldValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'project_user_id',
    'project_user_field_id',
    'value_string',
    'value_text',
    'value_integer',
    'value_decimal',
    'value_boolean',
    'value_date',
    'value_datetime',
    'value_json',
    'value_hash',
    'unique_scope_key',
])]
class ProjectUserFieldValue extends Model
{
    /** @use HasFactory<ProjectUserFieldValueFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the project that owns the value row.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the project user that owns the value row.
     */
    public function projectUser(): BelongsTo
    {
        return $this->belongsTo(ProjectUser::class);
    }

    /**
     * Get the field definition for the value row.
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ProjectUserField::class, 'project_user_field_id');
    }

    /**
     * Resolve the stored value into an API-safe representation.
     */
    public function typedValue(): mixed
    {
        $type = $this->field?->type;

        if (! $type instanceof ProjectUserFieldType) {
            return $this->value_string
                ?? $this->value_text
                ?? $this->value_integer
                ?? $this->value_decimal
                ?? $this->value_boolean
                ?? $this->value_date?->toDateString()
                ?? $this->value_datetime?->toIso8601String()
                ?? $this->value_json;
        }

        return match ($type) {
            ProjectUserFieldType::StringType,
            ProjectUserFieldType::Enum,
            ProjectUserFieldType::Email,
            ProjectUserFieldType::Url,
            ProjectUserFieldType::Phone,
            ProjectUserFieldType::Uuid => $this->value_string,
            ProjectUserFieldType::Text => $this->value_text,
            ProjectUserFieldType::Integer => $this->value_integer,
            ProjectUserFieldType::Decimal => $this->value_decimal,
            ProjectUserFieldType::Boolean => $this->value_boolean,
            ProjectUserFieldType::Date => $this->value_date?->toDateString(),
            ProjectUserFieldType::DateTime => $this->value_datetime?->toIso8601String(),
            ProjectUserFieldType::Json => $this->value_json,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_integer' => 'integer',
            'value_boolean' => 'boolean',
            'value_date' => 'date',
            'value_datetime' => 'datetime',
            'value_json' => 'array',
        ];
    }
}
