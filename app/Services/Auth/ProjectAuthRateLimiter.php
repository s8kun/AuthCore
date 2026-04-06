<?php

namespace App\Services\Auth;

use App\Models\Project;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ProjectAuthRateLimiter
{
    /**
     * Ensure the request is within the configured limit and consume one attempt.
     */
    public function ensureWithinLimit(
        Project $project,
        string $scope,
        int $maxAttempts,
        int $decaySeconds,
        ?string $secondaryKey = null,
        string $message = 'Too many requests.',
    ): void {
        $limiterKey = $this->key($project, $scope, $secondaryKey);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($limiterKey);

            throw new HttpResponseException(response()->json([
                'message' => $message,
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => $retryAfter,
            ]));
        }

        RateLimiter::hit($limiterKey, $decaySeconds);
    }

    /**
     * Clear the limiter state for a scope.
     */
    public function clear(Project $project, string $scope, ?string $secondaryKey = null): void
    {
        RateLimiter::clear($this->key($project, $scope, $secondaryKey));
    }

    /**
     * Build a stable project-aware limiter key.
     */
    public function key(Project $project, string $scope, ?string $secondaryKey = null): string
    {
        return implode('|', array_filter([
            'project-auth',
            $project->getKey(),
            $scope,
            $secondaryKey,
        ], fn (?string $value): bool => filled($value)));
    }
}
