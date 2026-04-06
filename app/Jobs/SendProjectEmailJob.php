<?php

namespace App\Jobs;

use App\Enums\ProjectEmailTemplateType;
use App\Models\Project;
use App\Services\Auth\ProjectMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendProjectEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $recipient,
        public readonly string $templateType,
        public readonly array $variables = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ProjectMailService $projectMailService): void
    {
        $project = Project::query()->find($this->projectId);

        if (! $project instanceof Project) {
            return;
        }

        $projectMailService->sendTemplateNow(
            $project,
            $this->recipient,
            ProjectEmailTemplateType::from($this->templateType),
            $this->variables,
        );
    }
}
