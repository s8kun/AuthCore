<?php

namespace App\Services\Auth;

use App\Enums\ProjectEmailTemplateType;
use App\Enums\ProjectMailMode;
use App\Jobs\SendProjectEmailJob;
use App\Mail\ProjectTemplatedMail;
use App\Models\Project;
use App\Models\ProjectMailSetting;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use RuntimeException;

class ProjectMailService
{
    public function __construct(
        private readonly MailManager $mailManager,
        private readonly ProjectEmailTemplateRenderer $templateRenderer,
    ) {}

    /**
     * Queue a templated email for later delivery.
     *
     * @param  array<string, mixed>  $variables
     */
    public function queueTemplateEmail(
        Project $project,
        string $recipient,
        ProjectEmailTemplateType $templateType,
        array $variables = [],
    ): void {
        SendProjectEmailJob::dispatch(
            $project->getKey(),
            $recipient,
            $templateType->value,
            $variables,
        );
    }

    /**
     * Send a templated email immediately.
     *
     * @param  array<string, mixed>  $variables
     */
    public function sendTemplateNow(
        Project $project,
        string $recipient,
        ProjectEmailTemplateType $templateType,
        array $variables = [],
    ): void {
        $project->loadMissing(['mailSettings', 'emailTemplates']);

        $template = $project->emailTemplates
            ->first(fn ($candidate): bool => $candidate->type === $templateType);

        if ($template === null || ! $template->is_enabled) {
            return;
        }

        $mailSettings = $project->mailSettings;

        if (! $mailSettings instanceof ProjectMailSetting) {
            throw new RuntimeException('Project mail settings are not configured.');
        }

        $rendered = $this->templateRenderer->render($project, $template, $variables);

        $mailable = new ProjectTemplatedMail(
            subjectLine: $rendered['subject'],
            htmlBody: $rendered['html_body'],
            textBody: $rendered['text_body'],
            fromEmail: $mailSettings->from_email,
            fromName: $mailSettings->from_name,
            replyToEmail: $mailSettings->reply_to_email,
        );

        $this->mailerFor($mailSettings)
            ->to($recipient)
            ->send($mailable);
    }

    /**
     * Send a verification-style test email immediately.
     */
    public function sendTestEmail(Project $project, string $recipient): void
    {
        $project->loadMissing('mailSettings');

        $mailSettings = $project->mailSettings;

        if (! $mailSettings instanceof ProjectMailSetting) {
            throw new RuntimeException('Project mail settings are not configured.');
        }

        $mailable = new ProjectTemplatedMail(
            subjectLine: "Test email for {$project->name}",
            htmlBody: '<p>This is a successful test email from your project mail settings.</p>',
            textBody: 'This is a successful test email from your project mail settings.',
            fromEmail: $mailSettings->from_email,
            fromName: $mailSettings->from_name,
            replyToEmail: $mailSettings->reply_to_email,
        );

        $this->mailerFor($mailSettings)
            ->to($recipient)
            ->send($mailable);

        $mailSettings->forceFill([
            'is_verified' => true,
            'last_tested_at' => now(),
        ])->save();
    }

    /**
     * Get the mailer that should be used for the project.
     */
    private function mailerFor(ProjectMailSetting $mailSettings): Mailer
    {
        if ($mailSettings->mail_mode === ProjectMailMode::Platform) {
            return $this->mailManager->mailer();
        }

        if (
            blank($mailSettings->smtp_host)
            || blank($mailSettings->smtp_port)
            || blank($mailSettings->smtp_username)
            || blank($mailSettings->smtp_password_encrypted)
        ) {
            throw new RuntimeException('Custom SMTP settings are incomplete.');
        }

        return $this->mailManager->build([
            'transport' => 'smtp',
            'host' => $mailSettings->smtp_host,
            'port' => $mailSettings->smtp_port,
            'username' => $mailSettings->smtp_username,
            'password' => $mailSettings->smtp_password_encrypted,
            'scheme' => $mailSettings->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            'timeout' => $mailSettings->smtp_timeout,
        ]);
    }
}
