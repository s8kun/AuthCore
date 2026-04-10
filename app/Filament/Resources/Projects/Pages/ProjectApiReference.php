<?php

namespace App\Filament\Resources\Projects\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ProjectApiReference extends ProjectDocsPage
{
    protected static ?string $navigationLabel = 'API Reference';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected string $view = 'filament.resources.projects.pages.project-api-reference';

    public function getHeading(): string
    {
        return "{$this->getRecord()->name} API Reference";
    }

    public function getSubheading(): ?string
    {
        return 'Exact request and response examples for this project.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->makePageSwitchAction('integration', 'Developer Docs', Heroicon::OutlinedBookOpen),
            $this->makeEditProjectAction(),
        ];
    }
}
