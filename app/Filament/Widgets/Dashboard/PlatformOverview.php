<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ApiRequestLog;
use App\Models\AuthEventLog;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PlatformOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Overview';

    protected ?string $description = 'Projects, users, and recent activity.';

    protected function getStats(): array
    {
        $latestProject = $this->getLatestProject();

        return [
            Stat::make('Projects', number_format($this->getProjectsCount()))
                ->url(ProjectResource::getUrl('index')),
            Stat::make('Project Users', number_format($this->getProjectUsersCount()))
                ->url(ProjectResource::getUrl('index')),
            Stat::make('Requests (24h)', number_format($this->getRecentRequestCount())),
            Stat::make('Auth Events (24h)', number_format($this->getRecentAuthEventCount())),
            Stat::make('Latest Project', $latestProject?->name ?? 'Create project')
                ->url($latestProject
                    ? ProjectResource::getUrl('integration', ['record' => $latestProject])
                    : ProjectResource::getUrl('create')),
        ];
    }

    protected function getProjectsCount(): int
    {
        return $this->getProjectsQuery()->count();
    }

    protected function getProjectUsersCount(): int
    {
        $owner = $this->getOwner();

        if ($owner === null) {
            return 0;
        }

        return ProjectUser::query()
            ->whereHas('project', fn (Builder $query): Builder => $query->whereBelongsTo($owner, 'owner'))
            ->count();
    }

    protected function getRecentRequestCount(): int
    {
        $owner = $this->getOwner();

        if ($owner === null) {
            return 0;
        }

        return ApiRequestLog::query()
            ->where('created_at', '>=', now()->subDay())
            ->whereHas('project', fn (Builder $query): Builder => $query->whereBelongsTo($owner, 'owner'))
            ->count();
    }

    protected function getRecentAuthEventCount(): int
    {
        $owner = $this->getOwner();

        if ($owner === null) {
            return 0;
        }

        return AuthEventLog::query()
            ->where('created_at', '>=', now()->subDay())
            ->whereHas('project', fn (Builder $query): Builder => $query->whereBelongsTo($owner, 'owner'))
            ->count();
    }

    protected function getLatestProject(): ?Project
    {
        return $this->getProjectsQuery()
            ->latest('updated_at')
            ->first();
    }

    protected function getProjectsQuery(): Builder
    {
        $owner = $this->getOwner();

        $query = Project::query();

        if ($owner === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereBelongsTo($owner, 'owner');
    }

    protected function getOwner(): ?User
    {
        $owner = auth()->user();

        return $owner instanceof User ? $owner : null;
    }
}
