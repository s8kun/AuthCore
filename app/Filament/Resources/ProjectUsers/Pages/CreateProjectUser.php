<?php

namespace App\Filament\Resources\ProjectUsers\Pages;

use App\Filament\Resources\ProjectUsers\ProjectUserResource;
use App\Models\ProjectUser;
use App\Services\ProjectUserFields\SaveProjectUserFieldValues;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProjectUser extends CreateRecord
{
    protected static string $resource = ProjectUserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $customFields = is_array($data['custom_fields'] ?? null) ? $data['custom_fields'] : [];
        unset($data['custom_fields']);

        /** @var ProjectUser $projectUser */
        $projectUser = parent::handleRecordCreation($data);

        app(SaveProjectUserFieldValues::class)->save(
            $projectUser,
            $customFields,
            applyDefaults: true,
        );

        return $projectUser;
    }
}
