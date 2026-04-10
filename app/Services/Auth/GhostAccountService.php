<?php

namespace App\Services\Auth;

use App\Enums\AuthEventType;
use App\Enums\ProjectOtpPurpose;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use App\Models\ProjectOtp;
use App\Models\ProjectUser;
use App\Services\ProjectUserFields\SaveProjectUserFieldValues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GhostAccountService
{
    public function __construct(
        private readonly AuthEventLogger $authEventLogger,
        private readonly ProjectOtpService $projectOtpService,
        private readonly ProjectTokenService $projectTokenService,
        private readonly SaveProjectUserFieldValues $saveProjectUserFieldValues,
    ) {}

    /**
     * Create or update a ghost account inside a project.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Project $project, array $attributes, Request $request): ProjectUser
    {
        $settings = $project->authSettings ?? $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());
        $customFieldPayload = is_array($attributes['custom_fields'] ?? null) ? $attributes['custom_fields'] : [];

        if (! $settings->ghost_accounts_enabled) {
            throw ValidationException::withMessages([
                'email' => ['Ghost accounts are disabled for this project.'],
            ]);
        }

        /** @var ProjectUser|null $existingUser */
        $existingUser = ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $attributes['email'])
            ->first();

        if ($existingUser instanceof ProjectUser && ! $existingUser->is_ghost) {
            throw ValidationException::withMessages([
                'email' => ['An active account already exists for this email address.'],
            ]);
        }

        $ghostAccount = DB::transaction(function () use ($project, $existingUser, $attributes, $customFieldPayload): ProjectUser {
            $ghostAccount = $existingUser ?? new ProjectUser([
                'project_id' => $project->getKey(),
                'email' => $attributes['email'],
            ]);

            $ghostAccount->fill([
                'is_active' => true,
                'is_ghost' => true,
                'invited_at' => now(),
                'ghost_source' => $attributes['ghost_source'] ?? 'api',
                'must_set_password' => (bool) ($attributes['must_set_password'] ?? true),
                'must_verify_email' => (bool) ($attributes['must_verify_email'] ?? false),
            ]);

            $ghostAccount->save();

            $this->saveProjectUserFieldValues->save(
                $ghostAccount,
                $customFieldPayload,
                applyDefaults: ! ($existingUser instanceof ProjectUser),
            );

            return $ghostAccount->refresh();
        });

        if (($attributes['send_invite'] ?? true) === true) {
            $this->projectOtpService->send(
                $project,
                $ghostAccount->email,
                ProjectOtpPurpose::GhostAccountClaim,
                $request,
                $ghostAccount,
                ['ghost_account_id' => $ghostAccount->getKey()],
            );
        }

        $this->authEventLogger->log(
            $project,
            AuthEventType::GhostAccountCreated,
            true,
            $request,
            $ghostAccount,
            $ghostAccount->email,
        );

        return $ghostAccount;
    }

    /**
     * Claim a ghost account and turn it into a normal account.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function claim(Project $project, array $attributes, Request $request): array
    {
        $settings = $project->authSettings ?? $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());
        $customFieldPayload = is_array($attributes['custom_fields'] ?? null) ? $attributes['custom_fields'] : [];

        if (! $settings->ghost_accounts_enabled) {
            throw ValidationException::withMessages([
                'email' => ['Ghost accounts are disabled for this project.'],
            ]);
        }

        /** @var ProjectUser|null $ghostAccount */
        $ghostAccount = ProjectUser::query()
            ->whereBelongsTo($project)
            ->where('email', $attributes['email'])
            ->where('is_ghost', true)
            ->first();

        if (! $ghostAccount instanceof ProjectUser) {
            throw ValidationException::withMessages([
                'email' => ['The invited account could not be found.'],
            ]);
        }

        $verifiedOtp = $this->projectOtpService->verify(
            $project,
            $ghostAccount->email,
            $attributes['otp_code'],
            ProjectOtpPurpose::GhostAccountClaim,
            $request,
        );

        if (! $verifiedOtp instanceof ProjectOtp) {
            throw ValidationException::withMessages([
                'otp_code' => ['The one-time code is invalid or expired.'],
            ]);
        }

        if ($ghostAccount->must_set_password && blank($attributes['password'] ?? null)) {
            throw ValidationException::withMessages([
                'password' => ['A password is required to claim this account.'],
            ]);
        }

        $claimedAccount = DB::transaction(function () use ($ghostAccount, $attributes, $customFieldPayload): ProjectUser {
            $ghostAccount->fill([
                'password' => filled($attributes['password'] ?? null)
                    ? Hash::make((string) $attributes['password'])
                    : $ghostAccount->password,
                'is_ghost' => false,
                'claimed_at' => now(),
                'email_verified_at' => now(),
                'must_set_password' => false,
                'must_verify_email' => false,
            ]);

            $ghostAccount->save();

            $this->saveProjectUserFieldValues->save($ghostAccount, $customFieldPayload);

            return $ghostAccount->refresh();
        });

        $this->authEventLogger->log(
            $project,
            AuthEventType::GhostAccountClaimed,
            true,
            $request,
            $claimedAccount,
            $claimedAccount->email,
        );

        return $this->projectTokenService->issueTokenPair(
            $claimedAccount,
            Str::limit((string) ($request->userAgent() ?: 'project-api-client'), 255, ''),
            $request,
        );
    }
}
