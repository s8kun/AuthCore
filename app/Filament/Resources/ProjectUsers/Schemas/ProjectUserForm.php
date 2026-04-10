<?php

namespace App\Filament\Resources\ProjectUsers\Schemas;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Services\ProjectUserFields\BuildProjectUserFieldComponents;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProjectUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Project User')
                    ->schema([
                        Select::make('project_id')
                            ->relationship(
                                name: 'project',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereBelongsTo(auth()->user(), 'owner'),
                            )
                            ->live()
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (?ProjectUser $record): bool => $record === null)
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->dehydrated(false),
                        Toggle::make('is_active')
                            ->default(true),
                        Toggle::make('is_ghost')
                            ->default(false),
                        Toggle::make('must_set_password')
                            ->default(false),
                        Toggle::make('must_verify_email')
                            ->default(false),
                    ])
                    ->columns(2),
                Section::make('Custom Fields')
                    ->description('Use the project schema to add profile and business fields like first_name, last_name, phone, department, or external_id.')
                    ->schema([
                        Grid::make(2)
                            ->schema(function (Get $get, ?ProjectUser $record): array {
                                $projectId = $record?->project_id ?? $get('project_id');

                                if (! filled($projectId)) {
                                    return [
                                        Placeholder::make('custom_fields_project_hint')
                                            ->label('Custom Fields')
                                            ->content('Select a project first to load its custom user fields.'),
                                    ];
                                }

                                $project = Project::query()->find($projectId);

                                if (! $project instanceof Project) {
                                    return [];
                                }

                                return app(BuildProjectUserFieldComponents::class)->forAdminForm($project, $record);
                            })
                            ->key('projectUserCustomFields'),
                    ])
                    ->columns(2),
            ]);
    }
}
