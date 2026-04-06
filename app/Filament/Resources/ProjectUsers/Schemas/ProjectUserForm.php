<?php

namespace App\Filament\Resources\ProjectUsers\Schemas;

use App\Models\ProjectUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('first_name')
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->maxLength(255),
                        TextInput::make('role')
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
            ]);
    }
}
