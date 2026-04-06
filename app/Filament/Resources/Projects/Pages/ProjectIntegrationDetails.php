<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ApiRequestLog;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectIntegrationDetails extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.project-integration-details';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getHeading(): string
    {
        return "{$this->getRecord()->name} Integration Details";
    }

    public function getSubheading(): ?string
    {
        return 'Everything needed to wire a client application to the project-scoped auth API.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit Project')
                ->icon(Heroicon::PencilSquare)
                ->url(ProjectResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    public function getBaseApiUrl(): string
    {
        return Str::beforeLast(route('api.v1.auth.register'), '/register');
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
     * @return Collection<int, ApiRequestLog>
     */
    public function getRecentLogs(): Collection
    {
        return $this->getRecord()
            ->apiRequestLogs()
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    public function getRegisterRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{register_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "user@example.com",
    "password": "password",
        "password_confirmation": "password"
  }'
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

    public function getClaimGhostAccountRequestExample(): string
    {
        return <<<'TEXT'
curl --request POST '{{ghost_claim_url}}' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-Project-Key: {{project_key}}' \
  --data '{
    "email": "ghost@example.com",
    "otp_code": "{{otp_code}}",
    "password": "new-password",
    "password_confirmation": "new-password"
  }'
TEXT;
    }

    public function getLoginResponseExample(): string
    {
        return $this->getRegisterResponseExample();
    }

    public function getRegisterResponseExample(): string
    {
        $accessTokenTtlMinutes = $this->getProject()->authSettings?->access_token_ttl_minutes ?? (int) config('sanctum.expiration');

        return $this->encodeJson([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => '1|plain-text-token',
                'refresh_token' => '<refresh-token>',
                'expires_at' => now()->addMinutes($accessTokenTtlMinutes)->toIso8601String(),
                'expires_in_seconds' => $accessTokenTtlMinutes * 60,
                'refresh_token_expires_at' => now()->addDays(30)->toIso8601String(),
                'refresh_token_expires_in_seconds' => 2592000,
                'user' => [
                    'id' => '019d6000-0000-7000-8000-000000000001',
                    'project_id' => $this->getProject()->id,
                    'email' => 'user@example.com',
                    'is_active' => true,
                    'is_ghost' => false,
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ],
        ]);
    }

    public function getMeResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                'id' => '019d6000-0000-7000-8000-000000000001',
                'project_id' => $this->getProject()->id,
                'email' => 'user@example.com',
                'is_active' => true,
                'is_ghost' => false,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
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
            '{{verify_otp_url}}' => route('api.v1.auth.verify-otp'),
            '{{ghost_claim_url}}' => route('api.v1.auth.ghost-accounts.claim'),
            '{{token}}' => '<plain-text-token>',
            '{{refresh_token}}' => '<refresh-token>',
            '{{reset_token}}' => '<password-reset-token>',
            '{{otp_code}}' => '123456',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJson(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
