<?php

namespace App\Filament\Resources\AuthEventLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuthEventLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('Unknown'),
                TextColumn::make('event_type')
                    ->badge()
                    ->searchable(),
                IconColumn::make('success')
                    ->boolean(),
                TextColumn::make('endpoint')
                    ->wrap()
                    ->placeholder('N/A'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('success')
                    ->options([
                        1 => 'Success',
                        0 => 'Failure',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
