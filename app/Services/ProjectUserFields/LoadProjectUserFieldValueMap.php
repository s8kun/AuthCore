<?php

namespace App\Services\ProjectUserFields;

use App\Models\ProjectUser;
use App\Models\ProjectUserField;
use App\Models\ProjectUserFieldValue;
use Illuminate\Support\Collection;

class LoadProjectUserFieldValueMap
{
    public function __construct(
        private readonly BuildProjectUserFieldDefinitions $fieldDefinitions,
    ) {}

    /**
     * Build an API-safe custom field map for a project user.
     *
     * @return array<string, mixed>
     */
    public function forApi(ProjectUser $projectUser): array
    {
        $project = $projectUser->project()->firstOrFail();

        return $this->buildMap($projectUser, $this->fieldDefinitions->forApi($project));
    }

    /**
     * Build an admin-form-safe custom field map for a project user.
     *
     * @return array<string, mixed>
     */
    public function forAdminForm(ProjectUser $projectUser): array
    {
        $project = $projectUser->project()->firstOrFail();

        return $this->buildMap($projectUser, $this->fieldDefinitions->forAdminForm($project));
    }

    /**
     * @param  Collection<int, ProjectUserField>  $definitions
     * @return array<string, mixed>
     */
    private function buildMap(ProjectUser $projectUser, Collection $definitions): array
    {
        if ($definitions->isEmpty()) {
            return [];
        }

        /** @var Collection<string, ProjectUserFieldValue> $values */
        $values = $projectUser->customFieldValues()
            ->whereIn('project_user_field_id', $definitions->modelKeys())
            ->with('field')
            ->get()
            ->keyBy('project_user_field_id');

        $payload = [];

        foreach ($definitions as $definition) {
            $value = $values->get($definition->id);

            if ($value instanceof ProjectUserFieldValue) {
                $payload[$definition->key] = $value->typedValue();

                continue;
            }

            if ($definition->default_value !== null) {
                $payload[$definition->key] = $definition->default_value;
            }
        }

        return $payload;
    }
}
