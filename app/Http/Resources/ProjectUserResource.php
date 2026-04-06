<?php

namespace App\Http\Resources;

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
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'is_ghost' => (bool) $this->is_ghost,
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'invited_at' => $this->invited_at?->toIso8601String(),
            'ghost_source' => $this->ghost_source,
            'must_set_password' => (bool) $this->must_set_password,
            'must_verify_email' => (bool) $this->must_verify_email,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
