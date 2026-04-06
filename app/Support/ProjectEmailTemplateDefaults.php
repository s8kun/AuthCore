<?php

namespace App\Support;

use App\Enums\ProjectEmailTemplateType;
use App\Models\Project;

class ProjectEmailTemplateDefaults
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(Project $project): array
    {
        return [
            [
                'type' => ProjectEmailTemplateType::Otp,
                'subject' => '{{ project_name }} sign-in code',
                'html_body' => '<p>Hello {{ user_email }},</p><p>Your one-time code for {{ project_name }} is <strong>{{ otp_code }}</strong>.</p><p>This code expires in {{ expires_in }}.</p><p>If you did not request this code, please contact {{ support_email }}.</p>',
                'text_body' => "Hello {{ user_email }},\n\nYour one-time code for {{ project_name }} is {{ otp_code }}.\n\nThis code expires in {{ expires_in }}.\n\nIf you did not request this code, contact {{ support_email }}.",
                'is_enabled' => true,
            ],
            [
                'type' => ProjectEmailTemplateType::ForgotPassword,
                'subject' => 'Reset your {{ project_name }} password',
                'html_body' => '<p>Hello {{ user_email }},</p><p>We received a password reset request for {{ project_name }}.</p><p><a href="{{ reset_link }}">Reset your password</a></p><p>This link expires in {{ expires_in }}.</p><p>If you did not request this, contact {{ support_email }}.</p>',
                'text_body' => "Hello {{ user_email }},\n\nWe received a password reset request for {{ project_name }}.\n\nReset your password: {{ reset_link }}\n\nThis link expires in {{ expires_in }}.\n\nIf you did not request this, contact {{ support_email }}.",
                'is_enabled' => true,
            ],
            [
                'type' => ProjectEmailTemplateType::ResetPasswordSuccess,
                'subject' => '{{ project_name }} password reset complete',
                'html_body' => '<p>Hello {{ user_email }},</p><p>Your password for {{ project_name }} has been changed successfully.</p><p>If this was not you, contact {{ support_email }} immediately.</p>',
                'text_body' => "Hello {{ user_email }},\n\nYour password for {{ project_name }} has been changed successfully.\n\nIf this was not you, contact {{ support_email }} immediately.",
                'is_enabled' => true,
            ],
            [
                'type' => ProjectEmailTemplateType::Welcome,
                'subject' => 'Welcome to {{ project_name }}',
                'html_body' => '<p>Hello {{ user_email }},</p><p>Welcome to {{ project_name }}.</p><p>Need help? Contact {{ support_email }}.</p>',
                'text_body' => "Hello {{ user_email }},\n\nWelcome to {{ project_name }}.\n\nNeed help? Contact {{ support_email }}.",
                'is_enabled' => true,
            ],
            [
                'type' => ProjectEmailTemplateType::EmailVerification,
                'subject' => 'Verify your {{ project_name }} email',
                'html_body' => '<p>Hello {{ user_email }},</p><p>Your verification code for {{ project_name }} is <strong>{{ otp_code }}</strong>.</p><p>This code expires in {{ expires_in }}.</p>',
                'text_body' => "Hello {{ user_email }},\n\nYour verification code for {{ project_name }} is {{ otp_code }}.\n\nThis code expires in {{ expires_in }}.",
                'is_enabled' => true,
            ],
            [
                'type' => ProjectEmailTemplateType::GhostAccountInvite,
                'subject' => 'Claim your {{ project_name }} account',
                'html_body' => '<p>Hello {{ user_email }},</p><p>Your account has been prepared for {{ project_name }}.</p><p>Use code <strong>{{ otp_code }}</strong> to claim it.</p><p>This code expires in {{ expires_in }}.</p>',
                'text_body' => "Hello {{ user_email }},\n\nYour account has been prepared for {{ project_name }}.\n\nUse code {{ otp_code }} to claim it.\n\nThis code expires in {{ expires_in }}.",
                'is_enabled' => true,
            ],
        ];
    }
}
