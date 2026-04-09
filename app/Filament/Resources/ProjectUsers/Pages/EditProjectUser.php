<?php

namespace App\Filament\Resources\ProjectUsers\Pages;

use App\Filament\Resources\ProjectUsers\ProjectUserResource;
use App\Models\ProjectUser;
use App\Services\ProjectUserFields\LoadProjectUserFieldValueMap;
use App\Services\ProjectUserFields\SaveProjectUserFieldValues;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProjectUser extends EditRecord
{
    protected static string $resource = ProjectUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ProjectUser $projectUser */
        $projectUser = $this->getRecord();

        return [
            ...$data,
            'custom_fields' => app(LoadProjectUserFieldValueMap::class)->forAdminForm($projectUser),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $customFields = is_array($data['custom_fields'] ?? null) ? $data['custom_fields'] : [];
        unset($data['custom_fields']);

        $record->update($data);

        /** @var ProjectUser $record */
        app(SaveProjectUserFieldValues::class)->save($record, $customFields);

        return $record;
    }
}
