<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('owner.email')
                    ->label('Owner')
                    ->searchable(),
                TextColumn::make('api_key')
                    ->label('Project Key')
                    ->copyable()
                    ->limit(16)
                    ->tooltip(fn (Project $record): string => $record->api_key),
                TextColumn::make('rate_limit')
                    ->label('Rate Limit')
                    ->suffix(' rpm')
                    ->sortable(),
                TextColumn::make('project_users_count')
                    ->label('Users')
                    ->sortable(),
                TextColumn::make('api_request_logs_count')
                    ->label('Requests')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('integration')
                    ->label('Integration')
                    ->icon(Heroicon::Eye)
                    ->url(fn (Project $record): string => ProjectResource::getUrl('integration', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
