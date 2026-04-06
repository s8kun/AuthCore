<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Project;
use Filament\Forms\Components\Placeholder;
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
                            ->required()
                            ->helperText('This key is generated automatically and shown again on the integration details page after save.'),
                        Placeholder::make('owner_email')
                            ->label('Owner Account')
                            ->content(fn (?Project $record): string => $record?->owner?->email ?? (string) auth()->user()?->email),
                    ])
                    ->columns(2),
            ]);
    }
}
