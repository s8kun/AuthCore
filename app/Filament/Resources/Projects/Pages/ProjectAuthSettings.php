<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectAuthMode;
use App\Enums\ProjectLoginIdentifierMode;
use App\Models\Project;
use App\Models\ProjectAuthSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class ProjectAuthSettings extends ManageProjectSettingsPage
{
    protected static ?string $title = 'Auth Settings';

    protected static ?string $breadcrumb = 'Auth Settings';

    public function getSubheading(): ?string
    {
        return 'Manage token lifetimes, OTP limits, password reset rules, and ghost-account behavior for this project.';
    }

    /**
     * @return array<Component|Action>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make('Core Authentication')
                ->schema([
                    Select::make('auth_mode')
                        ->options([
                            ProjectAuthMode::Standard->value => 'Standard',
                        ])
                        ->required(),
                    Select::make('login_identifier_mode')
                        ->options([
                            ProjectLoginIdentifierMode::Email->value => 'Email',
                        ])
                        ->required(),
                    Toggle::make('email_verification_enabled'),
                    Toggle::make('magic_link_enabled'),
                ])
                ->columns(2),
            Section::make('Token Lifetimes')
                ->schema([
                    TextInput::make('access_token_ttl_minutes')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('refresh_token_ttl_days')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->columns(2),
            Section::make('OTP Rules')
                ->schema([
                    Toggle::make('otp_enabled'),
                    TextInput::make('otp_length')
                        ->numeric()
                        ->minValue(4)
                        ->maxValue(12)
                        ->required(),
                    TextInput::make('otp_ttl_minutes')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('otp_max_attempts')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('otp_resend_cooldown_seconds')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('otp_daily_limit_per_email')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->columns(2),
            Section::make('Password Recovery')
                ->schema([
                    Toggle::make('forgot_password_enabled'),
                    TextInput::make('reset_password_ttl_minutes')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('forgot_password_requests_per_hour')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->columns(2),
            Section::make('Ghost Accounts')
                ->schema([
                    Toggle::make('ghost_accounts_enabled'),
                    TextInput::make('max_ghost_accounts_per_email')
                        ->numeric()
                        ->minValue(1),
                ])
                ->columns(2),
        ];
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'Auth settings updated.';
    }

    protected function getSettingsRecord(): Model
    {
        /** @var Project $project */
        $project = $this->getRecord();

        return $project->authSettings()->firstOrCreate([], ProjectAuthSetting::defaults());
    }
}
