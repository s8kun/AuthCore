<?php

namespace App\Filament\Resources\ApiRequestLogs;

use App\Filament\Resources\ApiRequestLogs\Pages\ListApiRequestLogs;
use App\Filament\Resources\ApiRequestLogs\Pages\ViewApiRequestLog;
use App\Filament\Resources\ApiRequestLogs\Schemas\ApiRequestLogInfolist;
use App\Filament\Resources\ApiRequestLogs\Tables\ApiRequestLogsTable;
use App\Models\ApiRequestLog;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiRequestLogResource extends Resource
{
    protected static ?string $model = ApiRequestLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'endpoint';

    public static function getNavigationGroup(): string|BackedEnum|null
    {
        return 'Observability';
    }

    public static function getNavigationLabel(): string
    {
        return 'API Request Logs';
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApiRequestLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiRequestLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
            'index' => ListApiRequestLogs::route('/'),
            'view' => ViewApiRequestLog::route('/{record}'),
        ];
    }
}
