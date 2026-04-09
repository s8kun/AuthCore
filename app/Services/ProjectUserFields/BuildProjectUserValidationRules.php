<?php

namespace App\Services\ProjectUserFields;

use App\Enums\ProjectUserFieldType;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\ProjectUserField;
use App\Models\ProjectUserFieldValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class BuildProjectUserValidationRules
{
    public function __construct(
        private readonly BuildProjectUserFieldDefinitions $fieldDefinitions,
        private readonly NormalizeProjectUserFieldValue $normalizeProjectUserFieldValue,
    ) {}

    /**
     * Build validation rules for a project's active custom fields.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function for(Project $project, ?ProjectUser $ignoreProjectUser = null): array
    {
        $definitions = $this->fieldDefinitions->active($project);

        if ($definitions->isEmpty()) {
            return [
                'custom_fields' => ['nullable', 'array'],
            ];
        }

        $rules = [
            'custom_fields' => ['nullable', 'array'],
        ];

        foreach ($definitions as $definition) {
            $rules["custom_fields.{$definition->key}"] = $this->forField($definition, $ignoreProjectUser);
        }

        return $rules;
    }

    /**
     * Build the validation rules for a single field definition.
     *
     * @return array<int, ValidationRule|string>
     */
    public function forField(ProjectUserField $field, ?ProjectUser $ignoreProjectUser = null): array
    {
        $constraints = $field->validation_rules ?? [];
        $rules = [];

        $rules[] = $field->is_required && $field->default_value === null ? 'required' : 'nullable';

        foreach ($this->typeRules($field, $constraints) as $rule) {
            $rules[] = $rule;
        }

        if ($field->is_unique && $field->supportsUniqueConstraint()) {
            $rules[] = $this->uniqueRule($field, $ignoreProjectUser);
        }

        return $rules;
    }

    /**
     * Build the base type-driven validation rules for a definition.
     *
     * @param  array<string, mixed>  $constraints
     * @return array<int, ValidationRule|string>
     */
    private function typeRules(ProjectUserField $field, array $constraints): array
    {
        $rules = match ($field->type) {
            ProjectUserFieldType::StringType,
            ProjectUserFieldType::Text,
            ProjectUserFieldType::Phone => ['string'],
            ProjectUserFieldType::Integer => ['integer'],
            ProjectUserFieldType::Decimal => ['numeric'],
            ProjectUserFieldType::Boolean => ['boolean'],
            ProjectUserFieldType::Date,
            ProjectUserFieldType::DateTime => ['date'],
            ProjectUserFieldType::Enum => ['string', Rule::in($field->options ?? Arr::get($constraints, 'allowed_options', []))],
            ProjectUserFieldType::Email => ['string', 'email'],
            ProjectUserFieldType::Url => ['string', 'url'],
            ProjectUserFieldType::Uuid => ['string', 'uuid'],
            ProjectUserFieldType::Json => ['array'],
        };

        if ($field->type->isStringLike()) {
            if (($minLength = Arr::get($constraints, 'min_length')) !== null) {
                $rules[] = 'min:'.$minLength;
            }

            if (($maxLength = Arr::get($constraints, 'max_length')) !== null) {
                $rules[] = 'max:'.$maxLength;
            }
        }

        if (in_array($field->type, [ProjectUserFieldType::Integer, ProjectUserFieldType::Decimal], true)) {
            if (($minimum = Arr::get($constraints, 'min')) !== null) {
                $rules[] = 'min:'.$minimum;
            }

            if (($maximum = Arr::get($constraints, 'max')) !== null) {
                $rules[] = 'max:'.$maximum;
            }
        }

        if ($field->type === ProjectUserFieldType::Decimal && ($scale = Arr::get($constraints, 'scale')) !== null) {
            $rules[] = 'decimal:0,'.$scale;
        }

        if (in_array($field->type, [ProjectUserFieldType::Date, ProjectUserFieldType::DateTime], true)) {
            if (($after = Arr::get($constraints, 'after')) !== null) {
                $rules[] = 'after:'.$after;
            }

            if (($before = Arr::get($constraints, 'before')) !== null) {
                $rules[] = 'before:'.$before;
            }
        }

        if (($regex = Arr::get($constraints, 'regex')) !== null) {
            $rules[] = 'regex:'.$regex;
        }

        return $rules;
    }

    /**
     * Build a project-scoped uniqueness rule for a definition.
     */
    private function uniqueRule(ProjectUserField $field, ?ProjectUser $ignoreProjectUser): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field, $ignoreProjectUser): void {
            try {
                $normalized = $this->normalizeProjectUserFieldValue->normalize($field, $value);
            } catch (\Throwable) {
                return;
            }

            if ($normalized === null) {
                return;
            }

            $query = ProjectUserFieldValue::query()
                ->where('project_user_field_id', $field->id)
                ->where('unique_scope_key', $field->id)
                ->where('value_hash', $this->normalizeProjectUserFieldValue->hash($field, $normalized));

            if ($ignoreProjectUser instanceof ProjectUser) {
                $query->where('project_user_id', '!=', $ignoreProjectUser->id);
            }

            if ($query->exists()) {
                $fail("The {$field->label} has already been taken.");
            }
        };
    }
}
