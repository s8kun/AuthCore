<?php

namespace App\Filament\Resources\ProjectUsers;

use App\Filament\Resources\ProjectUsers\Pages\CreateProjectUser;
use App\Filament\Resources\ProjectUsers\Pages\EditProjectUser;
use App\Filament\Resources\ProjectUsers\Pages\ListProjectUsers;
use App\Filament\Resources\ProjectUsers\Schemas\ProjectUserForm;
use App\Filament\Resources\ProjectUsers\Tables\ProjectUsersTable;
use App\Models\ProjectUser;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectUserResource extends Resource
{
    protected static ?string $model = ProjectUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationGroup(): string|BackedEnum|null
    {
        return 'Identity';
    }

    public static function getNavigationLabel(): string
    {
        return 'Project Users';
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectUsersTable::configure($table);
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

    public static function getPages(): array
    {
        return [
            'index' => ListProjectUsers::route('/'),
            'create' => CreateProjectUser::route('/create'),
            'edit' => EditProjectUser::route('/{record}/edit'),
        ];
    }
}
