<?php

use App\Enums\AuthEventType;
use App\Enums\ProjectEmailTemplateType;
use App\Enums\ProjectOtpPurpose;
use App\Jobs\SendProjectEmailJob;
use App\Models\ApiRequestLog;
use App\Models\AuthEventLog;
use App\Models\Project;
use App\Models\ProjectOtp;
use App\Models\ProjectUser;
use App\Models\RefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function projectHeaders(Project $project, ?string $token = null): array
{
    return array_filter([
        'X-Project-Key' => $project->api_key,
        'Authorization' => $token === null ? null : 'Bearer '.$token,
    ]);
}

it('rejects missing and invalid project keys', function () {
    $payload = [
        'email' => 'project-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertBadRequest()
        ->assertJson([
            'message' => 'The X-Project-Key header is required.',
        ]);

    $this->postJson('/api/v1/auth/register', $payload, [
        'X-Project-Key' => 'invalid-project-key',
    ])->assertUnauthorized()
        ->assertJson([
            'message' => 'The provided project key is invalid.',
        ]);
});

it('registers, authenticates, returns the current user, and logs out a project user', function () {
    $project = Project::factory()->create();
    $password = 'password';

    $registerResponse = $this->postJson('/api/v1/auth/register', [
        'email' => 'new-user@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ], projectHeaders($project));

    $registerResponse->assertCreated()
        ->assertJsonPath('data.user.email', 'new-user@example.com')
        ->assertJsonPath('data.user.project_id', $project->id)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.is_ghost', false);

    $token = $registerResponse->json('data.access_token');
    $refreshToken = $registerResponse->json('data.refresh_token');
    [$personalAccessTokenId] = explode('|', $token, 2);

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($refreshToken)->toBeString()->not->toBeEmpty()
        ->and($registerResponse->json('data.expires_at'))->not->toBeNull()
        ->and($registerResponse->json('data.refresh_token_expires_at'))->not->toBeNull();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertOk()
        ->assertJsonPath('data.email', 'new-user@example.com');

    $this->postJson('/api/v1/auth/logout', [], projectHeaders($project, $token))
        ->assertOk()
        ->assertJsonPath('data.message', 'Logged out successfully.');

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/register',
        'method' => 'POST',
    ]);

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/me',
        'method' => 'GET',
    ]);

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/logout',
        'method' => 'POST',
    ]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => (int) $personalAccessTokenId,
    ]);
    $this->assertDatabaseMissing('refresh_tokens', [
        'project_user_id' => ProjectUser::query()->where('email', 'new-user@example.com')->value('id'),
        'revoked_at' => null,
    ]);

    Auth::forgetGuards();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertUnauthorized();
});

it('allows the same email to register in different projects', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $payload = [
        'email' => 'shared@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->postJson('/api/v1/auth/register', $payload, projectHeaders($project))
        ->assertCreated()
        ->assertJsonPath('data.user.project_id', $project->id);

    $this->postJson('/api/v1/auth/register', $payload, projectHeaders($otherProject))
        ->assertCreated()
        ->assertJsonPath('data.user.project_id', $otherProject->id);

    expect(ProjectUser::query()->where('email', 'shared@example.com')->count())->toBe(2);
});

it('returns a pending registration response when email verification is enabled', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'email_verification_enabled' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'pending@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], projectHeaders($project));

    $response->assertAccepted()
        ->assertJsonPath('data.message', 'Registration successful. Verify your email to continue.')
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonPath('data.verification_purpose', ProjectOtpPurpose::EmailVerification->value)
        ->assertJsonPath('data.user.email', 'pending@example.com')
        ->assertJsonPath('data.user.must_verify_email', true)
        ->assertJsonPath('data.user.email_verified_at', null)
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonMissingPath('data.refresh_token');

    $projectUser = ProjectUser::query()
        ->whereBelongsTo($project)
        ->where('email', 'pending@example.com')
        ->firstOrFail();

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use ($project): bool {
        return $job->projectId === $project->id
            && $job->templateType === ProjectEmailTemplateType::EmailVerification->value
            && $job->recipient === 'pending@example.com';
    });

    Queue::assertNotPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job): bool {
        return $job->templateType === ProjectEmailTemplateType::Welcome->value
            && $job->recipient === 'pending@example.com';
    });

    expect($projectUser->isPendingEmailVerification())->toBeTrue()
        ->and(AuthEventLog::query()->whereBelongsTo($project)->where('event_type', AuthEventType::VerificationSent)->exists())->toBeTrue();
});

