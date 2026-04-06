<?php

namespace App\Providers;

use App\Http\Middleware\ResolveProjectFromApiKey;
use App\Models\Project;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('project-auth', function (Request $request): Limit {
            /** @var Project|null $project */
            $project = $request->attributes->get(ResolveProjectFromApiKey::PROJECT_ATTRIBUTE);

            $projectRateLimit = $project?->rate_limit ?? 60;
            $routeKey = $request->route()?->uri() ?? $request->path();
            $rateLimitKey = implode('|', [
                $project?->getKey() ?? 'unknown-project',
                $request->ip() ?? 'unknown-ip',
                $routeKey,
            ]);

            return Limit::perMinute($projectRateLimit)
                ->by($rateLimitKey)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many requests.',
                    ], 429, $headers);
                });
        });
    }
}
