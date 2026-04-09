<?php

namespace App\Services\ProjectUserFields;

use App\Models\Project;
use App\Models\ProjectUserField;
use Illuminate\Support\Collection;

class BuildProjectUserFieldDefinitions
{
    /**
     * Get the active field definitions for a project.
     *
     * @return Collection<int, ProjectUserField>
     */
    public function active(Project $project): Collection
    {
        return ProjectUserField::query()
            ->whereBelongsTo($project)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Get the API-visible field definitions for a project.
     *
     * @return Collection<int, ProjectUserField>
     */
    public function forApi(Project $project): Collection
    {
        return ProjectUserField::query()
            ->whereBelongsTo($project)
            ->active()
            ->visibleInApi()
            ->ordered()
            ->get();
    }

    /**
     * Get the admin-form-visible field definitions for a project.
     *
     * @return Collection<int, ProjectUserField>
     */
    public function forAdminForm(Project $project): Collection
    {
        return ProjectUserField::query()
            ->whereBelongsTo($project)
            ->active()
            ->visibleInAdminForm()
            ->ordered()
            ->get();
    }

    /**
     * Resolve the allowed keys for a project's active definitions.
     *
     * @return list<string>
     */
    public function allowedKeys(Project $project): array
    {
        return $this->active($project)
            ->pluck('key')
            ->values()
            ->all();
    }
}
