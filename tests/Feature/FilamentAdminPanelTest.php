<?php

use App\Filament\Resources\ApiRequestLogs\Pages\ListApiRequestLogs;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ProjectAuthSettings;
use App\Filament\Resources\Projects\Pages\ProjectEmailTemplates;
use App\Filament\Resources\Projects\Pages\ProjectIntegrationDetails;
use App\Filament\Resources\Projects\Pages\ProjectMailSettings;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ApiRequestLog;
use App\Models\Project;
use App\Models\ProjectEmailTemplate;
use App\Models\User;
use App\Services\Auth\ProjectMailService;
use App\Support\ProjectEmailTemplateDefaults;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function authenticateFilamentOwner(User $owner): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    test()->actingAs($owner);
}

/**
 * @return array<int, array<string, mixed>>
 */
function projectEmailTemplateFormState(Project $project): array
{
    $templates = $project->fresh()->emailTemplates()->get()->keyBy(
        fn (ProjectEmailTemplate $template): string => $template->type->value,
    );

    return collect(ProjectEmailTemplateDefaults::for($project))
        ->map(function (array $defaultTemplate) use ($templates): array {
            /** @var ProjectEmailTemplate|null $existingTemplate */
            $existingTemplate = $templates->get($defaultTemplate['type']->value);

            return [
                'type' => $defaultTemplate['type']->value,
                'subject' => $existingTemplate?->subject ?? $defaultTemplate['subject'],
                'html_body' => $existingTemplate?->html_body ?? $defaultTemplate['html_body'],
                'text_body' => $existingTemplate?->text_body ?? $defaultTemplate['text_body'],
                'is_enabled' => $existingTemplate?->is_enabled ?? $defaultTemplate['is_enabled'],
            ];
        })
        ->values()
        ->all();
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

it('shows the full project settings sub-navigation inside the admin panel', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    authenticateFilamentOwner($owner);

    $this->get(ProjectResource::getUrl('edit', ['record' => $project]))
        ->assertOk()
        ->assertSee(ProjectResource::getUrl('mail-settings', ['record' => $project]), false)
        ->assertSee(ProjectResource::getUrl('auth-settings', ['record' => $project]), false)
        ->assertSee(ProjectResource::getUrl('email-templates', ['record' => $project]), false)
        ->assertSee(ProjectResource::getUrl('integration', ['record' => $project]), false);
});

it('loads the project settings pages for the owning account', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    authenticateFilamentOwner($owner);

    foreach ([ProjectMailSettings::class, ProjectAuthSettings::class, ProjectEmailTemplates::class] as $page) {
        Livewire::test($page, ['record' => $project->getKey()])
            ->assertOk();
    }
});

it('prevents owners from loading another owners project settings pages', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherOwnersProject = Project::factory()->for($otherOwner, 'owner')->create();

    authenticateFilamentOwner($owner);

    foreach (['mail-settings', 'auth-settings', 'email-templates'] as $page) {
        $this->get(ProjectResource::getUrl($page, ['record' => $otherOwnersProject]))
            ->assertNotFound();
    }
});

it('validates required smtp fields when custom smtp mode is selected', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    authenticateFilamentOwner($owner);

    $mailSettings = $project->mailSettings;

    Livewire::test(ProjectMailSettings::class, ['record' => $project->getKey()])
        ->fillForm([
            'mail_mode' => 'custom_smtp',
            'from_name' => $mailSettings->from_name,
            'from_email' => $mailSettings->from_email,
            'reply_to_email' => $mailSettings->reply_to_email,
            'support_email' => $mailSettings->support_email,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_encryption' => null,
            'smtp_timeout' => null,
        ])
        ->call('save')
        ->assertHasFormErrors([
            'smtp_host' => 'required',
            'smtp_port' => 'required',
            'smtp_username' => 'required',
            'smtp_password' => 'required',
            'smtp_encryption' => 'required',
        ]);
});

it('preserves the stored smtp password when it is left blank during updates', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $project->mailSettings->update([
        'mail_mode' => 'custom_smtp',
        'smtp_host' => 'smtp.example.test',
        'smtp_port' => 587,
        'smtp_username' => 'mailer-user',
        'smtp_password_encrypted' => 'existing-secret',
        'smtp_encryption' => 'tls',
        'smtp_timeout' => 15,
    ]);

    $originalCiphertext = $project->fresh()->mailSettings->getRawOriginal('smtp_password_encrypted');
    $mailSettings = $project->fresh()->mailSettings;

    authenticateFilamentOwner($owner);

    Livewire::test(ProjectMailSettings::class, ['record' => $project->getKey()])
        ->fillForm([
            'mail_mode' => 'custom_smtp',
            'from_name' => 'Updated Sender',
            'from_email' => $mailSettings->from_email,
            'reply_to_email' => $mailSettings->reply_to_email,
            'support_email' => 'support@acme.test',
            'smtp_host' => $mailSettings->smtp_host,
            'smtp_port' => $mailSettings->smtp_port,
            'smtp_username' => $mailSettings->smtp_username,
            'smtp_password' => '',
            'smtp_encryption' => $mailSettings->smtp_encryption,
            'smtp_timeout' => $mailSettings->smtp_timeout,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $freshMailSettings = $project->fresh()->mailSettings;

    expect($freshMailSettings->smtp_password_encrypted)->toBe('existing-secret')
        ->and($freshMailSettings->getRawOriginal('smtp_password_encrypted'))->toBe($originalCiphertext)
        ->and($freshMailSettings->from_name)->toBe('Updated Sender')
        ->and($freshMailSettings->support_email)->toBe('support@acme.test');
});

it('can clear the stored smtp password from the mail settings page', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $project->mailSettings->update([
        'mail_mode' => 'custom_smtp',
        'smtp_host' => 'smtp.example.test',
        'smtp_port' => 587,
        'smtp_username' => 'mailer-user',
        'smtp_password_encrypted' => 'existing-secret',
        'smtp_encryption' => 'tls',
        'is_verified' => true,
        'last_tested_at' => now(),
    ]);

    authenticateFilamentOwner($owner);

    Livewire::test(ProjectMailSettings::class, ['record' => $project->getKey()])
        ->assertActionVisible('resetSmtpPassword')
        ->callAction('resetSmtpPassword')
        ->assertNotified();

    expect($project->fresh()->mailSettings->smtp_password_encrypted)->toBeNull()
        ->and($project->fresh()->mailSettings->is_verified)->toBeFalse()
        ->and($project->fresh()->mailSettings->last_tested_at)->toBeNull();
});

