<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Project;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class FeatureAdoptionOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Auth Features';

    protected ?string $description = 'Enabled by project.';

    protected function getStats(): array
    {
        return [
            Stat::make('Email Verification', number_format($this->countProjectsWithFeature('email_verification_enabled'))),
            Stat::make('OTP', number_format($this->countProjectsWithFeature('otp_enabled'))),
            Stat::make('Forgot Password', number_format($this->countProjectsWithFeature('forgot_password_enabled'))),
            Stat::make('Ghost Accounts', number_format($this->countProjectsWithFeature('ghost_accounts_enabled'))),
        ];
    }

    protected function countProjectsWithFeature(string $column): int
    {
        $owner = $this->getOwner();

        if ($owner === null) {
            return 0;
        }

        return Project::query()
            ->whereBelongsTo($owner, 'owner')
            ->whereHas('authSettings', fn (Builder $query): Builder => $query->where($column, true))
            ->count();
    }

    protected function getOwner(): ?User
    {
        $owner = auth()->user();

        return $owner instanceof User ? $owner : null;
    }
}
