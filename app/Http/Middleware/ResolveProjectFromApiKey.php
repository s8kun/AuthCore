<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveProjectFromApiKey
{
    public const PROJECT_ATTRIBUTE = 'project';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Project-Key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return response()->json([
                'message' => 'The X-Project-Key header is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $project = Project::query()
            ->where('api_key', trim($apiKey))
            ->first();

        if (! $project instanceof Project) {
            return response()->json([
                'message' => 'The provided project key is invalid.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set(self::PROJECT_ATTRIBUTE, $project);

        return $next($request);
    }
}
