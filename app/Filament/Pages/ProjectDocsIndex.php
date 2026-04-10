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
        return 'Open developer docs and API references for each project.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createProject')
                ->label('New Project')
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
                'title' => 'Choose a project',
                'description' => 'Docs, credentials, and custom fields are project-specific.',
            ],
            [
                'title' => 'Start with Developer Docs',
                'description' => 'Use the quick-start page first.',
            ],
            [
                'title' => 'Use API Reference for detail',
                'description' => 'Open it when you need exact requests and responses.',
            ],
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