it('retries a pending registration on the same project user record', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'email_verification_enabled' => true,
        'otp_resend_cooldown_seconds' => 300,
    ]);

    $projectUser = ProjectUser::factory()
        ->for($project)
        ->pendingEmailVerification()
        ->create([
            'email' => 'retry@example.com',
            'password' => 'old-password',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'phone' => '+15550000000',
        ]);

    $projectUser->createToken('legacy-pending-token');

    $projectUser->refreshTokens()->create([
        'project_id' => $project->id,
        'token_hash' => hash('sha256', 'legacy-refresh-token'),
        'expires_at' => now()->addDays(7),
        'user_agent' => 'Pest',
        'ip_address' => '127.0.0.1',
    ]);

    $previousOtp = ProjectOtp::query()->create([
        'project_id' => $project->id,
        'project_user_id' => $projectUser->id,
        'email' => $projectUser->email,
        'purpose' => ProjectOtpPurpose::EmailVerification,
        'code_hash' => Hash::make('111111'),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
        'max_attempts' => 5,
        'resend_count' => 0,
        'last_sent_at' => now(),
        'meta' => [],
    ]);

    $otpCode = null;

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'retry@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        'first_name' => 'Updated',
        'phone' => null,
    ], projectHeaders($project));

    $response->assertAccepted()
        ->assertJsonPath('data.user.id', $projectUser->id)
        ->assertJsonPath('data.user.first_name', 'Updated')
        ->assertJsonPath('data.user.last_name', 'User')
        ->assertJsonPath('data.user.phone', null)
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonMissingPath('data.access_token');

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use (&$otpCode, $project): bool {
        if (
            $job->projectId !== $project->id
            || $job->templateType !== ProjectEmailTemplateType::EmailVerification->value
            || $job->recipient !== 'retry@example.com'
        ) {
            return false;
        }

        $otpCode = $job->variables['otp_code'] ?? null;

        return filled($otpCode);
    });

    expect(ProjectUser::query()->whereBelongsTo($project)->where('email', 'retry@example.com')->count())->toBe(1)
        ->and(Hash::check('new-password', $projectUser->refresh()->password))->toBeTrue()
        ->and($projectUser->first_name)->toBe('Updated')
        ->and($projectUser->last_name)->toBe('User')
        ->and($projectUser->phone)->toBeNull()
        ->and(PersonalAccessToken::query()
            ->where('tokenable_type', ProjectUser::class)
            ->where('tokenable_id', $projectUser->id)
            ->count())->toBe(0)
        ->and(RefreshToken::query()->where('project_user_id', $projectUser->id)->whereNull('revoked_at')->count())->toBe(0);

    expect(Hash::check((string) $otpCode, $previousOtp->refresh()->code_hash))->toBeTrue()
        ->and(Hash::check('111111', $previousOtp->code_hash))->toBeFalse();
});

