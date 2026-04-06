<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\ProjectUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectAccessMatchesToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Project|null $project */
        $project = $request->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);
        $projectUser = $request->user();

        if (! $projectUser instanceof ProjectUser || $projectUser->currentAccessToken() === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $project instanceof Project || $projectUser->project_id !== $project->id) {
            return response()->json([
                'message' => 'This token does not belong to the requested project.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $projectUser->is_active) {
            return response()->json([
                'message' => 'This account is inactive.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($projectUser->isPendingEmailVerification()) {
            return response()->json([
                'message' => 'Email verification is required before accessing this resource.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
