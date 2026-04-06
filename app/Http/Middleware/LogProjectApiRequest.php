<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogProjectApiRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle any tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        /** @var Project|null $project */
        $project = $request->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);

        if ($project instanceof Project) {
            $project->apiRequestLogs()->create([
                'endpoint' => '/'.$request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
            ]);
        }
    }
}