it('rejects registering an already verified project user and logs the failure', function () {
    $project = Project::factory()->create();

    $projectUser = ProjectUser::factory()->for($project)->create([
        'email' => 'verified@example.com',
        'first_name' => 'Existing',
    ]);

    $this->postJson('/api/v1/auth/register', [
        'email' => 'verified@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'This email is already taken.');

    expect(ProjectUser::query()->whereBelongsTo($project)->where('email', 'verified@example.com')->count())->toBe(1)
        ->and($projectUser->refresh()->first_name)->toBe('Existing')
        ->and(AuthEventLog::query()
            ->whereBelongsTo($project)
            ->where('event_type', AuthEventType::RegistrationFailed)
            ->where('email', 'verified@example.com')
            ->exists())->toBeTrue();
});

it('issues tokens when retrying a pending registration after email verification is disabled', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'email_verification_enabled' => false,
    ]);

    $projectUser = ProjectUser::factory()
        ->for($project)
        ->pendingEmailVerification()
        ->create([
            'email' => 'activate-on-retry@example.com',
            'password' => 'old-password',
        ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'activate-on-retry@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], projectHeaders($project));

    $response->assertCreated()
        ->assertJsonPath('data.user.id', $projectUser->id)
        ->assertJsonPath('data.user.must_verify_email', false)
        ->assertJsonPath('data.user.email', 'activate-on-retry@example.com')
        ->assertJsonPath('data.token_type', 'Bearer');

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use ($project): bool {
        return $job->projectId === $project->id
            && $job->templateType === ProjectEmailTemplateType::Welcome->value
            && $job->recipient === 'activate-on-retry@example.com';
    });

    Queue::assertNotPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job): bool {
        return $job->templateType === ProjectEmailTemplateType::EmailVerification->value
            && $job->recipient === 'activate-on-retry@example.com';
    });

    expect($projectUser->refresh()->must_verify_email)->toBeFalse()
        ->and($projectUser->email_verified_at)->not->toBeNull();
});

it('authenticates login requests within the resolved project', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    ProjectUser::factory()->for($project)->create([
        'email' => 'shared@example.com',
        'password' => 'password',
    ]);

    ProjectUser::factory()->for($otherProject)->create([
        'email' => 'shared@example.com',
        'password' => 'different-password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'shared@example.com',
        'password' => 'password',
    ], projectHeaders($project))
        ->assertOk()
        ->assertJsonPath('data.user.project_id', $project->id)
        ->assertJsonPath('data.user.email', 'shared@example.com');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'shared@example.com',
        'password' => 'password',
    ], projectHeaders($otherProject))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('blocks pending users until email verification is completed', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'email_verification_enabled' => true,
    ]);

    $this->postJson('/api/v1/auth/register', [
        'email' => 'verify-me@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], projectHeaders($project))
        ->assertAccepted();

    $projectUser = ProjectUser::query()
        ->whereBelongsTo($project)
        ->where('email', 'verify-me@example.com')
        ->firstOrFail();

    $otpCode = null;

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use (&$otpCode, $project): bool {
        if (
            $job->projectId !== $project->id
            || $job->templateType !== ProjectEmailTemplateType::EmailVerification->value
            || $job->recipient !== 'verify-me@example.com'
        ) {
            return false;
        }

        $otpCode = $job->variables['otp_code'] ?? null;

        return filled($otpCode);
    });

    $legacyAccessToken = $projectUser->createToken('legacy-pending-access')->plainTextToken;
    $legacyRefreshToken = 'legacy-pending-refresh-token';

    $projectUser->refreshTokens()->create([
        'project_id' => $project->id,
        'token_hash' => hash('sha256', $legacyRefreshToken),
        'expires_at' => now()->addDays(7),
        'user_agent' => 'Pest',
        'ip_address' => '127.0.0.1',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'verify-me@example.com',
        'password' => 'password',
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $legacyAccessToken))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Email verification is required before accessing this resource.',
        ]);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $legacyRefreshToken,
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['refresh_token']);

    expect(PersonalAccessToken::query()
        ->where('tokenable_type', ProjectUser::class)
        ->where('tokenable_id', $projectUser->id)
        ->count())->toBe(0)
        ->and(RefreshToken::query()->where('project_user_id', $projectUser->id)->whereNull('revoked_at')->count())->toBe(0);

    $this->postJson('/api/v1/auth/verify-otp', [
        'email' => 'verify-me@example.com',
        'purpose' => ProjectOtpPurpose::EmailVerification->value,
        'otp_code' => $otpCode,
    ], projectHeaders($project))
        ->assertOk()
        ->assertJsonPath('data.verified', true);

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use ($project): bool {
        return $job->projectId === $project->id
            && $job->templateType === ProjectEmailTemplateType::Welcome->value
            && $job->recipient === 'verify-me@example.com';
    });

    expect($projectUser->refresh()->must_verify_email)->toBeFalse()
        ->and($projectUser->email_verified_at)->not->toBeNull()
        ->and(AuthEventLog::query()->whereBelongsTo($project)->where('event_type', AuthEventType::VerificationCompleted)->exists())->toBeTrue();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'verify-me@example.com',
        'password' => 'password',
    ], projectHeaders($project))
        ->assertOk()
        ->assertJsonPath('data.user.email', 'verify-me@example.com');
});

