<?php

namespace App\Filament\Resources\AuthEventLogs;

use App\Filament\Resources\AuthEventLogs\Pages\ListAuthEventLogs;
use App\Filament\Resources\AuthEventLogs\Pages\ViewAuthEventLog;
use App\Filament\Resources\AuthEventLogs\Tables\AuthEventLogsTable;
use App\Models\AuthEventLog;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuthEventLogResource extends Resource
{
    protected static ?string $model = AuthEventLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'event_type';

    public static function getNavigationGroup(): string|BackedEnum|null
    {
        return 'Observability';
    }

    public static function getNavigationLabel(): string
    {
        return 'Auth Event Logs';
    }

    public static function table(Table $table): Table
    {
        return AuthEventLogsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Auth Event')
                    ->schema([
                        TextEntry::make('project.name')
                            ->label('Project'),
                        TextEntry::make('email')
                            ->placeholder('Unknown'),
                        TextEntry::make('event_type')
                            ->badge(),
                        TextEntry::make('success')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Success' : 'Failure')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('endpoint')
                            ->placeholder('N/A'),
                        TextEntry::make('method')
                            ->placeholder('N/A'),
                        TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->placeholder('Unknown'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        KeyValueEntry::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $owner */
        $owner = auth()->user();

        $query = parent::getEloquentQuery()
            ->with('project')
            ->latest('created_at');

        if ($owner === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('project', fn (Builder $projectQuery): Builder => $projectQuery->whereBelongsTo($owner, 'owner'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthEventLogs::route('/'),
            'view' => ViewAuthEventLog::route('/{record}'),
        ];
    }
}
