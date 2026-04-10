<?php

namespace App\Services\Auth;

use App\Enums\AuthEventType;
use App\Enums\ProjectEmailTemplateType;
use App\Enums\ProjectOtpPurpose;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use App\Models\ProjectUser;
use App\Services\ProjectUserFields\SaveProjectUserFieldValues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectAuthService
{
    public function __construct(
        private readonly AuthEventLogger $authEventLogger,
        private readonly ProjectMailService $projectMailService,
        private readonly ProjectOtpService $projectOtpService,
        private readonly ProjectTokenService $projectTokenService,
        private readonly SaveProjectUserFieldValues $saveProjectUserFieldValues,
    ) {}

    /**
     * Register a new project user.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function register(Project $project, array $attributes, Request $request): array
    {
        $settings = $project->authSettings ?? $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());
        $customFieldPayload = is_array($attributes['custom_fields'] ?? null) ? $attributes['custom_fields'] : [];

        $registration = DB::transaction(function () use ($project, $settings, $attributes, $customFieldPayload): array {
            /** @var ProjectUser|null $existingUser */
            $existingUser = ProjectUser::query()
                ->whereBelongsTo($project)
                ->where('email', $attributes['email'])
                ->lockForUpdate()
                ->first();

            if ($existingUser instanceof ProjectUser && $existingUser->is_ghost) {
                return [
                    'status' => 'duplicate',
                    'project_user' => $existingUser,
                ];
            }

            if ($existingUser instanceof ProjectUser && ! $existingUser->isPendingEmailVerification()) {
                return [
                    'status' => 'verified_conflict',
                    'project_user' => $existingUser,
                ];
            }

            $projectUser = $existingUser instanceof ProjectUser
                ? $this->retryRegistration($existingUser, $attributes, $settings)
                : $this->createProjectUser($project, $attributes, $settings);

            $this->saveProjectUserFieldValues->save(
                $projectUser,
                $customFieldPayload,
                applyDefaults: ! ($existingUser instanceof ProjectUser),
            );

            if ($projectUser->isPendingEmailVerification()) {
                $this->projectTokenService->revokeAllTokensForUser($projectUser);
            }

            return [
                'mode' => $existingUser instanceof ProjectUser ? 'retried' : 'created',
                'project_user' => $projectUser->refresh(),
                'status' => 'registered',
            ];
        }, attempts: 5);

        /** @var ProjectUser $projectUser */
        $projectUser = $registration['project_user'];

        if (($registration['status'] ?? null) === 'duplicate') {
            throw ValidationException::withMessages([
                'email' => ['This email is already taken.'],
            ]);
        }

        if (($registration['status'] ?? null) === 'verified_conflict') {
            $this->authEventLogger->log(
                $project,
                AuthEventType::RegistrationFailed,
                false,
                $request,
                $projectUser,
                $attributes['email'],
                ['reason' => 'verified_email_collision'],
            );

            throw ValidationException::withMessages([
                'email' => ['This email is already taken.'],
            ]);
        }

        $verificationRequired = $projectUser->isPendingEmailVerification();

        if ($verificationRequired) {
            $this->projectOtpService->send(
                $project,
                $projectUser->email,
                ProjectOtpPurpose::EmailVerification,
                $request,
                $projectUser,
                ['mode' => $registration['mode']],
                ignoreCooldown: true,
            );

            $this->authEventLogger->log(
                $project,
                AuthEventType::VerificationSent,
                true,
                $request,
                $projectUser,
                $projectUser->email,
                [
                    'mode' => $registration['mode'],
                    'purpose' => ProjectOtpPurpose::EmailVerification->value,
                ],
            );
        } else {
            $this->projectMailService->queueTemplateEmail(
                $project,
                $projectUser->email,
                ProjectEmailTemplateType::Welcome,
                [
                    'user_email' => $projectUser->email,
                ],
            );
        }

        $this->authEventLogger->log(
            $project,
            AuthEventType::RegistrationSucceeded,
            true,
            $request,
            $projectUser,
            $projectUser->email,
            [
                'mode' => $registration['mode'],
                'verification_required' => $verificationRequired,
            ],
        );

        if ($verificationRequired) {
            return [
                'message' => 'Registration successful. Verify your email to continue.',
                'project_user' => $projectUser,
                'verification_purpose' => ProjectOtpPurpose::EmailVerification->value,
                'verification_required' => true,
            ];
        }

        return $this->projectTokenService->issueTokenPair(
            $projectUser,
            $this->resolveTokenName($request),
            $request,
        );
    }

    /**
     * Authenticate a project user.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function login(Project $project, array $attributes, Request $request): array
    {
        /** @var ProjectUser|null $projectUser */
        $projectUser = ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $attributes['email'])
            ->first();

        if (
            ! $projectUser instanceof ProjectUser
            || ! $projectUser->is_active
            || $projectUser->is_ghost
            || blank($projectUser->password)
            || ! Hash::check($attributes['password'], $projectUser->password)
        ) {
            $this->authEventLogger->log(
                $project,
                AuthEventType::LoginFailed,
                false,
                $request,
                null,
                $attributes['email'],
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($projectUser->isPendingEmailVerification()) {
            $this->authEventLogger->log(
                $project,
                AuthEventType::LoginFailed,
                false,
                $request,
                $projectUser,
                $projectUser->email,
                ['reason' => 'email_verification_required'],
            );

            throw ValidationException::withMessages([
                'email' => ['Email verification is required before signing in.'],
            ]);
        }

        $projectUser->forceFill([
            'last_login_at' => now(),
        ])->save();

        $this->authEventLogger->log(
            $project,
            AuthEventType::LoginSucceeded,
            true,
            $request,
            $projectUser,
            $projectUser->email,
        );

        return $this->projectTokenService->issueTokenPair(
            $projectUser,
            $this->resolveTokenName($request),
            $request,
        );
    }

    /**
     * Resolve a deterministic token name from the request metadata.
     */
    private function resolveTokenName(Request $request): string
    {
        return Str::limit((string) ($request->userAgent() ?: 'project-api-client'), 255, '');
    }

    /**
     * Revoke the authenticated project user's tokens.
     */
    public function logout(ProjectUser $projectUser, Request $request): void
    {
        $currentAccessToken = $projectUser->currentAccessToken();

        if ($currentAccessToken !== null) {
            $projectUser->tokens()->whereKey($currentAccessToken->getKey())->delete();
        }

        $projectUser->refreshTokens()
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        $this->authEventLogger->log(
            $projectUser->project,
            AuthEventType::LogoutSucceeded,
            true,
            $request,
            $projectUser,
            $projectUser->email,
        );
    }

    /**
     * Activate a pending project user after email verification.
     */
    public function completeEmailVerification(ProjectUser $projectUser, Request $request): void
    {
        $projectUser->loadMissing('project');

        if (! $projectUser->isPendingEmailVerification()) {
            return;
        }

        $projectUser->forceFill([
            'email_verified_at' => now(),
            'must_verify_email' => false,
        ])->save();

        $this->projectMailService->queueTemplateEmail(
            $projectUser->project,
            $projectUser->email,
            ProjectEmailTemplateType::Welcome,
            [
                'user_email' => $projectUser->email,
            ],
        );

        $this->authEventLogger->log(
            $projectUser->project,
            AuthEventType::VerificationCompleted,
            true,
            $request,
            $projectUser,
            $projectUser->email,
            ['purpose' => ProjectOtpPurpose::EmailVerification->value],
        );
    }

    /**
     * Create a brand-new project user from a registration request.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createProjectUser(Project $project, array $attributes, ProjectAuthSetting $settings): ProjectUser
    {
        return $project->projectUsers()->create([
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'email_verified_at' => $settings->email_verification_enabled ? null : now(),
            'is_active' => true,
            'is_ghost' => false,
            'must_verify_email' => $settings->email_verification_enabled,
        ]);
    }

    /**
     * Retry registration against an existing pending project user.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function retryRegistration(ProjectUser $projectUser, array $attributes, ProjectAuthSetting $settings): ProjectUser
    {
        $updates = [
            'password' => $attributes['password'],
            'email_verified_at' => $settings->email_verification_enabled ? null : now(),
            'is_active' => true,
            'is_ghost' => false,
            'must_verify_email' => $settings->email_verification_enabled,
        ];

        $projectUser->fill($updates)->save();

        return $projectUser;
    }
}
