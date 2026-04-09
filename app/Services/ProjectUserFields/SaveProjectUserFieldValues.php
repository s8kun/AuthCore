<?php

namespace App\Services\ProjectUserFields;

use App\Models\ProjectUser;
use App\Models\ProjectUserField;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SaveProjectUserFieldValues
{
    public function __construct(
        private readonly BuildProjectUserFieldDefinitions $fieldDefinitions,
        private readonly NormalizeProjectUserFieldValue $normalizeProjectUserFieldValue,
    ) {}

    /**
     * Persist custom field values for a project user.
     *
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, ProjectUserField>|null  $definitions
     */
    public function save(
        ProjectUser $projectUser,
        array $payload,
        ?Collection $definitions = null,
        bool $applyDefaults = false,
    ): void {
        $definitions ??= $this->fieldDefinitions->active($projectUser->project()->firstOrFail());

        foreach ($definitions as $field) {
            $hasSubmittedValue = array_key_exists($field->key, $payload);

            if (! $hasSubmittedValue && ! $applyDefaults) {
                continue;
            }

            $rawValue = $hasSubmittedValue ? $payload[$field->key] : $field->default_value;
            $normalizedValue = $this->normalizeProjectUserFieldValue->normalize($field, $rawValue);

            if ($normalizedValue === null) {
                $projectUser->customFieldValues()
                    ->where('project_user_field_id', $field->id)
                    ->delete();

                continue;
            }

            $attributes = [
                'project_id' => $projectUser->project_id,
                'value_hash' => $field->is_unique ? $this->normalizeProjectUserFieldValue->hash($field, $normalizedValue) : null,
                'unique_scope_key' => $field->is_unique ? $field->id : null,
                ...$this->normalizeProjectUserFieldValue->toStorageAttributes($field, $normalizedValue),
            ];

            try {
                $projectUser->customFieldValues()->updateOrCreate(
                    ['project_user_field_id' => $field->id],
                    $attributes,
                );
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    "custom_fields.{$field->key}" => ["The {$field->label} has already been taken."],
                ]);
            }
        }
    }
}
