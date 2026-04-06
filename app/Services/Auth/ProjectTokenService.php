<?php

namespace App\Services\Auth;

use App\Enums\AuthEventType;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use App\Models\ProjectUser;
use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectTokenService
{
    public function __construct(
        private readonly AuthEventLogger $authEventLogger,
    ) {}

    /**
     * Issue a short-lived access token and long-lived refresh token pair.
     *
     * @return array<string, mixed>
     */
    public function issueTokenPair(ProjectUser $projectUser, string $deviceName, Request $request): array
    {
        $projectUser->loadMissing('project.authSettings');

        /** @var Project $project */
        $project = $projectUser->project;
        $settings = $project->authSettings ?? $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());

        $accessTokenExpiresAt = now()->addMinutes($settings->access_token_ttl_minutes);
        $accessToken = $projectUser->createToken($deviceName, ['*'], $accessTokenExpiresAt);

        $refreshTokenPlainText = Str::random(80);
        $refreshTokenExpiresAt = now()->addDays($settings->refresh_token_ttl_days);

        $refreshToken = $projectUser->refreshTokens()->create([
            'project_id' => $project->getKey(),
            'token_hash' => hash('sha256', $refreshTokenPlainText),
            'expires_at' => $refreshTokenExpiresAt,
            'user_agent' => Str::limit((string) ($request->userAgent() ?? 'project-api-client'), 1024, ''),
            'ip_address' => $request->ip(),
        ]);

        return [
            'expires_at' => $accessTokenExpiresAt,
            'plain_text_token' => $accessToken->plainTextToken,
            'project_user' => $projectUser,
            'refresh_token' => $refreshTokenPlainText,
            'refresh_token_expires_at' => $refreshToken->expires_at,
        ];
    }

    /**
     * Rotate a refresh token and issue a fresh access token pair.
     *
     * @return array<string, mixed>
     */
    public function rotateRefreshToken(Project $project, string $plainTextToken, string $deviceName, Request $request): array
    {
        $tokenHash = hash('sha256', $plainTextToken);

        /** @var RefreshToken|null $refreshToken */
        $refreshToken = RefreshToken::query()
            ->whereBelongsTo($project)
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $refreshToken instanceof RefreshToken) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid.'],
            ]);
        }

        if ($refreshToken->revoked_at !== null && $refreshToken->projectUser instanceof ProjectUser) {
            $this->revokeAllTokensForUser($refreshToken->projectUser);

            $this->authEventLogger->log(
                $project,
                AuthEventType::RefreshRotated,
                false,
                $request,
                $refreshToken->projectUser,
                $refreshToken->projectUser->email,
                ['reason' => 'refresh_token_reuse_detected'],
            );

            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid.'],
            ]);
        }

        if (! $refreshToken->isUsable()) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid.'],
            ]);
        }

        /** @var ProjectUser $projectUser */
        $projectUser = $refreshToken->projectUser()->firstOrFail();

        if ($projectUser->isPendingEmailVerification()) {
            $this->revokeAllTokensForUser($projectUser);

            $this->authEventLogger->log(
                $project,
                AuthEventType::RefreshRotated,
                false,
                $request,
                $projectUser,
                $projectUser->email,
                ['reason' => 'email_verification_required'],
            );

            throw ValidationException::withMessages([
                'refresh_token' => ['Email verification is required before refreshing tokens.'],
            ]);
        }

        return DB::transaction(function () use ($project, $projectUser, $refreshToken, $deviceName, $request): array {
            $payload = $this->issueTokenPair($projectUser, $deviceName, $request);

            /** @var string $newPlainTextRefreshToken */
            $newPlainTextRefreshToken = $payload['refresh_token'];

            /** @var RefreshToken $replacement */
            $replacement = RefreshToken::query()
                ->whereBelongsTo($project)
                ->whereBelongsTo($projectUser)
                ->where('token_hash', hash('sha256', $newPlainTextRefreshToken))
                ->firstOrFail();

            $refreshToken->forceFill([
                'revoked_at' => now(),
                'last_used_at' => now(),
                'replaced_by_token_id' => $replacement->getKey(),
            ])->save();

            $this->authEventLogger->log(
                $project,
                AuthEventType::RefreshRotated,
                true,
                $request,
                $projectUser,
                $projectUser->email,
            );

            return $payload;
        });
    }

    /**
     * Revoke every API token and refresh token for a project user.
     */
    public function revokeAllTokensForUser(ProjectUser $projectUser): void
    {
        $projectUser->tokens()->delete();

        $projectUser->refreshTokens()
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);
    }
}
