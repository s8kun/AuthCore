<?php

namespace App\Filament\Resources\ApiRequestLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApiRequestLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Log Details')
                    ->schema([
                        TextEntry::make('project.name')
                            ->label('Project'),
                        TextEntry::make('endpoint'),
                        TextEntry::make('method')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'GET' => 'gray',
                                'POST' => 'success',
                                default => 'warning',
                            }),
                        TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->placeholder('Unknown'),
                        TextEntry::make('created_at')
                            ->label('Logged At')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
