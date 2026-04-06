<?php

namespace App\Services\Auth;

use App\Enums\AuthEventType;
use App\Models\Project;
use App\Models\ProjectUser;
use Illuminate\Http\Request;

class AuthEventLogger
{
    /**
     * Persist an auth event log.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        Project $project,
        AuthEventType $eventType,
        bool $success,
        ?Request $request = null,
        ?ProjectUser $projectUser = null,
        ?string $email = null,
        array $metadata = [],
    ): void {
        $project->authEventLogs()->create([
            'project_user_id' => $projectUser?->getKey(),
            'email' => $email,
            'event_type' => $eventType,
            'endpoint' => $request?->path() !== null ? '/'.$request->path() : null,
            'method' => $request?->method(),
            'ip_address' => $request?->ip(),
            'success' => $success,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
