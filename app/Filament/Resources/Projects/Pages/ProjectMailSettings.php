<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectMailMode;
use App\Models\Project;
use App\Models\ProjectMailSetting;
use App\Services\Auth\ProjectMailService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

class ProjectMailSettings extends ManageProjectSettingsPage
{
    protected static ?string $title = 'Mail Settings';

    protected static ?string $breadcrumb = 'Mail Settings';

    public function getSubheading(): ?string
    {
        return 'Configure project sender identity, delivery mode, SMTP credentials, and test-email verification.';
    }

    /**
     * @return array<Component|Action>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make('Delivery Mode')
                ->schema([
                    Select::make('mail_mode')
                        ->label('Mail Mode')
                        ->options([
                            ProjectMailMode::Platform->value => 'Platform Mail',
                            ProjectMailMode::CustomSmtp->value => 'Custom SMTP',
                        ])
                        ->live()
                        ->required(),
                    Placeholder::make('verification_status')
                        ->label('Verification Status')
                        ->content(fn (): string => $this->getSettingsRecord()->is_verified ? 'Verified' : 'Not verified'),
                    Placeholder::make('last_tested_at_display')
                        ->label('Last Tested')
                        ->content(fn (): string => $this->getSettingsRecord()->last_tested_at?->toDayDateTimeString() ?? 'Never'),
                    Placeholder::make('smtp_password_status')
                        ->label('Stored SMTP Password')
                        ->content(fn (): string => $this->hasStoredSmtpPassword() ? 'Configured' : 'Not set'),
                ])
                ->columns(2),
            Section::make('Sender Identity')
                ->schema([
                    TextInput::make('from_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('from_email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    TextInput::make('reply_to_email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('support_email')
                        ->email()
                        ->maxLength(255),
                ])
                ->columns(2),
            Section::make('Custom SMTP')
                ->description('These fields are only required when custom SMTP is enabled for the project.')
                ->visible(fn (Get $get): bool => $get('mail_mode') === ProjectMailMode::CustomSmtp->value)
                ->schema([
                    TextInput::make('smtp_host')
                        ->required(fn (Get $get): bool => $get('mail_mode') === ProjectMailMode::CustomSmtp->value)
                        ->maxLength(255),
                    TextInput::make('smtp_port')
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (Get $get): bool => $get('mail_mode') === ProjectMailMode::CustomSmtp->value),
                    TextInput::make('smtp_username')
                        ->required(fn (Get $get): bool => $get('mail_mode') === ProjectMailMode::CustomSmtp->value)
                        ->maxLength(255),
                    TextInput::make('smtp_password')
                        ->label('SMTP Password')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => $get('mail_mode') === ProjectMailMode::CustomSmtp->value && ! $this->hasStoredSmtpPassword())
                        ->helperText($this->hasStoredSmtpPassword()
                            ? 'Leave blank to keep the current SMTP password. Use the reset action to remove it.'
                            : 'Required when using custom SMTP.'),
                    Select::make('smtp_encryption')
                        ->options([
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                            '' => 'None',
                        ])
                        ->required(fn (Get $get): bool => $get('mail_mode') === ProjectMailMode::CustomSmtp->value),
                    TextInput::make('smtp_timeout')
                        ->numeric()
                        ->minValue(1),
                ])
                ->columns(2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormFillData(): array
    {
        return [
            ...$this->getSettingsRecord()->attributesToArray(),
            'smtp_password' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $mailSettings = $this->getSettingsRecord();

        if (! array_key_exists('smtp_password', $data) || blank($data['smtp_password'])) {
            unset($data['smtp_password']);
        }

        if (array_key_exists('smtp_password', $data)) {
            $data['smtp_password_encrypted'] = $data['smtp_password'];
        }

        unset($data['smtp_password']);

        if (($data['mail_mode'] ?? null) === ProjectMailMode::Platform->value) {
            $data['is_verified'] = false;
            $data['last_tested_at'] = null;
        }

        if (
            $mailSettings->mail_mode?->value !== ($data['mail_mode'] ?? null)
            || $mailSettings->smtp_host !== ($data['smtp_host'] ?? null)
            || $mailSettings->smtp_port !== ($data['smtp_port'] ?? null)
            || $mailSettings->smtp_username !== ($data['smtp_username'] ?? null)
            || array_key_exists('smtp_password_encrypted', $data)
            || $mailSettings->smtp_encryption !== ($data['smtp_encryption'] ?? null)
            || $mailSettings->smtp_timeout !== ($data['smtp_timeout'] ?? null)
        ) {
            $data['is_verified'] = false;
            $data['last_tested_at'] = null;
        }

        return $data;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmail')
                ->label('Send Test Email')
                ->icon(Heroicon::PaperAirplane)
                ->schema([
                    TextInput::make('recipient')
                        ->email()
                        ->required()
                        ->default((string) auth()->user()?->email),
                ])
                ->action(function (array $data): void {
                    try {
                        app(ProjectMailService::class)->sendTestEmail(
                            $this->getRecord(),
                            $data['recipient'],
                        );

                        $this->fillForm();

                        Notification::make()
                            ->success()
                            ->title('Test email sent successfully.')
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('Test email failed.')
                            ->body($exception instanceof RuntimeException
                                ? $exception->getMessage()
                                : 'Unable to send a test email with the current mail configuration.')
                            ->send();
                    }
                }),
            Action::make('resetSmtpPassword')
                ->label('Reset SMTP Password')
                ->icon(Heroicon::Key)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->hasStoredSmtpPassword())
                ->action(function (): void {
                    $this->getSettingsRecord()->forceFill([
                        'smtp_password_encrypted' => null,
                        'is_verified' => false,
                        'last_tested_at' => null,
                    ])->save();

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('SMTP password removed.')
                        ->send();
                }),
        ];
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'Mail settings updated.';
    }

    protected function getSettingsRecord(): Model
    {
        /** @var Project $project */
        $project = $this->getRecord();

        return $project->mailSettings()->firstOrCreate([], ProjectMailSetting::defaults());
    }

    private function hasStoredSmtpPassword(): bool
    {
        return filled($this->getSettingsRecord()->getRawOriginal('smtp_password_encrypted'));
    }
}
