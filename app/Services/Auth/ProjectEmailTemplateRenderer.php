<?php

namespace App\Services\Auth;

use App\Models\Project;
use App\Models\ProjectEmailTemplate;

class ProjectEmailTemplateRenderer
{
    /**
     * Render the template subject and body with safe placeholder substitution.
     *
     * @param  array<string, mixed>  $variables
     * @return array{html_body: string, subject: string, text_body: ?string}
     */
    public function render(Project $project, ProjectEmailTemplate $template, array $variables = []): array
    {
        $mailSettings = $project->mailSettings()->first();
        $baseVariables = [
            'app_name' => (string) config('app.name'),
            'project_name' => $project->name,
            'support_email' => $mailSettings?->support_email ?? $mailSettings?->from_email ?? config('mail.from.address'),
            'user_email' => (string) ($variables['user_email'] ?? ''),
            'otp_code' => (string) ($variables['otp_code'] ?? ''),
            'reset_link' => (string) ($variables['reset_link'] ?? ''),
            'expires_in' => (string) ($variables['expires_in'] ?? ''),
        ];

        $renderVariables = [...$baseVariables, ...$variables];

        return [
            'subject' => $this->replacePlaceholders($template->subject, $renderVariables, false),
            'html_body' => $this->replacePlaceholders($template->html_body, $renderVariables, true),
            'text_body' => $template->text_body === null
                ? null
                : $this->replacePlaceholders($template->text_body, $renderVariables, false),
        ];
    }

    /**
     * Replace placeholder tokens in a template string.
     *
     * @param  array<string, mixed>  $variables
     */
    private function replacePlaceholders(string $template, array $variables, bool $escape): string
    {
        return (string) preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $matches) use ($variables, $escape): string {
            $value = (string) ($variables[$matches[1]] ?? '');

            return $escape ? e($value) : $value;
        }, $template);
    }
}
