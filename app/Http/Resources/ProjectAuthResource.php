<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expiresAt = $this->resource['expires_at'] ?? null;

        return [
            'token_type' => 'Bearer',
            'access_token' => $this->resource['plain_text_token'],
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_in_seconds' => $expiresAt === null
                ? null
                : max(0, $expiresAt->getTimestamp() - now()->getTimestamp()),
            'user' => ProjectUserResource::make($this->resource['project_user']),
        ];
    }
}
