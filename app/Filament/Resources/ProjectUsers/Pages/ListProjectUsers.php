<?php

namespace App\Filament\Resources\ProjectUsers\Pages;

use App\Filament\Resources\ProjectUsers\ProjectUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectUsers extends ListRecords
{
    protected static string $resource = ProjectUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
