<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Project;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class FeatureAdoptionOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Feature Adoption';

    protected ?string $description = 'See which project-level auth flows are enabled across your account.';

    protected function getStats(): array
    {
        return [
            Stat::make('Email Verification', number_format($this->countProjectsWithFeature('email_verification_enabled')))
                ->description('Projects that require email verification before normal sign-in')
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('success'),
            Stat::make('OTP', number_format($this->countProjectsWithFeature('otp_enabled')))
                ->description('Projects with one-time-password delivery and verification')
                ->descriptionIcon(Heroicon::OutlinedBolt)
                ->icon(Heroicon::OutlinedBolt)
                ->color('warning'),
            Stat::make('Forgot Password', number_format($this->countProjectsWithFeature('forgot_password_enabled')))
                ->description('Projects with password recovery enabled')
                ->descriptionIcon(Heroicon::OutlinedBookOpen)
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('primary'),
            Stat::make('Ghost Accounts', number_format($this->countProjectsWithFeature('ghost_accounts_enabled')))
                ->description('Projects that support invite-first account creation')
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray'),
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
