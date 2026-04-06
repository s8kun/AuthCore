<?php

namespace App\Filament\Resources\AuthEventLogs\Pages;

use App\Filament\Resources\AuthEventLogs\AuthEventLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuthEventLogs extends ListRecords
{
    protected static string $resource = AuthEventLogResource::class;
}
