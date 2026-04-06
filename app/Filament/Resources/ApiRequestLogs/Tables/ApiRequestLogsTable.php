<?php

namespace App\Filament\Resources\ApiRequestLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiRequestLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable(),
                TextColumn::make('endpoint')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'GET' => 'gray',
                        'POST' => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->placeholder('Unknown'),
                TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
