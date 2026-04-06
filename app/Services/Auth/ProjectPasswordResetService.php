<?php

namespace App\Services\Auth;

use App\Enums\AuthEventType;
use App\Enums\ProjectEmailTemplateType;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use App\Models\ProjectPasswordReset;
use App\Models\ProjectUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectPasswordResetService
{
    public function __construct(
        private readonly AuthEventLogger $authEventLogger,
        private readonly ProjectAuthRateLimiter $projectAuthRateLimiter,
        private readonly ProjectMailService $projectMailService,
        private readonly ProjectTokenService $projectTokenService,
    ) {}

    /**
     * Request a project-scoped password reset email.
     */
    public function request(Project $project, string $email, Request $request): void
    {
        $settings = $project->authSettings ?? $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());

        if (! $settings->forgot_password_enabled) {
            throw ValidationException::withMessages([
                'email' => ['Forgot password is disabled for this project.'],
            ]);
        }

        $this->projectAuthRateLimiter->ensureWithinLimit(
            $project,
            'forgot-password',
            $settings->forgot_password_requests_per_hour,
            3600,
            $email,
            'Too many forgot password requests.',
        );

        /** @var ProjectUser|null $projectUser */
        $projectUser = ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $email)
            ->where('is_active', true)
            ->where('is_ghost', false)
            ->first();

        if (! $projectUser instanceof ProjectUser) {
            $this->authEventLogger->log(
                $project,
                AuthEventType::PasswordResetRequested,
                true,
                $request,
                null,
                $email,
                ['user_exists' => false],
            );

            return;
        }

        $plainTextToken = Str::random(64);
        $expiresAt = now()->addMinutes($settings->reset_password_ttl_minutes);

        DB::transaction(function () use ($project, $projectUser, $email, $plainTextToken, $expiresAt, $request, $settings): void {
            ProjectPasswordReset::query()
                ->whereBelongsTo($project)
                ->whereBelongsTo($projectUser)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $project->passwordResets()->create([
                'project_user_id' => $projectUser->getKey(),
                'email' => $email,
                'token_hash' => hash('sha256', $plainTextToken),
                'expires_at' => $expiresAt,
                'requested_ip' => $request->ip(),
            ]);

            $resetUrl = route('api.v1.auth.reset-password', [
                'token' => $plainTextToken,
                'email' => $email,
            ]);

            $this->projectMailService->queueTemplateEmail(
                $project,
                $email,
                ProjectEmailTemplateType::ForgotPassword,
                [
                    'user_email' => $email,
                    'reset_link' => $resetUrl,
                    'expires_in' => $this->formatMinutes($settings->reset_password_ttl_minutes),
                ],
            );
        });

        $this->authEventLogger->log(
            $project,
            AuthEventType::PasswordResetRequested,
            true,
            $request,
            $projectUser,
            $email,
        );
    }

    /**
     * Complete a project-scoped password reset.
     */
    public function reset(Project $project, string $email, string $plainTextToken, string $password, Request $request): ProjectUser
    {
        /** @var ProjectUser $projectUser */
        $projectUser = ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $email)
            ->firstOrFail();

        /** @var ProjectPasswordReset|null $passwordReset */
        $passwordReset = ProjectPasswordReset::query()
            ->whereBelongsTo($project)
            ->whereBelongsTo($projectUser)
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->latest('created_at')
            ->first();

        if (! $passwordReset instanceof ProjectPasswordReset || ! $passwordReset->isUsable()) {
            throw ValidationException::withMessages([
                'token' => ['The password reset token is invalid or expired.'],
            ]);
        }

        DB::transaction(function () use ($project, $projectUser, $passwordReset, $password, $email): void {
            $projectUser->forceFill([
                'password' => Hash::make($password),
                'must_set_password' => false,
            ])->save();

            $passwordReset->forceFill([
                'used_at' => now(),
            ])->save();

            $this->projectTokenService->revokeAllTokensForUser($projectUser);

            $this->projectMailService->queueTemplateEmail(
                $project,
                $email,
                ProjectEmailTemplateType::ResetPasswordSuccess,
                [
                    'user_email' => $email,
                ],
            );
        });

        $this->authEventLogger->log(
            $project,
            AuthEventType::PasswordResetCompleted,
            true,
            $request,
            $projectUser,
            $email,
        );

        return $projectUser->refresh();
    }

    /**
     * Format a minute count for human-readable emails.
     */
    private function formatMinutes(int $minutes): string
    {
        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
