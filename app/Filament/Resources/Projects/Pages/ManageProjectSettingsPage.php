<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * @property-read Schema $form
 */
abstract class ManageProjectSettingsPage extends Page implements HasForms
{
    use CanUseDatabaseTransactions;
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->model($this->getSettingsRecord())
            ->statePath('data')
            ->components($this->getFormSchema());
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getHeading(): string
    {
        return "{$this->getRecord()->name} {$this->getTitle()}";
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();
            $data = $this->mutateFormDataBeforeSave($data);

            $this->handleSettingsSave($data);

            $this->commitDatabaseTransaction();
        } catch (Throwable $throwable) {
            $this->rollBackDatabaseTransaction();

            throw $throwable;
        }

        $this->fillForm();

        Notification::make()
            ->success()
            ->title($this->getSavedNotificationTitle())
            ->send();
    }

    /**
     * @return array<Component|Action>
     */
    protected function getFormSchema(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormFillData(): array
    {
        return $this->getSettingsRecord()->attributesToArray();
    }

    protected function fillForm(): void
    {
        $this->form->fill($this->getFormFillData());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleSettingsSave(array $data): void
    {
        $this->getSettingsRecord()->update($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'Settings updated.';
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    abstract protected function getSettingsRecord(): Model;
}
