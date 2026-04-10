<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class ProjectDocsIndex extends Page
{
    protected static ?string $slug = 'docs/project-docs';

    protected static string|UnitEnum|null $navigationGroup = 'Docs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Project Docs';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.project-docs-index';

    public function getHeading(): string
    {
        return 'Project Docs';
    }

    public function getSubheading(): ?string
    {
        return 'Learn the product shape first, then jump directly into a project-specific developer docs flow when you are ready to integrate.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createProject')
                ->label('Create Project')
                ->icon(Heroicon::Plus)
                ->url(ProjectResource::getUrl('create')),
        ];
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return once(function (): Collection {
            return $this->getProjectsQuery()
                ->with('authSettings')
                ->withCount(['projectUsers', 'apiRequestLogs'])
                ->latest('updated_at')
                ->limit(3)
                ->get();
        });
    }

    public function getProjectCount(): int
    {
        return $this->getProjectsQuery()->count();
    }

    /**
     * @return array<int, array{title: string, description: string}>
     */
    public function getDocsPlaybook(): array
    {
        return [
            [
                'title' => 'Choose the project you are integrating',
                'description' => 'Every API request is project-scoped, so credentials, custom fields, and feature flags all depend on the selected project.',
            ],
            [
                'title' => 'Open Developer Docs for the fastest happy path',
                'description' => 'Use the project-specific docs page to grab the key headers, see the quickest login flow, and understand how this project behaves.',
            ],
            [
                'title' => 'Switch to API Reference when you need depth',
                'description' => 'Once the basic flow is working, use the API reference page for detailed request and response examples plus failure cases.',
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, description: string}>
     */
    public function getApiConceptCards(): array
    {
        return [
            [
                'title' => 'Project-scoped credentials',
                'description' => 'Every auth request needs the project key header so the platform can resolve the correct project settings and schema.',
            ],
            [
                'title' => 'Short-lived access, long-lived refresh',
                'description' => 'Clients use a bearer access token for authenticated requests and a refresh token to rotate sessions safely over time.',
            ],
            [
                'title' => 'Built-in fields plus `custom_fields`',
                'description' => 'Base auth fields stay consistent across projects, while `custom_fields` let each project define its own user contract.',
            ],
            [
                'title' => 'Feature-aware flows',
                'description' => 'Email verification, OTP, forgot password, and ghost accounts change the contract, so docs should always be read in the project context.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getIntegrationChecklist(): array
    {
        return [
            'Store the `X-Project-Key` for the environment you are integrating against.',
            'Implement one happy-path sign-in or registration flow before building edge cases.',
            'Handle access-token expiry by rotating refresh tokens on the client or backend.',
            'Map project `custom_fields` exactly to the user schema before sending payloads.',
            'Enable feature-specific UI only after confirming the project has those auth features turned on.',
        ];
    }

    protected function getProjectsQuery(): Builder
    {
        $owner = $this->getOwner();

        $query = Project::query();

        if ($owner === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereBelongsTo($owner, 'owner');
    }

    protected function getOwner(): ?User
    {
        $owner = auth()->user();

        return $owner instanceof User ? $owner : null;
    }
}
