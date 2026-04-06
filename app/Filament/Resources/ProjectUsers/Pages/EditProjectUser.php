<?php

namespace App\Filament\Resources\ProjectUsers\Pages;

use App\Filament\Resources\ProjectUsers\ProjectUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProjectUser extends EditRecord
{
    protected static string $resource = ProjectUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