it('can send a project mail test email from the admin panel', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    authenticateFilamentOwner($owner);

    $mailService = Mockery::mock(ProjectMailService::class);
    $mailService->shouldReceive('sendTestEmail')
        ->once()
        ->withArgs(fn (Project $resolvedProject, string $recipient): bool => $resolvedProject->is($project) && $recipient === 'owner@example.com');

    app()->instance(ProjectMailService::class, $mailService);

    Livewire::test(ProjectMailSettings::class, ['record' => $project->getKey()])
        ->callAction('sendTestEmail', ['recipient' => 'owner@example.com'])
        ->assertNotified();
});

it('handles project mail test email failures gracefully', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    authenticateFilamentOwner($owner);

    $mailService = Mockery::mock(ProjectMailService::class);
    $mailService->shouldReceive('sendTestEmail')
        ->once()
        ->andThrow(new Exception('Transport failure'));

    app()->instance(ProjectMailService::class, $mailService);

    Livewire::test(ProjectMailSettings::class, ['record' => $project->getKey()])
        ->callAction('sendTestEmail', ['recipient' => 'owner@example.com'])
        ->assertNotified();
});

it('updates auth settings from the admin panel', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    authenticateFilamentOwner($owner);

    Livewire::test(ProjectAuthSettings::class, ['record' => $project->getKey()])
        ->fillForm([
            'auth_mode' => 'standard',
            'login_identifier_mode' => 'email',
            'email_verification_enabled' => true,
            'magic_link_enabled' => true,
            'access_token_ttl_minutes' => 30,
            'refresh_token_ttl_days' => 14,
            'otp_enabled' => true,
            'otp_length' => 8,
            'otp_ttl_minutes' => 5,
            'otp_max_attempts' => 3,
            'otp_resend_cooldown_seconds' => 90,
            'otp_daily_limit_per_email' => 12,
            'forgot_password_enabled' => true,
            'reset_password_ttl_minutes' => 45,
            'forgot_password_requests_per_hour' => 4,
            'ghost_accounts_enabled' => true,
            'max_ghost_accounts_per_email' => 2,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $authSettings = $project->fresh()->authSettings;

    expect($authSettings->access_token_ttl_minutes)->toBe(30)
        ->and($authSettings->refresh_token_ttl_days)->toBe(14)
        ->and($authSettings->otp_length)->toBe(8)
        ->and($authSettings->otp_max_attempts)->toBe(3)
        ->and($authSettings->otp_resend_cooldown_seconds)->toBe(90)
        ->and($authSettings->reset_password_ttl_minutes)->toBe(45)
        ->and($authSettings->email_verification_enabled)->toBeTrue()
        ->and($authSettings->magic_link_enabled)->toBeTrue()
        ->and($authSettings->max_ghost_accounts_per_email)->toBe(2);
});

it('updates project email templates from the admin panel', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    authenticateFilamentOwner($owner);

    $templates = projectEmailTemplateFormState($project);
    $templates[0]['subject'] = 'Your {{ project_name }} secure code';
    $templates[0]['html_body'] = '<p>Use {{ otp_code }} to sign in to {{ project_name }}.</p>';
    $templates[0]['text_body'] = 'Use {{ otp_code }} to sign in to {{ project_name }}.';
    $templates[0]['is_enabled'] = false;

    Livewire::test(ProjectEmailTemplates::class, ['record' => $project->getKey()])
        ->fillForm([
            'templates' => $templates,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $otpTemplate = $project->fresh()->emailTemplates()->where('type', 'otp')->firstOrFail();

    expect($otpTemplate->subject)->toBe('Your {{ project_name }} secure code')
        ->and($otpTemplate->html_body)->toBe('<p>Use {{ otp_code }} to sign in to {{ project_name }}.</p>')
        ->and($otpTemplate->text_body)->toBe('Use {{ otp_code }} to sign in to {{ project_name }}.')
        ->and($otpTemplate->is_enabled)->toBeFalse();
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