it('forbids using a token from one project against another project', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $projectUser = ProjectUser::factory()->for($project)->create();
    $token = $projectUser->createToken('cross-project-test')->plainTextToken;

    $this->getJson('/api/v1/auth/me', projectHeaders($otherProject, $token))
        ->assertForbidden()
        ->assertJson([
            'message' => 'This token does not belong to the requested project.',
        ]);
});

it('keys rate limiting by project and endpoint', function () {
    config()->set('cache.default', 'file');
    Cache::store('file')->flush();

    $project = Project::factory()->create([
        'rate_limit' => 1,
    ]);
    $otherProject = Project::factory()->create([
        'rate_limit' => 1,
    ]);
    $projectUser = ProjectUser::factory()->for($project)->create();
    $otherProjectUser = ProjectUser::factory()->for($otherProject)->create();
    $token = $projectUser->createToken('project-a')->plainTextToken;
    $otherToken = $otherProjectUser->createToken('project-b')->plainTextToken;

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertOk();

    Auth::forgetGuards();

    $this->postJson('/api/v1/auth/logout', [], projectHeaders($project, $token))
        ->assertOk();

    Auth::forgetGuards();

    $this->getJson('/api/v1/auth/me', projectHeaders($otherProject, $otherToken))
        ->assertOk();
});

it('applies the per-project rate limit and still logs the throttled request', function () {
    config()->set('cache.default', 'file');
    Cache::store('file')->flush();

    $project = Project::factory()->create([
        'rate_limit' => 1,
    ]);
    $projectUser = ProjectUser::factory()->for($project)->create();
    $token = $projectUser->createToken('rate-limit-test')->plainTextToken;

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertOk();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertTooManyRequests()
        ->assertJson([
            'message' => 'Too many requests.',
        ]);

    expect(ApiRequestLog::query()->where('project_id', $project->id)->count())->toBe(2);
});

it('expires project user tokens and records project scoped api logs', function () {
    $project = Project::factory()->create();
    $project->authSettings()->update([
        'access_token_ttl_minutes' => 1,
    ]);
    ProjectUser::factory()->for($project)->create([
        'email' => 'expiring@example.com',
        'password' => 'password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'expiring@example.com',
        'password' => 'password',
    ], projectHeaders($project));

    $loginResponse->assertOk()
        ->assertJsonPath('data.user.email', 'expiring@example.com');

    $token = $loginResponse->json('data.access_token');
    [$personalAccessTokenId] = explode('|', $token, 2);

    expect(PersonalAccessToken::query()->find((int) $personalAccessTokenId)?->expires_at)->not->toBeNull();

    $this->assertDatabaseHas('api_request_logs', [
        'project_id' => $project->id,
        'endpoint' => '/api/v1/auth/login',
        'method' => 'POST',
    ]);

    $this->travel(2)->minutes();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $token))
        ->assertUnauthorized();
});

