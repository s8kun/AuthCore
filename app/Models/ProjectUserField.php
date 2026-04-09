<?php

namespace App\Models;

use App\Enums\ProjectUserFieldType;
use Database\Factories\ProjectUserFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'key',
    'label',
    'type',
    'description',
    'placeholder',
    'default_value',
    'options',
    'validation_rules',
    'ui_settings',
    'is_required',
    'is_nullable',
    'is_unique',
    'is_searchable',
    'is_filterable',
    'is_sortable',
    'show_in_admin_form',
    'show_in_api',
    'show_in_table',
    'is_active',
    'sort_order',
])]
class ProjectUserField extends Model
{
    /** @use HasFactory<ProjectUserFieldFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public const RESERVED_KEYS = [
        'id',
        'project_id',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'email_verified_at',
        'last_login_at',
        'is_active',
        'is_ghost',
        'claimed_at',
        'invited_at',
        'ghost_source',
        'must_set_password',
        'must_verify_email',
        'created_at',
        'updated_at',
        'deleted_at',
        'custom_fields',
    ];

    /**
     * Get the project that owns the field.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the stored values for this field definition.
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProjectUserFieldValue::class);
    }

    /**
     * Scope the query to active definitions.
     *
     * @param  Builder<EloquentModel>  $query
     * @return Builder<EloquentModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope the query to API-visible definitions.
     *
     * @param  Builder<EloquentModel>  $query
     * @return Builder<EloquentModel>
     */
    public function scopeVisibleInApi(Builder $query): Builder
    {
        return $query->where('show_in_api', true);
    }

    /**
     * Scope the query to admin-form-visible definitions.
     *
     * @param  Builder<EloquentModel>  $query
     * @return Builder<EloquentModel>
     */
    public function scopeVisibleInAdminForm(Builder $query): Builder
    {
        return $query->where('show_in_admin_form', true);
    }

    /**
     * Scope the query to table-visible definitions.
     *
     * @param  Builder<EloquentModel>  $query
     * @return Builder<EloquentModel>
     */
    public function scopeVisibleInTable(Builder $query): Builder
    {
        return $query->where('show_in_table', true);
    }

    /**
     * Scope the query to ordered definitions.
     *
     * @param  Builder<EloquentModel>  $query
     * @return Builder<EloquentModel>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    /**
     * Resolve the storage column for the field's type.
     */
    public function storageColumn(): string
    {
        return $this->type->storageColumn();
    }

    /**
     * Determine whether this field can safely enforce uniqueness.
     */
    public function supportsUniqueConstraint(): bool
    {
        return $this->type->supportsUniqueConstraint();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectUserFieldType::class,
            'default_value' => 'json',
            'options' => 'array',
            'validation_rules' => 'array',
            'ui_settings' => 'array',
            'is_required' => 'boolean',
            'is_nullable' => 'boolean',
            'is_unique' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'is_sortable' => 'boolean',
            'show_in_admin_form' => 'boolean',
            'show_in_api' => 'boolean',
            'show_in_table' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
