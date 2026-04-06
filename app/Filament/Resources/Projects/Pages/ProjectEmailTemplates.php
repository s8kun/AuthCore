<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Models\Project;
use App\Models\ProjectEmailTemplate;
use App\Support\ProjectEmailTemplateDefaults;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectEmailTemplates extends ManageProjectSettingsPage
{
    protected static ?string $title = 'Email Templates';

    protected static ?string $breadcrumb = 'Email Templates';

    public function getSubheading(): ?string
    {
        return 'Manage the branded email content sent for OTP, password resets, welcome emails, and account claims.';
    }

    /**
     * @return array<Component|Action>
     */
    protected function getFormSchema(): array
    {
        return [
            Section::make('Template Placeholders')
                ->schema([
                    Placeholder::make('available_placeholders')
                        ->label('Available Placeholders')
                        ->content('{{ project_name }}, {{ user_email }}, {{ otp_code }}, {{ reset_link }}, {{ support_email }}, {{ expires_in }}, {{ app_name }}'),
                ]),
            Section::make('Templates')
                ->schema([
                    Repeater::make('templates')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => filled($state['type'] ?? null) ? Str::headline((string) $state['type']) : 'Template')
                        ->schema([
                            Hidden::make('type'),
                            Placeholder::make('template_type')
                                ->label('Template Type')
                                ->content(fn (callable $get): string => Str::headline((string) $get('type'))),
                            Toggle::make('is_enabled')
                                ->default(true),
                            TextInput::make('subject')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Textarea::make('html_body')
                                ->label('HTML Body')
                                ->rows(8)
                                ->required()
                                ->columnSpanFull(),
                            Textarea::make('text_body')
                                ->label('Text Body')
                                ->rows(5)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormFillData(): array
    {
        /** @var Project $project */
        $project = $this->getRecord();
        $templates = $project->emailTemplates()->get()->keyBy(fn (ProjectEmailTemplate $template): string => $template->type->value);

        return [
            'templates' => collect(ProjectEmailTemplateDefaults::for($project))
                ->map(function (array $defaultTemplate) use ($templates): array {
                    /** @var ProjectEmailTemplate|null $existingTemplate */
                    $existingTemplate = $templates->get($defaultTemplate['type']->value);

                    return [
                        'type' => $defaultTemplate['type']->value,
                        'subject' => $existingTemplate?->subject ?? $defaultTemplate['subject'],
                        'html_body' => $existingTemplate?->html_body ?? $defaultTemplate['html_body'],
                        'text_body' => $existingTemplate?->text_body ?? $defaultTemplate['text_body'],
                        'is_enabled' => $existingTemplate?->is_enabled ?? $defaultTemplate['is_enabled'],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleSettingsSave(array $data): void
    {
        /** @var Project $project */
        $project = $this->getRecord();

        foreach ($data['templates'] ?? [] as $templateData) {
            $project->emailTemplates()->updateOrCreate(
                ['type' => $templateData['type']],
                [
                    'subject' => $templateData['subject'],
                    'html_body' => $templateData['html_body'],
                    'text_body' => $templateData['text_body'] ?: null,
                    'is_enabled' => (bool) $templateData['is_enabled'],
                ],
            );
        }
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'Email templates updated.';
    }

    protected function getSettingsRecord(): Model
    {
        return $this->getRecord();
    }
}