it('rotates refresh tokens and rejects refresh token reuse', function () {
    $project = Project::factory()->create();
    ProjectUser::factory()->for($project)->create([
        'email' => 'refresh@example.com',
        'password' => 'password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'refresh@example.com',
        'password' => 'password',
    ], projectHeaders($project));

    $initialRefreshToken = $loginResponse->json('data.refresh_token');

    $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $initialRefreshToken,
    ], projectHeaders($project));

    $refreshResponse->assertOk()
        ->assertJsonPath('data.user.email', 'refresh@example.com');

    expect($refreshResponse->json('data.refresh_token'))
        ->not->toBe($initialRefreshToken);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $initialRefreshToken,
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['refresh_token']);

    expect(RefreshToken::query()->whereBelongsTo($project)->count())->toBe(2)
        ->and(RefreshToken::query()->whereBelongsTo($project)->whereNull('revoked_at')->count())->toBe(0);
});

it('creates password reset records and completes a password reset within the current project', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $projectUser = ProjectUser::factory()->for($project)->create([
        'email' => 'reset@example.com',
        'password' => 'password',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'reset@example.com',
        'password' => 'password',
    ], projectHeaders($project));

    $oldAccessToken = $loginResponse->json('data.access_token');

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'reset@example.com',
    ], projectHeaders($project))
        ->assertAccepted()
        ->assertJsonPath('data.message', 'If the request can be processed, an email will be sent.');

    $resetLink = null;

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use (&$resetLink, $project): bool {
        if ($job->projectId !== $project->id || ! isset($job->variables['reset_link'])) {
            return false;
        }

        $resetLink = $job->variables['reset_link'];

        return true;
    });

    expect($resetLink)->toBeString();

    parse_str((string) parse_url((string) $resetLink, PHP_URL_QUERY), $query);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $query['token'],
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], projectHeaders($project))
        ->assertOk()
        ->assertJsonPath('data.message', 'Password reset successfully.');

    Auth::forgetGuards();

    $this->getJson('/api/v1/auth/me', projectHeaders($project, $oldAccessToken))
        ->assertUnauthorized();

    expect($projectUser->refresh()->password)->not->toBe('password')
        ->and(AuthEventLog::query()->whereBelongsTo($project)->where('event_type', AuthEventType::PasswordResetCompleted)->exists())->toBeTrue();
});

it('sends and verifies an otp within the current project', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $projectUser = ProjectUser::factory()->for($project)->create([
        'email' => 'otp@example.com',
    ]);

    $this->postJson('/api/v1/auth/send-otp', [
        'email' => 'otp@example.com',
        'purpose' => ProjectOtpPurpose::LoginVerify->value,
    ], projectHeaders($project))
        ->assertAccepted();

    $otpCode = null;

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use (&$otpCode, $project): bool {
        if ($job->projectId !== $project->id || ! isset($job->variables['otp_code'])) {
            return false;
        }

        $otpCode = $job->variables['otp_code'];

        return true;
    });

    expect($otpCode)->toBeString();

    $this->postJson('/api/v1/auth/verify-otp', [
        'email' => 'otp@example.com',
        'purpose' => ProjectOtpPurpose::LoginVerify->value,
        'otp_code' => $otpCode,
    ], projectHeaders($project))
        ->assertOk()
        ->assertJsonPath('data.verified', true);

    expect(AuthEventLog::query()->whereBelongsTo($project)->where('event_type', AuthEventType::OtpVerified)->exists())->toBeTrue()
        ->and($projectUser->refresh()->email)->toBe('otp@example.com');
});

