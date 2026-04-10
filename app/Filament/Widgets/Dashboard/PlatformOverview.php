<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ApiRequestLog;
use App\Models\AuthEventLog;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PlatformOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Platform Overview';

    protected ?string $description = 'Track control-plane volume, project activity, and the fastest next step.';

    protected function getStats(): array
    {
        $latestProject = $this->getLatestProject();

        return [
            Stat::make('Projects', number_format($this->getProjectsCount()))
                ->description('Projects owned by your account')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('warning')
                ->url(ProjectResource::getUrl('index')),
            Stat::make('Project Users', number_format($this->getProjectUsersCount()))
                ->description('Active project users across your workspace')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->icon(Heroicon::OutlinedUsers)
                ->color('success')
                ->url(ProjectResource::getUrl('index')),
            Stat::make('Requests (24h)', number_format($this->getRecentRequestCount()))
                ->description('Project-scoped API traffic in the last day')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->icon(Heroicon::OutlinedChartBar)
                ->color('primary'),
            Stat::make('Auth Events (24h)', number_format($this->getRecentAuthEventCount()))
                ->description('Recent auth lifecycle activity')
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray'),
            Stat::make('Continue Setup', $latestProject?->name ?? 'Create your first project')
                ->description($latestProject
                    ? 'Jump back into developer docs for the most recently updated project'
                    : 'Create a project, then use the docs flow to ship your first integration')
                ->descriptionIcon(Heroicon::OutlinedBookOpen)
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('primary')
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
