<?php

use App\Enums\AuthEventType;
use App\Models\ApiRequestLog;
use App\Models\AuthEventLog;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use App\Models\ProjectEmailTemplate;
use App\Models\ProjectMailSetting;
use App\Models\ProjectUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('uses uuid ids for the auth provider models and provisions related project defaults', function () {
    expect(Schema::hasColumns('projects', [
        'id',
        'owner_id',
        'name',
        'slug',
        'api_key',
        'api_secret',
        'status',
        'rate_limit',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('project_users', [
            'id',
            'project_id',
            'email',
            'password',
            'first_name',
            'last_name',
            'phone',
            'role',
            'email_verified_at',
            'is_active',
            'is_ghost',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('api_request_logs', [
            'id',
            'project_id',
            'endpoint',
            'route_name',
            'method',
            'email',
            'ip_address',
            'status_code',
            'success',
            'created_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('project_auth_settings', [
            'id',
            'project_id',
            'access_token_ttl_minutes',
            'refresh_token_ttl_days',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('project_mail_settings', [
            'id',
            'project_id',
            'mail_mode',
            'from_email',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('project_email_templates', [
            'id',
            'project_id',
            'type',
            'subject',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('auth_event_logs', [
            'id',
            'project_id',
            'event_type',
            'success',
        ]))->toBeTrue();

    $project = Project::factory()->create();
    $projectUser = ProjectUser::factory()->for($project)->create();
    $apiRequestLog = ApiRequestLog::factory()->for($project)->create();
    $authSettings = ProjectAuthSetting::query()->whereBelongsTo($project)->first();
    $mailSettings = ProjectMailSetting::query()->whereBelongsTo($project)->first();
    $emailTemplate = ProjectEmailTemplate::query()->whereBelongsTo($project)->first();
    $authEventLog = AuthEventLog::query()->create([
        'project_id' => $project->getKey(),
        'project_user_id' => $projectUser->getKey(),
        'email' => $projectUser->email,
        'event_type' => AuthEventType::LoginSucceeded,
        'success' => true,
    ]);

    expect(Str::isUuid($project->id))->toBeTrue()
        ->and(Str::isUuid($projectUser->id))->toBeTrue()
        ->and(Str::isUuid($apiRequestLog->id))->toBeTrue()
        ->and(Str::isUuid($authSettings?->id))->toBeTrue()
        ->and(Str::isUuid($mailSettings?->id))->toBeTrue()
        ->and(Str::isUuid($emailTemplate?->id))->toBeTrue()
        ->and(Str::isUuid($authEventLog->id))->toBeTrue()
        ->and($projectUser->project_id)->toBe($project->id)
        ->and($apiRequestLog->project_id)->toBe($project->id)
        ->and($authSettings?->project_id)->toBe($project->id)
        ->and($mailSettings?->project_id)->toBe($project->id)
        ->and(ProjectEmailTemplate::query()->whereBelongsTo($project)->count())->toBe(6);
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

    $project->load(['owner', 'projectUsers', 'apiRequestLogs', 'authSettings', 'mailSettings', 'emailTemplates']);

    expect($project->owner)->not->toBeNull()
        ->and($project->projectUsers)->toHaveCount(2)
        ->and($project->apiRequestLogs)->toHaveCount(2)
        ->and($project->authSettings)->not->toBeNull()
        ->and($project->mailSettings)->not->toBeNull()
        ->and($project->emailTemplates)->toHaveCount(6)
        ->and($projectUsers->first()->project->is($project))->toBeTrue()
        ->and($apiRequestLogs->first()->project->is($project))->toBeTrue();
});

it('issues sanctum tokens for project users with uuid-compatible tokenable ids', function () {
    $projectUser = ProjectUser::factory()->create();
    $token = $projectUser->createToken('integration-test');

    expect($token->plainTextToken)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => ProjectUser::class,
        'tokenable_id' => $projectUser->id,
        'name' => 'integration-test',
    ]);
});
