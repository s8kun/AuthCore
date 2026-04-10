<?php

namespace App\Filament\Resources\Projects\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ProjectIntegrationDetails extends ProjectDocsPage
{
    protected static ?string $navigationLabel = 'Developer Docs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected string $view = 'filament.resources.projects.pages.project-integration-details';

    public function getHeading(): string
    {
        return "{$this->getRecord()->name} Developer Docs";
    }

    public function getSubheading(): ?string
    {
        return 'Project-specific auth docs and quick-start examples.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->makePageSwitchAction('api-reference', 'API Reference', Heroicon::OutlinedCodeBracketSquare),
            $this->makeEditProjectAction(),
        ];
    }
}
