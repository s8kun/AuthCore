<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ProjectAuthSettings;
use App\Filament\Resources\Projects\Pages\ProjectEmailTemplates;
use App\Filament\Resources\Projects\Pages\ProjectIntegrationDetails;
use App\Filament\Resources\Projects\Pages\ProjectMailSettings;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|BackedEnum|null
    {
        return 'Platform';
    }

    public static function getNavigationLabel(): string
    {
        return 'Projects';
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $owner */
        $owner = auth()->user();

        $query = parent::getEloquentQuery()
            ->with('owner')
            ->withCount(['projectUsers', 'apiRequestLogs', 'authEventLogs'])
            ->latest('id');

        if ($owner === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereBelongsTo($owner, 'owner');
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditProject::class,
            ProjectMailSettings::class,
            ProjectAuthSettings::class,
            ProjectEmailTemplates::class,
            ProjectIntegrationDetails::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
            'mail-settings' => ProjectMailSettings::route('/{record}/mail-settings'),
            'auth-settings' => ProjectAuthSettings::route('/{record}/auth-settings'),
            'email-templates' => ProjectEmailTemplates::route('/{record}/email-templates'),
            'integration' => ProjectIntegrationDetails::route('/{record}/integration'),
        ];
    }
}
