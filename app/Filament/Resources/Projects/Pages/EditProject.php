<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('integration')
                ->label('Integration Details')
                ->icon(Heroicon::Eye)
                ->url(fn (): string => ProjectResource::getUrl('integration', ['record' => $this->getRecord()])),
            Action::make('rotateApiKey')
                ->label('Rotate API Key')
                ->icon(Heroicon::Key)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Project $project */
                    $project = $this->getRecord();

                    $project->forceFill([
                        'api_key' => Project::generateApiKey(),
                    ])->save();

                    Notification::make()
                        ->title('Project API key rotated.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
