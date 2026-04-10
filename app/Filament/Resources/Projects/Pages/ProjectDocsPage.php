<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectOtpPurpose;
use App\Enums\ProjectUserFieldType;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use App\Models\ProjectUserField;
use App\Services\ProjectUserFields\BuildProjectUserFieldDefinitions;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class ProjectDocsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function makeEditProjectAction(): Action
    {
        return Action::make('edit')
            ->label('Edit Project')
            ->icon(Heroicon::PencilSquare)
            ->url(ProjectResource::getUrl('edit', ['record' => $this->getRecord()]));
    }

    protected function makePageSwitchAction(string $page, string $label, Heroicon $icon): Action
    {
        return Action::make(Str::camel($page))
            ->label($label)
            ->icon($icon)
            ->url(ProjectResource::getUrl($page, ['record' => $this->getRecord()]));
    }

    public function getBaseApiUrl(): string
    {
        return Str::beforeLast(route('api.v1.auth.register'), '/register');
    }

    public function getAuthSettings(): ProjectAuthSetting
    {
        return once(function (): ProjectAuthSetting {
            $project = $this->getProject();

            return $project->authSettings
                ?? $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());
        });
    }

    public function getProject(): Project
    {
        /** @var Project $project */
        $project = $this->getRecord()
            ->loadMissing(['owner', 'authSettings', 'mailSettings'])
            ->loadCount(['projectUsers', 'apiRequestLogs', 'authEventLogs']);

        return $project;
    }

    /**
     * @return Collection<int, ProjectUserField>
     */
    public function getCustomFieldDefinitions(): Collection
    {
        return once(fn (): Collection => app(BuildProjectUserFieldDefinitions::class)->active($this->getProject()));
    }

    /**
     * @return Collection<int, ProjectUserField>
     */
    public function getApiVisibleCustomFieldDefinitions(): Collection
    {
        return $this->getCustomFieldDefinitions()
            ->filter(fn (ProjectUserField $field): bool => $field->show_in_api)
            ->values();
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string, copy_value: string}>
     */
    public function getQuickStartCredentials(): array
    {
        $authSettings = $this->getAuthSettings();

        return [
            [
                'label' => 'Base API URL',
                'value' => $this->getBaseApiUrl(),
                'hint' => 'Prefix every project-scoped auth request with this base URL.',
                'copy_value' => $this->getBaseApiUrl(),
            ],
            [
                'label' => 'X-Project-Key',
                'value' => $this->getProject()->api_key,
                'hint' => 'Send this header on every request so the API resolves the correct project.',
                'copy_value' => $this->getProject()->api_key,
            ],
            [
                'label' => 'Authorization Header',
                'value' => 'Bearer <plain-text-token>',
                'hint' => 'Use the access token returned by register, login, or refresh.',
                'copy_value' => 'Bearer <plain-text-token>',
            ],
            [
                'label' => 'Access Token TTL',
                'value' => "{$authSettings->access_token_ttl_minutes} minutes",
                'hint' => "Refresh tokens stay valid for {$authSettings->refresh_token_ttl_days} days.",
                'copy_value' => "{$authSettings->access_token_ttl_minutes} minutes",
            ],
        ];
    }

    public function getPrimaryIntegrationNote(): string
    {
        if ($this->getAuthSettings()->email_verification_enabled) {
            return 'Email verification is enabled, so a new registration returns a pending verification payload before access tokens are issued.';
        }

        return 'Email verification is disabled, so register can return tokens immediately and your first `/me` check can succeed without an extra verification step.';
    }

    /**
     * @return array<int, array{title: string, description: string}>
     */
    public function getQuickStartChecklist(): array
    {
        $authSettings = $this->getAuthSettings();

        return [
            [
                'title' => 'Attach project credentials',
                'description' => 'Send `Accept: application/json`, `Content-Type: application/json`, and `X-Project-Key` on every project-scoped auth request.',
            ],
            [
                'title' => 'Get an access token',
                'description' => "Use `POST /login` for the fastest smoke test, or `POST /register` when you are creating a new user. Access tokens last {$authSettings->access_token_ttl_minutes} minutes.",
            ],
            [
                'title' => 'Confirm the session with `/me`',
                'description' => 'Pass the returned access token as `Authorization: Bearer <token>` on `GET /me` to verify the integration is live.',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, description: string, code: string}>
     */
    public function getFirstSuccessfulFlowExamples(): array
    {
        return [
            [
                'label' => 'curl',
                'description' => 'Fastest way to smoke-test the API from a terminal.',
                'code' => $this->renderExampleTemplate($this->getLoginFlowCurlExample()),
            ],
            [
                'label' => 'JavaScript fetch',
                'description' => 'Good default for browser apps, server actions, and simple SDK wrappers.',
                'code' => $this->renderExampleTemplate($this->getLoginFlowFetchExample()),
            ],
            [
                'label' => 'Laravel HTTP',
                'description' => 'Fits queue jobs, controller actions, and backend service calls.',
                'code' => $this->renderExampleTemplate($this->getLoginFlowLaravelExample()),
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, enabled: bool, impact: string, summary: string, endpoints: array<int, string>}>
     */
    public function getProjectBehaviorCards(): array
    {
        $authSettings = $this->getAuthSettings();

        return [
            [
                'title' => 'Email Verification',
                'enabled' => $authSettings->email_verification_enabled,
                'summary' => $authSettings->email_verification_enabled
                    ? 'New registrations must verify before the user receives tokens.'
                    : 'Register can issue tokens immediately after the user is created.',
                'impact' => $authSettings->email_verification_enabled
                    ? 'Expect a pending verification payload from register until the user confirms their email.'
                    : 'The fastest first success path is register, store the returned tokens, and call `/me`.',
                'endpoints' => ['register', 'resend-otp', 'verify-otp'],
            ],
            [
                'title' => 'OTP',
                'enabled' => $authSettings->otp_enabled,
                'summary' => $authSettings->otp_enabled
                    ? "One-time passwords are enabled with {$authSettings->otp_length} digits and a {$authSettings->otp_ttl_minutes}-minute TTL."
                    : 'OTP delivery and verification endpoints reject requests until the feature is enabled.',
                'impact' => $authSettings->otp_enabled
                    ? "Clients should handle resend cooldowns and an {$authSettings->otp_max_attempts}-attempt verification limit."
                    : 'Skip OTP-only flows in your app until the project turns this feature on.',
                'endpoints' => ['send-otp', 'resend-otp', 'verify-otp'],
            ],
            [
                'title' => 'Forgot Password',
                'enabled' => $authSettings->forgot_password_enabled,
                'summary' => $authSettings->forgot_password_enabled
                    ? "Password reset links are valid for {$authSettings->reset_password_ttl_minutes} minutes."
                    : 'Forgot-password requests and password resets are both disabled right now.',
                'impact' => $authSettings->forgot_password_enabled
                    ? "Plan for up to {$authSettings->forgot_password_requests_per_hour} reset requests per hour per email address."
                    : 'Hide password-recovery entry points in your client until this feature is enabled.',
                'endpoints' => ['forgot-password', 'reset-password'],
            ],
            [
                'title' => 'Ghost Accounts',
                'enabled' => $authSettings->ghost_accounts_enabled,
                'summary' => $authSettings->ghost_accounts_enabled
                    ? 'Invite-first account creation is enabled for this project.'
                    : 'Ghost account creation and claiming are disabled right now.',
                'impact' => $authSettings->ghost_accounts_enabled
                    ? 'Ghost account payloads can accept `custom_fields`, but responses still expose only fields marked `Show In API`.'
                    : 'Do not surface ghost-account invitation or claim flows in the client yet.',
                'endpoints' => ['ghost-accounts.store', 'ghost-accounts.claim'],
            ],
            [
                'title' => 'Refresh Tokens',
                'enabled' => true,
                'summary' => "Refresh tokens are always part of the auth contract and stay valid for {$authSettings->refresh_token_ttl_days} days.",
                'impact' => 'Your client should store refresh tokens securely and call `POST /refresh` before short-lived access tokens expire.',
                'endpoints' => ['login', 'register', 'refresh', 'logout'],
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, detail: string, fix: string}>
     */
    public function getCommonErrorScenarios(): array
    {
        $authSettings = $this->getAuthSettings();

        return [
            [
                'title' => 'Missing `X-Project-Key`',
                'detail' => 'The API cannot resolve a project context without the `X-Project-Key` header.',
                'fix' => 'Send the exact project API key from this page on every request, including `/me`, `/refresh`, and `/logout`.',
            ],
            [
                'title' => 'Invalid project key after rotation',
                'detail' => 'Project keys change immediately when they are rotated in the admin panel.',
                'fix' => 'Update every client and environment variable that stores the old key before retrying requests.',
            ],
            [
                'title' => 'Expired access token',
                'detail' => "Access tokens last {$authSettings->access_token_ttl_minutes} minutes, so long-lived browser sessions must refresh or re-authenticate.",
                'fix' => 'Use `POST /refresh` with the stored refresh token, or send the user through login again if refresh has expired.',
            ],
            [
                'title' => 'Feature disabled for this project',
                'detail' => 'OTP, forgot-password, and ghost-account routes respect the project feature toggles shown above.',
                'fix' => 'Check the project auth settings before enabling UI flows that depend on those endpoints.',
            ],
            [
                'title' => '`custom_fields` validation failed',
                'detail' => 'Only configured field keys are accepted, and each field follows the project user schema rules.',
                'fix' => 'Match the exact field key, value type, and validation requirements shown in the Custom Fields Summary below.',
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required: bool, unique: bool, show_in_api: bool, show_in_admin_form: bool, default_value: string, example_value: string, rules: string, notes: string}>
     */
    public function getCustomFieldRows(): array
    {
        return $this->getCustomFieldDefinitions()
            ->map(function (ProjectUserField $field): array {
                $notes = array_filter([
                    $field->description,
                    Arr::get($field->ui_settings ?? [], 'help_text'),
                ]);

                return [
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'required' => $field->is_required && $field->default_value === null,
                    'unique' => $field->is_unique,
                    'show_in_api' => $field->show_in_api,
                    'show_in_admin_form' => $field->show_in_admin_form,
                    'default_value' => $this->stringifyValue($field->default_value),
                    'example_value' => $this->stringifyValue($this->exampleValueForField($field)),
                    'rules' => $this->fieldRulesSummary($field),
                    'notes' => $notes === [] ? 'No extra notes.' : implode(' ', $notes),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getOtpPurposes(): array
    {
        return array_map(
            static fn (ProjectOtpPurpose $purpose): string => $purpose->value,
            ProjectOtpPurpose::cases(),
        );
    }

    public function getCanonicalRequestExample(): string
    {
        return $this->encodeJson($this->buildRegistrationPayload());
    }

    public function getCanonicalResponseExample(): string
    {
        return $this->encodeJson([
            'data' => $this->buildUserPayload([
                'last_login_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return array<int, array{label: string, method: string, path: string, purpose: string, request: string, response: string, note: ?string, failures: array<int, string>}>
     */
    public function getApiReferenceEndpoints(): array
    {
        $authSettings = $this->getAuthSettings();

        return [
            [
                'label' => 'Register',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.register'),
                'purpose' => 'Create a project user and, when email verification is disabled, issue tokens immediately.',
                'request' => $this->renderExampleTemplate($this->getRegisterRequestExample()),
                'response' => $this->getRegisterResponseExample(),
                'note' => $authSettings->email_verification_enabled
                    ? 'Email verification is enabled, so register responds with a pending verification payload instead of a token pair.'
                    : 'Register returns access and refresh tokens immediately when the user can sign in without extra verification.',
                'failures' => [
                    'Missing `X-Project-Key` header.',
                    'Validation errors in `email`, `password`, or `custom_fields`.',
                    'An email address that already exists inside the project.',
                ],
            ],
            [
                'label' => 'Login',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.login'),
                'purpose' => 'Exchange a valid project user email and password for a new access token pair.',
                'request' => $this->renderExampleTemplate($this->getLoginRequestExample()),
                'response' => $this->getLoginResponseExample(),
                'note' => 'Use this endpoint for the fastest smoke test when you already have a project user.',
                'failures' => [
                    'Invalid credentials.',
                    'Login blocked because the user still needs email verification.',
                    'Ghost accounts cannot sign in until they are claimed.',
                ],
            ],
            [
                'label' => 'Me',
                'method' => 'GET',
                'path' => $this->routePath('api.v1.auth.me'),
                'purpose' => 'Return the authenticated project user payload for the active access token.',
                'request' => $this->renderExampleTemplate($this->getMeRequestExample()),
                'response' => $this->getMeResponseExample(),
                'note' => 'This is the best endpoint to confirm your headers, token handling, and custom field serialization are all working.',
                'failures' => [
                    'Missing or malformed bearer token.',
                    'Expired access token.',
                    'Missing `X-Project-Key` header.',
                ],
            ],
            [
                'label' => 'Logout',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.logout'),
                'purpose' => 'Invalidate the current access token and end the active authenticated session.',
                'request' => $this->renderExampleTemplate($this->getLogoutRequestExample()),
                'response' => $this->getLogoutResponseExample(),
                'note' => 'Call this when a client explicitly signs the user out.',
                'failures' => [
                    'Missing bearer token.',
                    'Token already revoked or invalid.',
                    'Project key header missing from the request.',
                ],
            ],
            [
                'label' => 'Refresh',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.refresh'),
                'purpose' => 'Rotate a refresh token and issue a fresh access token pair.',
                'request' => $this->renderExampleTemplate($this->getRefreshRequestExample()),
                'response' => $this->getRefreshResponseExample(),
                'note' => 'Use this before the short-lived access token expires in browser or mobile sessions.',
                'failures' => [
                    'Invalid or reused refresh token.',
                    'Expired refresh token.',
                    'Refresh blocked because the user still needs email verification.',
                ],
            ],
            [
                'label' => 'Forgot Password',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.forgot-password'),
                'purpose' => 'Start the password reset flow for an existing project user.',
                'request' => $this->renderExampleTemplate($this->getForgotPasswordRequestExample()),
                'response' => $this->getAcceptedResponseExample(),
                'note' => $authSettings->forgot_password_enabled
                    ? 'The endpoint returns an accepted response even when the email address does not exist.'
                    : 'Forgot password is disabled, so requests will be rejected until the feature is enabled.',
                'failures' => [
                    'The project has forgot-password disabled.',
                    'Request-rate limits for password reset have been exceeded.',
                    'Malformed email payload.',
                ],
            ],
            [
                'label' => 'Reset Password',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.reset-password'),
                'purpose' => 'Finalize a password reset using the reset token from the email flow.',
                'request' => $this->renderExampleTemplate($this->getResetPasswordRequestExample()),
                'response' => $this->getResetPasswordResponseExample(),
                'note' => $authSettings->forgot_password_enabled
                    ? 'Successful resets return a confirmation payload so the client can continue with sign-in.'
                    : 'Reset password depends on forgot-password and will reject requests while that feature is disabled.',
                'failures' => [
                    'Invalid or expired password reset token.',
                    'Password confirmation mismatch.',
                    'Forgot-password support disabled for the project.',
                ],
            ],
            [
                'label' => 'Send OTP',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.send-otp'),
                'purpose' => 'Create and deliver a one-time password for the requested purpose.',
                'request' => $this->renderExampleTemplate($this->getSendOtpRequestExample()),
                'response' => $this->getAcceptedResponseExample(),
                'note' => $authSettings->otp_enabled
                    ? 'OTPs respect cooldowns, TTLs, and per-email daily limits from project auth settings.'
                    : 'OTP delivery is disabled, so requests fail until the feature is enabled.',
                'failures' => [
                    'OTP is disabled for the project.',
                    'The purpose is invalid for the flow.',
                    'Cooldown or daily OTP limits have been exceeded.',
                ],
            ],
            [
                'label' => 'Resend OTP',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.resend-otp'),
                'purpose' => 'Trigger another OTP for the same email and purpose after the cooldown period.',
                'request' => $this->renderExampleTemplate($this->getResendOtpRequestExample()),
                'response' => $this->getAcceptedResponseExample(),
                'note' => $authSettings->otp_enabled
                    ? 'Use this when the user never received or already exhausted the most recent code.'
                    : 'OTP resend is disabled because OTP support is turned off for this project.',
                'failures' => [
                    'OTP is disabled for the project.',
                    'Cooldown still active from the last OTP request.',
                    'The submitted email and purpose do not match a valid pending OTP flow.',
                ],
            ],
            [
                'label' => 'Verify OTP',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.verify-otp'),
                'purpose' => 'Validate the submitted OTP code for the given email and purpose.',
                'request' => $this->renderExampleTemplate($this->getVerifyOtpRequestExample()),
                'response' => $this->getVerifyOtpResponseExample(),
                'note' => $authSettings->otp_enabled
                    ? 'Clients should surface attempt counts and expiration handling because OTP verification is intentionally short-lived.'
                    : 'OTP verification is unavailable while the project has OTP disabled.',
                'failures' => [
                    'Wrong or expired OTP code.',
                    'Maximum verification attempts exceeded.',
                    'OTP support disabled for the project.',
                ],
            ],
            [
                'label' => 'Create Ghost Account',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.ghost-accounts.store'),
                'purpose' => 'Create or update an invite-first project user that can be claimed later.',
                'request' => $this->renderExampleTemplate($this->getCreateGhostAccountRequestExample()),
                'response' => $this->getCreateGhostAccountResponseExample(),
                'note' => $authSettings->ghost_accounts_enabled
                    ? 'Ghost account payloads can include `custom_fields`, and API responses still honor the `Show In API` flag.'
                    : 'Ghost accounts are disabled, so this endpoint rejects requests until the feature is enabled.',
                'failures' => [
                    'Ghost accounts are disabled for the project.',
                    'A normal project user already exists for the submitted email.',
                    'Custom field validation errors in the invite payload.',
                ],
            ],
            [
                'label' => 'Claim Ghost Account',
                'method' => 'POST',
                'path' => $this->routePath('api.v1.auth.ghost-accounts.claim'),
                'purpose' => 'Convert a ghost account into a normal project user and issue a token pair.',
                'request' => $this->renderExampleTemplate($this->getClaimGhostAccountRequestExample()),
                'response' => $this->getClaimGhostAccountResponseExample(),
                'note' => $authSettings->ghost_accounts_enabled
                    ? 'Claiming a ghost account can also update `custom_fields` while finishing the onboarding flow.'
                    : 'Ghost account claims are unavailable while the feature is disabled.',
                'failures' => [
                    'Ghost accounts are disabled for the project.',
                    'The email does not belong to a pending ghost account.',
                    'OTP verification failed or a required password was not supplied.',
                ],
            ],
        ];
    }

    public function getCustomFieldsRequestExample(): string
    {
        return $this->encodeJson($this->buildCustomFieldExamplePayload($this->getCustomFieldDefinitions()));
    }

    public function getCustomFieldsResponseExample(): string
    {
        return $this->encodeJson($this->buildCustomFieldExamplePayload($this->getApiVisibleCustomFieldDefinitions()));
    }

    public function getLoginFlowCurlExample(): string
    {
        return <<<'TEXT'
# 1. Sign in and receive a token pair
curl --request POST '{{login_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com",
    "password": "password"
  }'

# 2. Call /me with the returned access token
curl --request GET '{{me_url}}' \
  --header 'Accept: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --header 'Authorization: Bearer <paste-access-token-from-login-response>'
TEXT;
    }

    public function getLoginFlowFetchExample(): string
    {
        return <<<'TEXT'
const loginResponse = await fetch('{{login_url}}', {
  method: 'POST',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Project-Key': '{{project_key}}',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password',
  }),
});

const loginPayload = await loginResponse.json();

const meResponse = await fetch('{{me_url}}', {
  headers: {
    Accept: 'application/json',
    'X-Project-Key': '{{project_key}}',
    Authorization: `Bearer ${loginPayload.data.access_token}`,
  },
});

console.log(await meResponse.json());
TEXT;
    }

    public function getLoginFlowLaravelExample(): string
    {
        return <<<'TEXT'
use Illuminate\Support\Facades\Http;

$loginPayload = Http::acceptJson()
    ->withHeaders([
        'X-Project-Key' => '{{project_key}}',
    ])
    ->post('{{login_url}}', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])
    ->throw()
    ->json('data');

$mePayload = Http::acceptJson()
    ->withHeaders([
        'X-Project-Key' => '{{project_key}}',
    ])
    ->withToken($loginPayload['access_token'])
    ->get('{{me_url}}')
    ->throw()
    ->json('data');
TEXT;
    }

    public function getRegisterRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{register_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{{payload}}'
TEXT;
    }

    public function getLoginRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{login_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com",
    "password": "password"
  }'
TEXT;
    }

    public function getMeRequestExample(): string
    {
        return <<<'TEXT'
curl --request GET '{{me_url}}' \
  --header 'Accept: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --header 'Authorization: Bearer {{token}}'
TEXT;
    }

    public function getLogoutRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{logout_url}}' \
  --header 'Accept: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --header 'Authorization: Bearer {{token}}'
TEXT;
    }

    public function getRefreshRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{refresh_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "refresh_token": "{{refresh_token}}"
  }'
TEXT;
    }

    public function getForgotPasswordRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{forgot_password_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com"
  }'
TEXT;
    }

    public function getResetPasswordRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{reset_password_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com",
    "token": "{{reset_token}}",
    "password": "new-password",
    "password_confirmation": "new-password"
  }'
TEXT;
    }

    public function getSendOtpRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{send_otp_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com",
    "purpose": "login_verify"
  }'
TEXT;
    }

    public function getVerifyOtpRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{verify_otp_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com",
    "purpose": "login_verify",
    "otp_code": "{{otp_code}}"
  }'
TEXT;
    }

    public function getResendOtpRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{resend_otp_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com",
    "purpose": "email_verification"
  }'
TEXT;
    }

    public function getCreateGhostAccountRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{ghost_store_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{{ghost_store_payload}}'
TEXT;
    }

    public function getClaimGhostAccountRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{ghost_claim_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{{ghost_claim_payload}}'
TEXT;
    }

    public function getLoginResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                ...$this->baseTokenResponsePayload(),
                'user' => $this->buildUserPayload([
                    'last_login_at' => now()->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function getRegisterResponseExample(): string
    {
        if ($this->getAuthSettings()->email_verification_enabled) {
            return $this->encodeJson([
                'data' => [
                    'message' => 'Registration successful. Verify your email to continue.',
                    'verification_required' => true,
                    'verification_purpose' => ProjectOtpPurpose::EmailVerification->value,
                    'user' => $this->buildUserPayload([
                        'email_verified_at' => null,
                        'must_verify_email' => true,
                    ]),
                ],
            ]);
        }

        return $this->encodeJson([
            'data' => [
                ...$this->baseTokenResponsePayload(),
                'user' => $this->buildUserPayload(),
            ],
        ]);
    }

    public function getMeResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                ...$this->buildUserPayload([
                    'last_login_at' => now()->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function getLogoutResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                'message' => 'Logged out successfully.',
            ],
        ]);
    }

    public function getAcceptedResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                'message' => 'If the request can be processed, an email will be sent.',
            ],
        ]);
    }

    public function getResetPasswordResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                'message' => 'Password reset successfully.',
            ],
        ]);
    }

    public function getVerifyOtpResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                'verified' => true,
                'message' => 'OTP verified successfully.',
            ],
        ]);
    }

    public function getRefreshResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                ...$this->baseTokenResponsePayload(),
                'user' => $this->buildUserPayload([
                    'last_login_at' => now()->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function getCreateGhostAccountResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                ...$this->buildUserPayload([
                    'email' => 'invitee@example.com',
                    'email_verified_at' => null,
                    'is_ghost' => true,
                    'claimed_at' => null,
                    'invited_at' => now()->toIso8601String(),
                    'ghost_source' => 'api',
                    'must_set_password' => true,
                    'must_verify_email' => false,
                ]),
            ],
        ]);
    }

    public function getClaimGhostAccountResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                ...$this->baseTokenResponsePayload(),
                'user' => $this->buildUserPayload([
                    'email' => 'invitee@example.com',
                    'email_verified_at' => now()->toIso8601String(),
                    'last_login_at' => now()->toIso8601String(),
                    'is_ghost' => false,
                    'claimed_at' => now()->toIso8601String(),
                    'invited_at' => now()->subMinutes(5)->toIso8601String(),
                    'ghost_source' => 'api',
                    'must_set_password' => false,
                    'must_verify_email' => false,
                ]),
            ],
        ]);
    }

    public function renderExampleTemplate(string $template): string
    {
        return strtr($template, [
            '{{project_key}}' => $this->getProject()->api_key,
            '{{register_url}}' => route('api.v1.auth.register'),
            '{{login_url}}' => route('api.v1.auth.login'),
            '{{me_url}}' => route('api.v1.auth.me'),
            '{{logout_url}}' => route('api.v1.auth.logout'),
            '{{refresh_url}}' => route('api.v1.auth.refresh'),
            '{{forgot_password_url}}' => route('api.v1.auth.forgot-password'),
            '{{reset_password_url}}' => route('api.v1.auth.reset-password'),
            '{{send_otp_url}}' => route('api.v1.auth.send-otp'),
            '{{resend_otp_url}}' => route('api.v1.auth.resend-otp'),
            '{{verify_otp_url}}' => route('api.v1.auth.verify-otp'),
            '{{ghost_store_url}}' => route('api.v1.auth.ghost-accounts.store'),
            '{{ghost_claim_url}}' => route('api.v1.auth.ghost-accounts.claim'),
            '{{token}}' => '<plain-text-token>',
            '{{refresh_token}}' => '<refresh-token>',
            '{{reset_token}}' => '<password-reset-token>',
            '{{otp_code}}' => '123456',
            '{{payload}}' => $this->encodeJson($this->buildRegistrationPayload()),
            '{{ghost_store_payload}}' => $this->encodeJson($this->buildGhostAccountPayload()),
            '{{ghost_claim_payload}}' => $this->encodeJson($this->buildGhostAccountClaimPayload()),
        ]);
    }

    protected function routePath(string $routeName): string
    {
        return (string) parse_url(route($routeName), PHP_URL_PATH);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJson(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserPayload(array $overrides = []): array
    {
        return array_replace([
            'id' => '019d6000-0000-7000-8000-000000000001',
            'project_id' => $this->getProject()->id,
            'email' => 'user@example.com',
            'custom_fields' => $this->buildCustomFieldExamplePayload($this->getApiVisibleCustomFieldDefinitions()),
            'email_verified_at' => now()->toIso8601String(),
            'last_login_at' => null,
            'is_active' => true,
            'is_ghost' => false,
            'claimed_at' => null,
            'invited_at' => null,
            'ghost_source' => null,
            'must_set_password' => false,
            'must_verify_email' => false,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRegistrationPayload(): array
    {
        $payload = [
            'email' => 'user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $customFields = $this->buildCustomFieldExamplePayload($this->getCustomFieldDefinitions());

        if ($customFields !== []) {
            $payload['custom_fields'] = $customFields;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGhostAccountPayload(): array
    {
        $payload = [
            'email' => 'invitee@example.com',
            'ghost_source' => 'api',
            'must_set_password' => true,
            'must_verify_email' => false,
            'send_invite' => true,
        ];

        $customFields = $this->buildCustomFieldExamplePayload($this->getCustomFieldDefinitions());

        if ($customFields !== []) {
            $payload['custom_fields'] = $customFields;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGhostAccountClaimPayload(): array
    {
        $payload = [
            'email' => 'ghost@example.com',
            'otp_code' => '{{otp_code}}',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $customFields = $this->buildCustomFieldExamplePayload($this->getCustomFieldDefinitions());

        if ($customFields !== []) {
            $payload['custom_fields'] = $customFields;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseTokenResponsePayload(): array
    {
        $accessTokenTtlMinutes = $this->getAuthSettings()->access_token_ttl_minutes;
        $refreshTokenTtlDays = $this->getAuthSettings()->refresh_token_ttl_days;

        return [
            'token_type' => 'Bearer',
            'access_token' => '1|plain-text-token',
            'refresh_token' => '<refresh-token>',
            'expires_at' => now()->addMinutes($accessTokenTtlMinutes)->toIso8601String(),
            'expires_in_seconds' => $accessTokenTtlMinutes * 60,
            'refresh_token_expires_at' => now()->addDays($refreshTokenTtlDays)->toIso8601String(),
            'refresh_token_expires_in_seconds' => $refreshTokenTtlDays * 86400,
        ];
    }

    /**
     * @param  Collection<int, ProjectUserField>  $definitions
     * @return array<string, mixed>
     */
    private function buildCustomFieldExamplePayload(Collection $definitions): array
    {
        return $definitions
            ->mapWithKeys(fn (ProjectUserField $field): array => [$field->key => $this->exampleValueForField($field)])
            ->all();
    }

    private function exampleValueForField(ProjectUserField $field): mixed
    {
        if ($field->default_value !== null) {
            return $field->default_value;
        }

        return match ($field->type) {
            ProjectUserFieldType::StringType => "Example {$field->label}",
            ProjectUserFieldType::Text => "Example details for {$field->label}.",
            ProjectUserFieldType::Integer => Arr::get($field->validation_rules ?? [], 'min', 1),
            ProjectUserFieldType::Decimal => $this->exampleDecimalValue($field),
            ProjectUserFieldType::Boolean => true,
            ProjectUserFieldType::Date => now()->toDateString(),
            ProjectUserFieldType::DateTime => now()->utc()->toIso8601String(),
            ProjectUserFieldType::Enum => Arr::first($field->options ?? []) ?? 'example',
            ProjectUserFieldType::Email => "user+{$field->key}@example.com",
            ProjectUserFieldType::Url => 'https://example.com/'.Str::kebab($field->key),
            ProjectUserFieldType::Phone => '+12025550123',
            ProjectUserFieldType::Uuid => '019d6000-0000-7000-8000-000000000111',
            ProjectUserFieldType::Json => [
                'key' => $field->key,
                'label' => $field->label,
            ],
        };
    }

    private function exampleDecimalValue(ProjectUserField $field): string
    {
        $rules = $field->validation_rules ?? [];
        $scale = (int) Arr::get($rules, 'scale', 2);
        $minimum = Arr::get($rules, 'min');

        if (is_numeric($minimum)) {
            return number_format((float) $minimum, $scale, '.', '');
        }

        return number_format(9.99, $scale, '.', '');
    }

    private function fieldRulesSummary(ProjectUserField $field): string
    {
        $rules = $field->validation_rules ?? [];
        $summary = [];

        if ($field->is_required && $field->default_value === null) {
            $summary[] = 'Required';
        } elseif ($field->default_value !== null) {
            $summary[] = 'Optional because a default is applied';
        } else {
            $summary[] = 'Optional';
        }

        if ($field->is_unique && $field->supportsUniqueConstraint()) {
            $summary[] = 'Unique per project';
        }

        if ($field->type === ProjectUserFieldType::Enum && filled($field->options)) {
            $summary[] = 'Allowed options: '.implode(', ', $field->options ?? []);
        }

        foreach ([
            'min' => 'Min',
            'max' => 'Max',
            'min_length' => 'Min length',
            'max_length' => 'Max length',
            'scale' => 'Scale',
            'after' => 'After',
            'before' => 'Before',
        ] as $key => $label) {
            if (($value = Arr::get($rules, $key)) !== null) {
                $summary[] = "{$label}: {$value}";
            }
        }

        if (($regex = Arr::get($rules, 'regex')) !== null) {
            $summary[] = 'Regex: '.$regex;
        }

        if (! $field->show_in_api) {
            $summary[] = 'Accepted on register but hidden from API responses';
        }

        return implode(' | ', $summary);
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'None';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }
}
