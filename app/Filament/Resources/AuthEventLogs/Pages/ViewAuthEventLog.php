<?php

namespace App\Filament\Resources\AuthEventLogs\Pages;

use App\Filament\Resources\AuthEventLogs\AuthEventLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAuthEventLog extends ViewRecord
{
    protected static string $resource = AuthEventLogResource::class;
}