it('rejects expired otp codes', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'otp_ttl_minutes' => 1,
    ]);

    ProjectUser::factory()->for($project)->create([
        'email' => 'expired-otp@example.com',
    ]);

    $this->postJson('/api/v1/auth/send-otp', [
        'email' => 'expired-otp@example.com',
        'purpose' => ProjectOtpPurpose::LoginVerify->value,
    ], projectHeaders($project))
        ->assertAccepted();

    $otpCode = null;

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use (&$otpCode): bool {
        $otpCode = $job->variables['otp_code'] ?? null;

        return $otpCode !== null;
    });

    $this->travel(2)->minutes();

    $this->postJson('/api/v1/auth/verify-otp', [
        'email' => 'expired-otp@example.com',
        'purpose' => ProjectOtpPurpose::LoginVerify->value,
        'otp_code' => $otpCode,
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['otp_code']);
});

it('enforces otp resend cooldowns', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'otp_resend_cooldown_seconds' => 300,
    ]);

    ProjectUser::factory()->for($project)->create([
        'email' => 'cooldown@example.com',
    ]);

    $this->postJson('/api/v1/auth/send-otp', [
        'email' => 'cooldown@example.com',
        'purpose' => ProjectOtpPurpose::LoginVerify->value,
    ], projectHeaders($project))
        ->assertAccepted();

    $this->postJson('/api/v1/auth/resend-otp', [
        'email' => 'cooldown@example.com',
        'purpose' => ProjectOtpPurpose::LoginVerify->value,
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['otp']);
});

it('creates and claims ghost accounts inside the current project', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'ghost_accounts_enabled' => true,
    ]);

    $createResponse = $this->postJson('/api/v1/auth/ghost-accounts', [
        'email' => 'ghost@example.com',
        'first_name' => 'Ghost',
        'send_invite' => true,
    ], projectHeaders($project));

    $createResponse->assertCreated()
        ->assertJsonPath('data.email', 'ghost@example.com')
        ->assertJsonPath('data.is_ghost', true);

    $otpCode = null;

    Queue::assertPushed(SendProjectEmailJob::class, function (SendProjectEmailJob $job) use (&$otpCode): bool {
        $otpCode = $job->variables['otp_code'] ?? null;

        return $otpCode !== null;
    });

    $claimResponse = $this->postJson('/api/v1/auth/ghost-accounts/claim', [
        'email' => 'ghost@example.com',
        'otp_code' => $otpCode,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], projectHeaders($project));

    $claimResponse->assertOk()
        ->assertJsonPath('data.user.email', 'ghost@example.com')
        ->assertJsonPath('data.user.is_ghost', false);

    expect(ProjectUser::query()->whereBelongsTo($project)->where('email', 'ghost@example.com')->firstOrFail()->claimed_at)->not->toBeNull();
});

it('rejects ghost account creation and claiming when ghost accounts are disabled', function () {
    $project = Project::factory()->create();

    ProjectUser::factory()->for($project)->create([
        'email' => 'ghost-disabled@example.com',
        'is_ghost' => true,
    ]);

    $this->postJson('/api/v1/auth/ghost-accounts', [
        'email' => 'ghost-disabled@example.com',
        'first_name' => 'Ghost',
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'Ghost accounts are disabled for this project.');

    $this->postJson('/api/v1/auth/ghost-accounts/claim', [
        'email' => 'ghost-disabled@example.com',
        'otp_code' => '123456',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], projectHeaders($project))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'Ghost accounts are disabled for this project.');
});

it('applies project-specific forgot password rate limits and logs auth events', function () {
    Queue::fake();
    config()->set('cache.default', 'file');
    Cache::store('file')->flush();

    $project = Project::factory()->create();
    $project->authSettings()->update([
        'forgot_password_requests_per_hour' => 1,
    ]);
    ProjectUser::factory()->for($project)->create([
        'email' => 'rate@example.com',
    ]);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'rate@example.com',
    ], projectHeaders($project))
        ->assertAccepted();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'rate@example.com',
    ], projectHeaders($project))
        ->assertTooManyRequests();

    expect(AuthEventLog::query()->whereBelongsTo($project)->where('event_type', AuthEventType::PasswordResetRequested)->exists())->toBeTrue();
});
