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
        $response = [];

        if (array_key_exists('plain_text_token', $this->resource)) {
            $expiresAt = $this->resource['expires_at'] ?? null;

            $response = [
                'token_type' => 'Bearer',
                'access_token' => $this->resource['plain_text_token'],
                'refresh_token' => $this->resource['refresh_token'] ?? null,
                'expires_at' => $expiresAt?->toIso8601String(),
                'expires_in_seconds' => $expiresAt === null
                    ? null
                    : max(0, $expiresAt->getTimestamp() - now()->getTimestamp()),
                'refresh_token_expires_at' => ($this->resource['refresh_token_expires_at'] ?? null)?->toIso8601String(),
                'refresh_token_expires_in_seconds' => ($this->resource['refresh_token_expires_at'] ?? null) === null
                    ? null
                    : max(0, $this->resource['refresh_token_expires_at']->getTimestamp() - now()->getTimestamp()),
            ];
        }

        if (array_key_exists('message', $this->resource)) {
            $response['message'] = $this->resource['message'];
        }

        if (array_key_exists('verification_required', $this->resource)) {
            $response['verification_required'] = (bool) $this->resource['verification_required'];
        }

        if (array_key_exists('verification_purpose', $this->resource)) {
            $response['verification_purpose'] = $this->resource['verification_purpose'];
        }

        $response['user'] = ProjectUserResource::make($this->resource['project_user']);

        return $response;
    }
}
