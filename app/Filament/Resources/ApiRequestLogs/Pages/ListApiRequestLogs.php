<?php

namespace App\Filament\Resources\ApiRequestLogs\Pages;

use App\Filament\Resources\ApiRequestLogs\ApiRequestLogResource;
use Filament\Resources\Pages\ListRecords;

class ListApiRequestLogs extends ListRecords
{
    protected static string $resource = ApiRequestLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
