<?php

namespace App\Http\Resources;

use App\Models\ProjectUser;
use App\Services\ProjectUserFields\LoadProjectUserFieldValueMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProjectUser $projectUser */
        $projectUser = $this->resource;

        return [
            'id' => $projectUser->id,
            'project_id' => $projectUser->project_id,
            'email' => $projectUser->email,
            'custom_fields' => app(LoadProjectUserFieldValueMap::class)->forApi($projectUser),
            'email_verified_at' => $projectUser->email_verified_at?->toIso8601String(),
            'last_login_at' => $projectUser->last_login_at?->toIso8601String(),
            'is_active' => (bool) $projectUser->is_active,
            'is_ghost' => (bool) $projectUser->is_ghost,
            'claimed_at' => $projectUser->claimed_at?->toIso8601String(),
            'invited_at' => $projectUser->invited_at?->toIso8601String(),
            'ghost_source' => $projectUser->ghost_source,
            'must_set_password' => (bool) $projectUser->must_set_password,
            'must_verify_email' => (bool) $projectUser->must_verify_email,
            'created_at' => $projectUser->created_at?->toIso8601String(),
            'updated_at' => $projectUser->updated_at?->toIso8601String(),
        ];
    }
}
