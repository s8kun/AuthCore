<?php

use App\Filament\Resources\ApiRequestLogs\Pages\ListApiRequestLogs;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ProjectIntegrationDetails;
use App\Models\ApiRequestLog;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function authenticateFilamentOwner(User $owner): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    test()->actingAs($owner);
}

it('requires owner authentication for the admin panel', function () {
    $this->get('/admin/projects')
        ->assertRedirect('/admin/login');
});

it('lists only the authenticated owners projects', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $ownerProjects = Project::factory()
        ->count(2)
        ->for($owner, 'owner')
        ->create();
    $otherOwnersProject = Project::factory()
        ->for($otherOwner, 'owner')
        ->create();

    authenticateFilamentOwner($owner);

    Livewire::test(ListProjects::class)
        ->assertOk()
        ->assertCanSeeTableRecords($ownerProjects)
        ->assertCanNotSeeTableRecords([$otherOwnersProject]);
});

it('creates a project from the admin panel and redirects to integration details', function () {
    $owner = User::factory()->create();

    authenticateFilamentOwner($owner);

    $component = Livewire::test(CreateProject::class)
        ->fillForm([
            'name' => 'Acme Web',
            'rate_limit' => 120,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $project = Project::query()->where('name', 'Acme Web')->firstOrFail();

    $component->assertRedirect(route('filament.admin.resources.projects.integration', ['record' => $project]));

    expect($project->owner_id)->toBe($owner->id)
        ->and($project->rate_limit)->toBe(120)
        ->and($project->api_key)->toHaveLength(40);
});

it('updates a project from the admin panel', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create([
        'name' => 'Starter App',
        'rate_limit' => 60,
    ]);

    authenticateFilamentOwner($owner);

    Livewire::test(EditProject::class, [
        'record' => $project->getKey(),
    ])
        ->assertOk()
        ->fillForm([
            'name' => 'Starter App Updated',
            'rate_limit' => 180,
            'api_key' => $project->api_key,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($project->refresh()->name)->toBe('Starter App Updated')
        ->and($project->rate_limit)->toBe(180);
});

it('shows project integration details inside the admin panel', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    ApiRequestLog::factory()
        ->count(2)
        ->for($project)
        ->create();

    authenticateFilamentOwner($owner);

    Livewire::test(ProjectIntegrationDetails::class, [
        'record' => $project->getKey(),
    ])
        ->assertOk()
        ->assertSee($project->api_key)
        ->assertSee(route('api.v1.auth.register'))
        ->assertSee(route('api.v1.auth.login'))
        ->assertSee(route('api.v1.auth.me'))
        ->assertSee(route('api.v1.auth.logout'))
        ->assertSee('Bearer <plain-text-token>');
});

it('shows only the authenticated owners api request logs', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $otherProject = Project::factory()->for($otherOwner, 'owner')->create();

    $ownerLogs = ApiRequestLog::factory()
        ->count(2)
        ->for($project)
        ->create();
    $otherOwnersLog = ApiRequestLog::factory()
        ->for($otherProject)
        ->create();

    authenticateFilamentOwner($owner);

    Livewire::test(ListApiRequestLogs::class)
        ->assertOk()
        ->assertCanSeeTableRecords($ownerLogs)
        ->assertCanNotSeeTableRecords([$otherOwnersLog]);
});
