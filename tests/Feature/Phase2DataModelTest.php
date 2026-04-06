<?php

use App\Models\ApiRequestLog;
use App\Models\Project;
use App\Models\ProjectUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('uses integer ids for the phase 2 models', function () {
    expect(Schema::hasColumns('projects', [
        'id',
        'owner_id',
        'name',
        'api_key',
        'rate_limit',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('project_users', [
            'id',
            'project_id',
            'email',
            'password',
            'role',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('api_request_logs', [
            'id',
            'project_id',
            'endpoint',
            'method',
            'ip_address',
            'created_at',
        ]))->toBeTrue();

    $project = Project::factory()->create();
    $projectUser = ProjectUser::factory()->for($project)->create();
    $apiRequestLog = ApiRequestLog::factory()->for($project)->create();

    expect($project->id)->toBeInt()
        ->and($projectUser->id)->toBeInt()
        ->and($apiRequestLog->id)->toBeInt()
        ->and($projectUser->project_id)->toBe($project->id)
        ->and($apiRequestLog->project_id)->toBe($project->id);
});

it('allows the same email address to exist in different projects', function () {
    $email = fake()->safeEmail();
    $firstProject = Project::factory()->create();
    $secondProject = Project::factory()->create();

    ProjectUser::factory()->for($firstProject)->create([
        'email' => $email,
    ]);

    ProjectUser::factory()->for($secondProject)->create([
        'email' => $email,
    ]);

    expect(
        ProjectUser::query()->where('email', $email)->count()
    )->toBe(2);
});

it('loads the expected project relationships', function () {
    $project = Project::factory()->create();
    $projectUsers = ProjectUser::factory()->count(2)->for($project)->create();
    $apiRequestLogs = ApiRequestLog::factory()->count(2)->for($project)->create();

    $project->load(['owner', 'projectUsers', 'apiRequestLogs']);

    expect($project->owner)->not->toBeNull()
        ->and($project->projectUsers)->toHaveCount(2)
        ->and($project->apiRequestLogs)->toHaveCount(2)
        ->and($projectUsers->first()->project->is($project))->toBeTrue()
        ->and($apiRequestLogs->first()->project->is($project))->toBeTrue();
});

it('issues sanctum tokens for project users with integer tokenable ids', function () {
    $projectUser = ProjectUser::factory()->create();
    $token = $projectUser->createToken('integration-test');

    expect($token->plainTextToken)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => ProjectUser::class,
        'tokenable_id' => $projectUser->id,
        'name' => 'integration-test',
    ]);
});
