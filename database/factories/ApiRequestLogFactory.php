<?php

namespace Database\Factories;

use App\Models\ApiRequestLog;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'endpoint' => fake()->randomElement([
                '/api/v1/auth/register',
                '/api/v1/auth/login',
                '/api/v1/auth/me',
                '/api/v1/auth/logout',
            ]),
            'route_name' => fake()->randomElement([
                'api.v1.auth.register',
                'api.v1.auth.login',
                'api.v1.auth.me',
                'api.v1.auth.logout',
            ]),
            'method' => fake()->randomElement(['GET', 'POST']),
            'email' => fake()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'status_code' => 200,
            'success' => true,
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
