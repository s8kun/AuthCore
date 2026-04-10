<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectUserFieldType;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ProjectUserField;
use App\Services\ProjectUserFields\NormalizeProjectUserFieldValue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProjectUserSchema extends ManageRelatedRecords
{
    protected static string $resource = ProjectResource::class;

    protected static string $relationship = 'projectUserFields';

    protected static ?string $title = 'Customize Project Users';

    protected static ?string $breadcrumb = 'Customize Project Users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public function getSubheading(): ?string
    {
        return 'Define project-specific fields for project users. Only system-managed auth and account-state fields are built in; profile fields like first_name, last_name, and phone belong in this schema.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('builtInFields')
                ->label('Built-in Fields')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->slideOver()
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->schema([
                    Section::make('Already Built Into Project Users')
                        ->schema([
                            Placeholder::make('fixed_fields')
                                ->hiddenLabel()
                                ->content(collect(ProjectUserField::RESERVED_KEYS)->implode(', ')),
                        ]),
                    Section::make('Typical Custom Fields')
                        ->schema([
                            Placeholder::make('examples')
                                ->hiddenLabel()
                                ->content('Examples: first_name, last_name, phone, status, department, employee_number, onboarding_stage, external_id.'),
                        ]),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('key')
                    ->badge()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ProjectUserFieldType|string $state): string => $state instanceof ProjectUserFieldType ? $state->label() : (string) $state),
                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),
                IconColumn::make('is_unique')
                    ->label('Unique')
                    ->boolean(),
                IconColumn::make('show_in_admin_form')
                    ->label('Admin Form')
                    ->boolean(),
                IconColumn::make('show_in_api')
                    ->label('API')
                    ->boolean(),
                IconColumn::make('show_in_table')
                    ->label('Table')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('createField')
                    ->label('Add Field')
                    ->icon(Heroicon::Plus)
                    ->slideOver()
                    ->schema($this->getFieldSchema())
                    ->action(function (array $data): void {
                        $validated = $this->validateDefinitionData($data);

                        $this->getRecord()->projectUserFields()->create($validated);
                        $this->resetTable();

                        Notification::make()
                            ->success()
                            ->title('Custom field created.')
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('editField')
                    ->label('Edit')
                    ->icon(Heroicon::PencilSquare)
                    ->slideOver()
                    ->fillForm(fn (ProjectUserField $record): array => $this->fillDefinitionFormData($record))
                    ->schema($this->getFieldSchema())
                    ->action(function (array $data, ProjectUserField $record): void {
                        $validated = $this->validateDefinitionData($data, $record);

                        $record->update($validated);
                        $this->resetTable();

                        Notification::make()
                            ->success()
                            ->title('Custom field updated.')
                            ->send();
                    }),
                Action::make('toggleActive')
                    ->label(fn (ProjectUserField $record): string => $record->is_active ? 'Disable' : 'Enable')
                    ->icon(fn (ProjectUserField $record): Heroicon => $record->is_active ? Heroicon::OutlinedPauseCircle : Heroicon::OutlinedPlayCircle)
                    ->requiresConfirmation()
                    ->action(function (ProjectUserField $record): void {
                        $record->update([
                            'is_active' => ! $record->is_active,
                        ]);

                        $this->resetTable();

                        Notification::make()
                            ->success()
                            ->title($record->is_active ? 'Custom field enabled.' : 'Custom field disabled.')
                            ->send();
                    }),
                Action::make('archiveField')
                    ->label('Archive')
                    ->color('danger')
                    ->icon(Heroicon::Trash)
                    ->requiresConfirmation()
                    ->action(function (ProjectUserField $record): void {
                        $record->delete();
                        $this->resetTable();

                        Notification::make()
                            ->success()
                            ->title('Custom field archived.')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No custom fields defined')
            ->emptyStateDescription('Built-in project user fields are already available. Add custom fields here for project-specific data such as status, department, employee number, or onboarding stage.');
    }

    /**
     * @return array<int, mixed>
     */
    private function getFieldSchema(): array
    {
        return [
            Section::make('Field Basics')
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (filled($get('key'))) {
                                return;
                            }

                            $set('key', str($state)->snake()->toString());
                        }),
                    TextInput::make('key')
                        ->required()
                        ->maxLength(64)
                        ->helperText('Lowercase snake_case only. System keys like email, password, verification timestamps, and account flags are reserved.')
                        ->regex('/^[a-z][a-z0-9_]*$/'),
                    Select::make('type')
                        ->options(ProjectUserFieldType::options())
                        ->required()
                        ->live(),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                    TextInput::make('placeholder')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('help_text')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Grid::make(2)
                ->schema(fn (Get $get): array => $this->getDynamicFieldSchema($get))
                ->key('dynamicFieldSettings'),
            Section::make('Behavior')
                ->schema([
                    Toggle::make('is_required')
                        ->label('Required')
                        ->live()
                        ->afterStateUpdated(fn (Set $set, bool $state): bool => $state ? $set('is_nullable', false) : true),
                    Toggle::make('is_nullable')
                        ->label('Nullable')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(fn (Set $set, bool $state): bool => $state ? $set('is_required', false) : true),
                    Toggle::make('is_unique')
                        ->label('Unique'),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    Toggle::make('show_in_admin_form')
                        ->label('Show In Admin Form')
                        ->default(true),
                    Toggle::make('show_in_api')
                        ->label('Show In API')
                        ->default(true),
                    Toggle::make('show_in_table')
                        ->label('Show In Table'),
                    Toggle::make('is_searchable')
                        ->label('Searchable'),
                    Toggle::make('is_filterable')
                        ->label('Filterable'),
                    Toggle::make('is_sortable')
                        ->label('Sortable'),
                ])
                ->columns(2),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function getDynamicFieldSchema(Get $get): array
    {
        $type = $get('type');

        if (! is_string($type) || $type === '') {
            return [
                Placeholder::make('field_type_hint')
                    ->label('Field Settings')
                    ->content('Choose a field type to configure defaults, enum options, and validation limits.'),
            ];
        }

        $projectUserFieldType = ProjectUserFieldType::from($type);
        $components = [];

        if ($projectUserFieldType === ProjectUserFieldType::Enum) {
            $components[] = TagsInput::make('options')
                ->label('Allowed Options')
                ->trim()
                ->reorderable()
                ->nestedRecursiveRules(['min:1', 'max:255'])
                ->helperText('These are the values the project owner can choose from.')
                ->required()
                ->columnSpanFull();
        }

        $components[] = $this->defaultValueComponentFor($projectUserFieldType);

        if ($projectUserFieldType->isStringLike()) {
            $components[] = TextInput::make('min_length')
                ->numeric()
                ->minValue(0);
            $components[] = TextInput::make('max_length')
                ->numeric()
                ->minValue(1);
            $components[] = TextInput::make('regex')
                ->label('Regex')
                ->columnSpanFull();
        }

        if (in_array($projectUserFieldType, [ProjectUserFieldType::Integer, ProjectUserFieldType::Decimal], true)) {
            $components[] = TextInput::make('min')
                ->numeric();
            $components[] = TextInput::make('max')
                ->numeric();
        }

        if ($projectUserFieldType === ProjectUserFieldType::Decimal) {
            $components[] = TextInput::make('scale')
                ->numeric()
                ->minValue(0)
                ->default(2);
        }

        if (in_array($projectUserFieldType, [ProjectUserFieldType::Date, ProjectUserFieldType::DateTime], true)) {
            $components[] = DatePicker::make('after')
                ->label('Must Be After');
            $components[] = DatePicker::make('before')
                ->label('Must Be Before');
        }

        return [
            Section::make('Type-Specific Settings')
                ->schema($components)
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    private function defaultValueComponentFor(ProjectUserFieldType $type): Toggle|TextInput|Textarea
    {
        return match ($type) {
            ProjectUserFieldType::Boolean => Toggle::make('default_value')
                ->label('Default Value'),
            ProjectUserFieldType::Json => Textarea::make('default_value')
                ->label('Default JSON')
                ->rows(5)
                ->columnSpanFull(),
            default => TextInput::make('default_value')
                ->label('Default Value'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fillDefinitionFormData(ProjectUserField $record): array
    {
        $rules = $record->validation_rules ?? [];
        $defaultValue = $record->default_value;

        if (is_array($defaultValue)) {
            $defaultValue = json_encode($defaultValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return [
            'label' => $record->label,
            'key' => $record->key,
            'type' => $record->type->value,
            'description' => $record->description,
            'placeholder' => $record->placeholder,
            'help_text' => $record->ui_settings['help_text'] ?? null,
            'default_value' => $defaultValue,
            'options' => $record->options,
            'min' => Arr::get($rules, 'min'),
            'max' => Arr::get($rules, 'max'),
            'min_length' => Arr::get($rules, 'min_length'),
            'max_length' => Arr::get($rules, 'max_length'),
            'regex' => Arr::get($rules, 'regex'),
            'scale' => Arr::get($rules, 'scale'),
            'after' => Arr::get($rules, 'after'),
            'before' => Arr::get($rules, 'before'),
            'is_required' => $record->is_required,
            'is_nullable' => $record->is_nullable,
            'is_unique' => $record->is_unique,
            'is_active' => $record->is_active,
            'show_in_admin_form' => $record->show_in_admin_form,
            'show_in_api' => $record->show_in_api,
            'show_in_table' => $record->show_in_table,
            'is_searchable' => $record->is_searchable,
            'is_filterable' => $record->is_filterable,
            'is_sortable' => $record->is_sortable,
            'sort_order' => $record->sort_order,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateDefinitionData(array $data, ?ProjectUserField $record = null): array
    {
        $project = $this->getRecord();

        $validator = Validator::make($data, [
            'label' => ['required', 'string', 'max:120'],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'type' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'default_value' => ['nullable'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'min:1', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric'],
            'min_length' => ['nullable', 'integer', 'min:0'],
            'max_length' => ['nullable', 'integer', 'min:1'],
            'regex' => ['nullable', 'string'],
            'scale' => ['nullable', 'integer', 'min:0', 'max:10'],
            'after' => ['nullable', 'date'],
            'before' => ['nullable', 'date'],
            'is_required' => ['nullable', 'boolean'],
            'is_nullable' => ['nullable', 'boolean'],
            'is_unique' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'show_in_admin_form' => ['nullable', 'boolean'],
            'show_in_api' => ['nullable', 'boolean'],
            'show_in_table' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_sortable' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($data, $project, $record): void {
            $type = ProjectUserFieldType::tryFrom((string) ($data['type'] ?? ''));

            if (! $type instanceof ProjectUserFieldType) {
                $validator->errors()->add('type', 'Select a supported field type.');

                return;
            }

            $key = (string) ($data['key'] ?? '');

            if (in_array($key, ProjectUserField::RESERVED_KEYS, true)) {
                $validator->errors()->add('key', 'This key is reserved by the system.');
            }

            $duplicateKeyExists = ProjectUserField::query()
                ->whereBelongsTo($project)
                ->where('key', $key)
                ->when($record instanceof ProjectUserField, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($duplicateKeyExists) {
                $validator->errors()->add('key', 'This project already uses that field key.');
            }

            $options = collect($data['options'] ?? [])
                ->map(fn (mixed $option): string => trim((string) $option))
                ->filter()
                ->values();

            if ($type === ProjectUserFieldType::Enum) {
                if ($options->isEmpty()) {
                    $validator->errors()->add('options', 'Enum fields need at least one option.');
                }

                if ($options->count() !== $options->unique()->count()) {
                    $validator->errors()->add('options', 'Enum options must be unique.');
                }
            }

            if (($data['is_unique'] ?? false) && ! $type->supportsUniqueConstraint()) {
                $validator->errors()->add('is_unique', 'This field type does not support unique values in v1.');
            }

            if (
                $record instanceof ProjectUserField
                && $record->type !== $type
                && $record->values()->exists()
            ) {
                $validator->errors()->add('type', 'This field already has values, so changing its type is not allowed.');
            }

            try {
                $normalizedDefault = $this->normalizeDefaultValue($type, $data['default_value'] ?? null);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }

                return;
            }

            if ($type === ProjectUserFieldType::Enum && $normalizedDefault !== null && ! $options->contains($normalizedDefault)) {
                $validator->errors()->add('default_value', 'The default value must match one of the enum options.');
            }
        });

        $validated = $validator->validate();
        $type = ProjectUserFieldType::from((string) $validated['type']);

        return [
            'label' => $validated['label'],
            'key' => $validated['key'],
            'type' => $type,
            'description' => $validated['description'] ?? null,
            'placeholder' => $validated['placeholder'] ?? null,
            'default_value' => $this->normalizeDefaultValue($type, $validated['default_value'] ?? null),
            'options' => $type === ProjectUserFieldType::Enum
                ? collect($validated['options'] ?? [])->map(fn (string $option): string => trim($option))->filter()->values()->all()
                : null,
            'validation_rules' => array_filter([
                'min' => Arr::get($validated, 'min'),
                'max' => Arr::get($validated, 'max'),
                'min_length' => Arr::get($validated, 'min_length'),
                'max_length' => Arr::get($validated, 'max_length'),
                'regex' => Arr::get($validated, 'regex'),
                'scale' => Arr::get($validated, 'scale'),
                'after' => Arr::get($validated, 'after'),
                'before' => Arr::get($validated, 'before'),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'ui_settings' => array_filter([
                'help_text' => $validated['help_text'] ?? null,
            ], fn (mixed $value): bool => filled($value)),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_nullable' => (bool) ($validated['is_nullable'] ?? true),
            'is_unique' => (bool) ($validated['is_unique'] ?? false),
            'is_searchable' => (bool) ($validated['is_searchable'] ?? false),
            'is_filterable' => (bool) ($validated['is_filterable'] ?? false),
            'is_sortable' => (bool) ($validated['is_sortable'] ?? false),
            'show_in_admin_form' => (bool) ($validated['show_in_admin_form'] ?? true),
            'show_in_api' => (bool) ($validated['show_in_api'] ?? true),
            'show_in_table' => (bool) ($validated['show_in_table'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    private function normalizeDefaultValue(ProjectUserFieldType $type, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $field = new ProjectUserField([
            'type' => $type,
        ]);

        if ($type === ProjectUserFieldType::Json && is_string($value)) {
            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'default_value' => ['The JSON default value must be a valid JSON object or array.'],
                ]);
            }

            $value = $decoded;
        }

        return app(NormalizeProjectUserFieldValue::class)->normalize($field, $value);
    }
}
