<?php

use App\Enums\ProjectUserFieldType;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\ProjectUserField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function customFieldProjectHeaders(Project $project, ?string $token = null): array
{
    return array_filter([
        'X-Project-Key' => $project->api_key,
        'Authorization' => $token === null ? null : 'Bearer '.$token,
    ]);
}

it('persists custom field values and returns only API-visible custom fields', function () {
    $project = Project::factory()->create();

    $statusField = ProjectUserField::factory()
        ->for($project)
        ->enum(['pending', 'approved', 'cancelled'])
        ->required()
        ->create([
            'key' => 'status',
            'label' => 'Status',
        ]);

    $employeeNumberField = ProjectUserField::factory()
        ->for($project)
        ->uniqueField()
        ->create([
            'key' => 'employee_number',
            'label' => 'Employee Number',
        ]);

    $internalNotesField = ProjectUserField::factory()
        ->for($project)
        ->create([
            'key' => 'internal_notes',
            'label' => 'Internal Notes',
            'type' => ProjectUserFieldType::Text,
            'show_in_api' => false,
        ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'custom-fields@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'custom_fields' => [
            'status' => 'approved',
            'employee_number' => 'EMP-100',
            'internal_notes' => 'Internal only note',
        ],
    ], customFieldProjectHeaders($project));

    $response->assertCreated()
        ->assertJsonPath('data.user.custom_fields.status', 'approved')
        ->assertJsonPath('data.user.custom_fields.employee_number', 'EMP-100')
        ->assertJsonMissingPath('data.user.custom_fields.internal_notes');

    $projectUser = ProjectUser::query()
        ->whereBelongsTo($project)
        ->where('email', 'custom-fields@example.com')
        ->firstOrFail();

    $this->assertDatabaseHas('project_user_field_values', [
        'project_id' => $project->id,
        'project_user_id' => $projectUser->id,
        'project_user_field_id' => $statusField->id,
        'value_string' => 'approved',
    ]);

    $this->assertDatabaseHas('project_user_field_values', [
        'project_id' => $project->id,
        'project_user_id' => $projectUser->id,
        'project_user_field_id' => $employeeNumberField->id,
        'value_string' => 'EMP-100',
    ]);

    $this->assertDatabaseHas('project_user_field_values', [
        'project_id' => $project->id,
        'project_user_id' => $projectUser->id,
        'project_user_field_id' => $internalNotesField->id,
        'value_text' => 'Internal only note',
    ]);

    $token = $response->json('data.access_token');

    $this->getJson('/api/v1/auth/me', customFieldProjectHeaders($project, $token))
        ->assertOk()
        ->assertJsonPath('data.custom_fields.status', 'approved')
        ->assertJsonPath('data.custom_fields.employee_number', 'EMP-100')
        ->assertJsonMissingPath('data.custom_fields.internal_notes');
});

it('rejects invalid and undefined custom field payloads', function () {
    $project = Project::factory()->create();

    ProjectUserField::factory()
        ->for($project)
        ->enum(['pending', 'approved'])
        ->required()
        ->create([
            'key' => 'status',
            'label' => 'Status',
        ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'invalid-custom-fields@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'custom_fields' => [
            'status' => 'archived',
            'unknown_field' => 'value',
        ],
    ], customFieldProjectHeaders($project));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'custom_fields.status',
            'custom_fields.unknown_field',
        ]);
});

it('enforces unique custom fields within a project while allowing duplicates across projects', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    ProjectUserField::factory()
        ->for($project)
        ->uniqueField()
        ->create([
            'key' => 'employee_number',
            'label' => 'Employee Number',
        ]);

    ProjectUserField::factory()
        ->for($otherProject)
        ->uniqueField()
        ->create([
            'key' => 'employee_number',
            'label' => 'Employee Number',
        ]);

    $payload = [
        'password' => 'password',
        'password_confirmation' => 'password',
        'custom_fields' => [
            'employee_number' => 'EMP-200',
        ],
    ];

    $this->postJson('/api/v1/auth/register', [
        ...$payload,
        'email' => 'first@example.com',
    ], customFieldProjectHeaders($project))->assertCreated();

    $this->postJson('/api/v1/auth/register', [
        ...$payload,
        'email' => 'second@example.com',
    ], customFieldProjectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['custom_fields.employee_number']);

    $this->postJson('/api/v1/auth/register', [
        ...$payload,
        'email' => 'other-project@example.com',
    ], customFieldProjectHeaders($otherProject))->assertCreated();
});

it('applies custom field defaults during registration when no explicit value is submitted', function () {
    $project = Project::factory()->create();

    $statusField = ProjectUserField::factory()
        ->for($project)
        ->enum(['pending', 'approved'])
        ->create([
            'key' => 'status',
            'label' => 'Status',
            'default_value' => 'pending',
        ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'defaults@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], customFieldProjectHeaders($project));

    $response->assertCreated()
        ->assertJsonPath('data.user.custom_fields.status', 'pending');

    $projectUser = ProjectUser::query()
        ->whereBelongsTo($project)
        ->where('email', 'defaults@example.com')
        ->firstOrFail();

    $this->assertDatabaseHas('project_user_field_values', [
        'project_id' => $project->id,
        'project_user_id' => $projectUser->id,
        'project_user_field_id' => $statusField->id,
        'value_string' => 'pending',
    ]);
});
