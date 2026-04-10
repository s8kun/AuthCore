<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Project Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),
                        TextInput::make('slug')
                            ->readOnly()
                            ->dehydrated(false)
                            ->placeholder('Set after save'),
                        Select::make('status')
                            ->options(collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $status): array => [
                                $status->value => ucfirst($status->value),
                            ])->all())
                            ->default(ProjectStatus::Active->value)
                            ->required(),
                        TextInput::make('rate_limit')
                            ->label('Requests Per Minute')
                            ->numeric()
                            ->minValue(1)
                            ->default(60)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Integration Credentials')
                    ->schema([
                        TextInput::make('api_key')
                            ->label('Project API Key')
                            ->default(fn (): string => Project::generateApiKey())
                            ->readOnly()
                            ->required(),
                        TextInput::make('api_secret')
                            ->label('Project API Secret')
                            ->password()
                            ->revealable()
                            ->default(fn (): string => Project::generateApiSecret())
                            ->readOnly()
                            ->required(),
                        Placeholder::make('owner_email')
                            ->label('Owner')
                            ->content(fn (?Project $record): string => $record?->owner?->email ?? (string) auth()->user()?->email),
                    ])
                    ->columns(2),
            ]);
    }
}
