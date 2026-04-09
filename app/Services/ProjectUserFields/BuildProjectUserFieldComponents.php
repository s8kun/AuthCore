<?php

namespace App\Services\ProjectUserFields;

use App\Enums\ProjectUserFieldType;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\ProjectUserField;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;

class BuildProjectUserFieldComponents
{
    public function __construct(
        private readonly BuildProjectUserFieldDefinitions $fieldDefinitions,
        private readonly BuildProjectUserValidationRules $validationRules,
    ) {}

    /**
     * @return array<int, Component>
     */
    public function forAdminForm(Project $project, ?ProjectUser $projectUser = null): array
    {
        $definitions = $this->fieldDefinitions->forAdminForm($project);

        if ($definitions->isEmpty()) {
            return [
                Placeholder::make('custom_fields_empty')
                    ->label('Custom Fields')
                    ->content('No custom fields are configured for this project yet. Open the project and use "Customize Project Users" to define them.'),
            ];
        }

        return $definitions
            ->map(fn (ProjectUserField $field): Component => $this->componentFor($field, $projectUser))
            ->all();
    }

    private function componentFor(ProjectUserField $field, ?ProjectUser $projectUser): Component
    {
        $component = match ($field->type) {
            ProjectUserFieldType::StringType => TextInput::make("custom_fields.{$field->key}"),
            ProjectUserFieldType::Text => Textarea::make("custom_fields.{$field->key}")->rows(4),
            ProjectUserFieldType::Integer => TextInput::make("custom_fields.{$field->key}")->numeric()->inputMode('numeric'),
            ProjectUserFieldType::Decimal => TextInput::make("custom_fields.{$field->key}")->numeric()->inputMode('decimal'),
            ProjectUserFieldType::Boolean => Toggle::make("custom_fields.{$field->key}"),
            ProjectUserFieldType::Date => DatePicker::make("custom_fields.{$field->key}"),
            ProjectUserFieldType::DateTime => DateTimePicker::make("custom_fields.{$field->key}"),
            ProjectUserFieldType::Enum => Select::make("custom_fields.{$field->key}")
                ->options(collect($field->options ?? [])->mapWithKeys(fn (string $option): array => [$option => $option])->all()),
            ProjectUserFieldType::Email => TextInput::make("custom_fields.{$field->key}")->email(),
            ProjectUserFieldType::Url => TextInput::make("custom_fields.{$field->key}")->url(),
            ProjectUserFieldType::Phone => TextInput::make("custom_fields.{$field->key}")->tel(),
            ProjectUserFieldType::Uuid => TextInput::make("custom_fields.{$field->key}"),
            ProjectUserFieldType::Json => Textarea::make("custom_fields.{$field->key}")
                ->rows(6)
                ->formatStateUsing(fn (mixed $state): string => is_array($state)
                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'
                    : (string) ($state ?? ''))
                ->dehydrateStateUsing(function (mixed $state): array {
                    if (is_array($state)) {
                        return $state;
                    }

                    if (! is_string($state) || trim($state) === '') {
                        return [];
                    }

                    $decoded = json_decode($state, true);

                    return is_array($decoded) ? $decoded : [];
                }),
        };

        return $component
            ->label($field->label)
            ->helperText($field->ui_settings['help_text'] ?? $field->description)
            ->placeholder($field->placeholder)
            ->default($field->default_value)
            ->required($field->is_required && $field->default_value === null)
            ->rules($this->validationRules->forField($field, $projectUser))
            ->columnSpan($field->type === ProjectUserFieldType::Text || $field->type === ProjectUserFieldType::Json ? 2 : 1);
    }
}
