<?php

namespace App\Filament\Resources\ProjectUsers\Pages;

use App\Filament\Resources\ProjectUsers\ProjectUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectUser extends CreateRecord
{
    protected static string $resource = ProjectUserResource::class;
}
