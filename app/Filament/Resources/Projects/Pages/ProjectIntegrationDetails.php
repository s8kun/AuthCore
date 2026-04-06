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
            ->loadMissing('owner')
            ->loadCount(['projectUsers', 'apiRequestLogs']);

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
    "password_confirmation": "password",
    "device_name": "Web App"
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
    "password": "password",
    "device_name": "Web App"
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

    public function getLoginResponseExample(): string
    {
        return $this->getRegisterResponseExample();
    }

    public function getRegisterResponseExample(): string
    {
        return $this->encodeJson([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => '1|plain-text-token',
                'expires_at' => now()->addMinutes((int) config('sanctum.expiration'))->toIso8601String(),
                'expires_in_seconds' => ((int) config('sanctum.expiration')) * 60,
                'user' => [
                    'id' => 1,
                    'project_id' => $this->getProject()->id,
                    'email' => 'user@example.com',
                    'role' => 'user',
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
                'id' => 1,
                'project_id' => $this->getProject()->id,
                'email' => 'user@example.com',
                'role' => 'user',
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

    public function renderExampleTemplate(string $template): string
    {
        return strtr($template, [
            '{{project_key}}' => $this->getProject()->api_key,
            '{{register_url}}' => route('api.v1.auth.register'),
            '{{login_url}}' => route('api.v1.auth.login'),
            '{{me_url}}' => route('api.v1.auth.me'),
            '{{logout_url}}' => route('api.v1.auth.logout'),
            '{{token}}' => '<plain-text-token>',
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
