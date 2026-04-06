<?php

use App\Models\ApiRequestLog;
use App\Models\Project;
use App\Models\ProjectUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function projectHeaders(Project $project, ?string $token = null): array
{
    return array_filter([
        'X-Project-Key' => $project->api_key,
        'Authorization' => $token === null ? null : 'Bearer '.$token,
    ]);
}

it('rejects missing and invalid project keys', function () {
    $payload = [
        'email' => 'project-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertBadRequest()
        ->assertJson([
            'message' => 'The X-Project-Key header is required.',
        ]);

    $this->postJson('/api/v1/auth/register', $payload, [
        'X-Project-Key' => 'invalid-project-key',
    ])->assertUnauthorized()
        ->assertJson([
            'message' => 'The provided project key is invalid.',
        ]);
});

it('registers, authenticates, returns the current user, and logs out a project user', function () {
    $project = Project::factory()->create();
    $password = 'password';

    $registerResponse = $this->postJson('/api/v1/auth/register', [
        'email' => 'new-user@example.com',
        'password' => $password,
        'password_confirmation' => $password,
        'device_name' => 'Integration Test Device',
    ], projectHeaders($project));

    $registerResponse->assertCreated()
        ->assertJsonPath('data.user.email', 'new-user@example.com')
        ->assertJsonPath('data.user.project_id', $project->id)
        ->assertJsonPath('data.token_type', 'Bearer');

    $token = $registerResponse->json('data.access_token');
    [$personalAccessTokenId] = explode('|', $token, 2);

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($registerResponse->json('data.expires_at'))->not->toBeNull();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertOk()
        ->assertJsonPath('data.email', 'new-user@example.com');

    $this->postJson('/api/v1/auth/logout', [], projectHeaders($project, $token))
        ->assertOk()
        ->assertJsonPath('data.message', 'Logged out successfully.');

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/register',
        'method' => 'POST',
    ]);

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/me',
        'method' => 'GET',
    ]);

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/logout',
        'method' => 'POST',
    ]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => (int) $personalAccessTokenId,
    ]);

    Auth::forgetGuards();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertUnauthorized();
});

it('allows the same email to register in different projects', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $payload = [
        'email' => 'shared@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'Shared Email Device',
    ];

    $this->postJson('/api/v1/auth/register', $payload, projectHeaders($project))
        ->assertCreated()
        ->assertJsonPath('data.user.project_id', $project->id);

    $this->postJson('/api/v1/auth/register', $payload, projectHeaders($otherProject))
        ->assertCreated()
        ->assertJsonPath('data.user.project_id', $otherProject->id);

    expect(ProjectUser::query()->where('email', 'shared@example.com')->count())->toBe(2);
});

it('authenticates login requests within the resolved project', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    ProjectUser::factory()->for($project)->create([
        'email' => 'shared@example.com',
        'password' => 'password',
    ]);

    ProjectUser::factory()->for($otherProject)->create([
        'email' => 'shared@example.com',
        'password' => 'different-password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'shared@example.com',
        'password' => 'password',
    ], projectHeaders($project))
        ->assertOk()
        ->assertJsonPath('data.user.project_id', $project->id)
        ->assertJsonPath('data.user.email', 'shared@example.com');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'shared@example.com',
        'password' => 'password',
    ], projectHeaders($otherProject))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('forbids using a token from one project against another project', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $projectUser = ProjectUser::factory()->for($project)->create();
    $token = $projectUser->createToken('cross-project-test')->plainTextToken;

    $this->getJson('/api/v1/auth/me', projectHeaders($otherProject, $token))
        ->assertForbidden()
        ->assertJson([
            'message' => 'This token does not belong to the requested project.',
        ]);
});

it('keys rate limiting by project and endpoint', function () {
    config()->set('cache.default', 'file');
    Cache::store('file')->flush();

    $project = Project::factory()->create([
        'rate_limit' => 1,
    ]);
    $otherProject = Project::factory()->create([
        'rate_limit' => 1,
    ]);
    $projectUser = ProjectUser::factory()->for($project)->create();
    $otherProjectUser = ProjectUser::factory()->for($otherProject)->create();
    $token = $projectUser->createToken('project-a')->plainTextToken;
    $otherToken = $otherProjectUser->createToken('project-b')->plainTextToken;

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertOk();

    Auth::forgetGuards();

    $this->postJson('/api/v1/auth/logout', [], projectHeaders($project, $token))
        ->assertOk();

    Auth::forgetGuards();

    $this->getJson('/api/v1/auth/me', projectHeaders($otherProject, $otherToken))
        ->assertOk();
});

it('applies the per-project rate limit and still logs the throttled request', function () {
    config()->set('cache.default', 'file');
    Cache::store('file')->flush();

    $project = Project::factory()->create([
        'rate_limit' => 1,
    ]);
    $projectUser = ProjectUser::factory()->for($project)->create();
    $token = $projectUser->createToken('rate-limit-test')->plainTextToken;

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertOk();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertTooManyRequests()
        ->assertJson([
            'message' => 'Too many requests.',
        ]);

    expect(ApiRequestLog::query()->where('project_id', $project->id)->count())->toBe(2);
});

it('expires project user tokens and records project scoped api logs', function () {
    config()->set('sanctum.expiration', 1);

    $project = Project::factory()->create();
    ProjectUser::factory()->for($project)->create([
        'email' => 'expiring@example.com',
        'password' => 'password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'expiring@example.com',
        'password' => 'password',
        'device_name' => 'Expiry Test Device',
    ], projectHeaders($project));

    $loginResponse->assertOk()
        ->assertJsonPath('data.user.email', 'expiring@example.com');

    $token = $loginResponse->json('data.access_token');
    [$personalAccessTokenId] = explode('|', $token, 2);

    expect(PersonalAccessToken::query()->find((int) $personalAccessTokenId)?->expires_at)->not->toBeNull();

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/login',
        'method' => 'POST',
    ]);

    $this->travel(2)->minutes();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertUnauthorized();
});
