<x-filament-panels::page>
    @php
        $projects = $this->getProjects();
        $playbook = $this->getDocsPlaybook();
        $conceptCards = $this->getApiConceptCards();
        $checklist = $this->getIntegrationChecklist();
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-[2rem] border border-amber-200/70 bg-[linear-gradient(135deg,rgba(251,191,36,0.18),rgba(255,255,255,0.94),rgba(14,165,233,0.12))] px-6 py-6 shadow-sm dark:border-amber-500/20 dark:bg-[linear-gradient(135deg,rgba(245,158,11,0.16),rgba(15,23,42,0.92),rgba(14,165,233,0.16))] sm:px-8 sm:py-8">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.9fr)]">
                <div class="space-y-4">
                    <span class="inline-flex rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-amber-950 dark:text-amber-100">
                        Docs Entry Point
                    </span>

                    <div class="space-y-3">
                        <h2 class="max-w-3xl text-3xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-4xl">
                            Project-scoped auth docs that explain the product before they dump raw details.
                        </h2>

                        <p class="max-w-3xl text-sm leading-6 text-gray-700 dark:text-gray-200 sm:text-base">
                            This sidebar section gives developers a clear place to learn: what the platform expects, what changes
                            per project, and where to find the fastest path to a successful integration.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-950/45">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">Projects</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($this->getProjectCount()) }}</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                            Each project has its own API key, feature flags, and user schema.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-950/45">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">Best Starting Point</p>
                        <p class="mt-3 text-base font-semibold text-gray-950 dark:text-white">Developer Docs</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                            Open a project’s docs page first, then move to API Reference when you need exact contracts.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)]">
            <x-filament::section>
                <x-slot name="heading">Docs Playbook</x-slot>
                <x-slot name="description">Use this sequence to move from orientation to project-specific implementation quickly.</x-slot>

                <div class="grid gap-4">
                    @foreach ($playbook as $index => $step)
                        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/30">
                            <div class="flex gap-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-sm font-semibold text-amber-950 dark:text-amber-100">
                                    {{ $index + 1 }}
                                </span>

                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $step['title'] }}</p>
                                    <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $step['description'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">API Concepts</x-slot>
                <x-slot name="description">These are the product-level ideas developers should understand before touching client code.</x-slot>

                <div class="grid gap-4">
                    @foreach ($conceptCards as $card)
                        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/30">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $card['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $card['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Integration Checklist</x-slot>
            <x-slot name="description">A short product-facing checklist you can use before wiring a new environment or client application.</x-slot>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($checklist as $index => $item)
                    <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/30">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">Step {{ $index + 1 }}</p>
                        <p class="mt-3 text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $item }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Your Projects</x-slot>
            <x-slot name="description">Jump straight into the docs surfaces that matter for the projects you are actively integrating.</x-slot>

            @if ($projects->isNotEmpty())
                <div class="grid gap-5 xl:grid-cols-3">
                    @foreach ($projects as $project)
                        <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-950/30">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $project->name }}</h3>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $project->slug }}</p>
                                </div>

                                <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700 dark:border-white/10 dark:text-gray-200">
                                    {{ ucfirst($project->status->value) }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->authSettings?->email_verification_enabled ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-100' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' }}">
                                    Email Verification {{ $project->authSettings?->email_verification_enabled ? 'On' : 'Off' }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->authSettings?->otp_enabled ? 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-100' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' }}">
                                    OTP {{ $project->authSettings?->otp_enabled ? 'On' : 'Off' }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $project->authSettings?->ghost_accounts_enabled ? 'bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-100' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' }}">
                                    Ghost {{ $project->authSettings?->ghost_accounts_enabled ? 'On' : 'Off' }}
                                </span>
                            </div>

                            <dl class="mt-5 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-slate-950/40">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">Users</dt>
                                    <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($project->project_users_count) }}</dd>
                                </div>

                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-slate-950/40">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">Requests</dt>
                                    <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($project->api_request_logs_count) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                <a
                                    href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('integration', ['record' => $project]) }}"
                                    class="inline-flex items-center justify-center rounded-xl bg-gray-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                                >
                                    Developer Docs
                                </a>

                                <a
                                    href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('api-reference', ['record' => $project]) }}"
                                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:bg-slate-950/30 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white"
                                >
                                    API Reference
                                </a>

                                <a
                                    href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('auth-settings', ['record' => $project]) }}"
                                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:bg-slate-950/30 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white sm:col-span-2"
                                >
                                    Auth Settings
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-[2rem] border border-dashed border-gray-300 bg-white/70 px-6 py-8 text-center shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">No projects yet</h3>
                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        Create your first project, then come back here to open project-specific Developer Docs and API Reference pages.
                    </p>
                    <a
                        href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('create') }}"
                        class="mt-5 inline-flex items-center justify-center rounded-xl bg-gray-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                    >
                        Create Project
                    </a>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
