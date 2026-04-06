<?php

use App\Enums\ProjectMailMode;
use App\Models\Project;
use App\Services\Auth\ProjectEmailTemplateRenderer;
use App\Services\Auth\ProjectMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('uses the platform mailer when the project is configured for platform mail', function () {
    $project = Project::factory()->create();
    $project->mailSettings()->update([
        'mail_mode' => ProjectMailMode::Platform,
    ]);

    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('to')->once()->with('owner@example.com')->andReturnSelf();
    $mailer->shouldReceive('send')->once();

    $mailManager = Mockery::mock(MailManager::class);
    $mailManager->shouldReceive('mailer')->once()->andReturn($mailer);
    $mailManager->shouldNotReceive('build');

    $service = new ProjectMailService(
        $mailManager,
        Mockery::mock(ProjectEmailTemplateRenderer::class),
    );

    $service->sendTestEmail($project->fresh(), 'owner@example.com');

    expect($project->fresh()->mailSettings->is_verified)->toBeTrue();
});

it('builds a custom smtp mailer when the project is configured for custom smtp', function () {
    $project = Project::factory()->create();
    $project->mailSettings->update([
        'mail_mode' => ProjectMailMode::CustomSmtp,
        'smtp_host' => 'smtp.example.test',
        'smtp_port' => 587,
        'smtp_username' => 'mailer-user',
        'smtp_password_encrypted' => 'super-secret',
        'smtp_encryption' => 'tls',
        'smtp_timeout' => 10,
    ]);

    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('to')->once()->with('owner@example.com')->andReturnSelf();
    $mailer->shouldReceive('send')->once();

    $mailManager = Mockery::mock(MailManager::class);
    $mailManager->shouldReceive('build')->once()->with(Mockery::on(function (array $config): bool {
        return $config['transport'] === 'smtp'
            && $config['host'] === 'smtp.example.test'
            && $config['port'] === 587
            && $config['username'] === 'mailer-user'
            && $config['password'] === 'super-secret';
    }))->andReturn($mailer);
    $mailManager->shouldNotReceive('mailer');

    $service = new ProjectMailService(
        $mailManager,
        Mockery::mock(ProjectEmailTemplateRenderer::class),
    );

    $service->sendTestEmail($project->fresh(), 'owner@example.com');

    expect($project->fresh()->mailSettings->is_verified)->toBeTrue();
});
