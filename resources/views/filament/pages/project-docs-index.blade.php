<x-filament-panels::page>
    @php
        $projects = $this->getProjects();
        $playbook = $this->getDocsPlaybook();
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <x-filament::section>
                <x-slot name="heading">Start here</x-slot>

                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Open a project, start with Developer Docs, then use API Reference when you need exact payloads.
                </p>
            </x-filament::section>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Projects</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($this->getProjectCount()) }}</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Default path</p>
                    <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white">Developer Docs first</p>
                </div>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">How to use the docs</x-slot>

            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($playbook as $index => $step)
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                            Step {{ $index + 1 }}
                        </p>
                        <h3 class="mt-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $step['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Your Projects</x-slot>

            @if ($projects->isNotEmpty())
                <div class="grid gap-4 xl:grid-cols-3">
                    @foreach ($projects as $project)
                        @php
                            $enabledFeatures = collect([
                                'Email verification' => $project->authSettings?->email_verification_enabled,
                                'OTP' => $project->authSettings?->otp_enabled,
                                'Ghost accounts' => $project->authSettings?->ghost_accounts_enabled,
                            ])->filter();
                        @endphp

                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $project->name }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $project->slug }}</p>
                                </div>

                                <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700 dark:border-white/10 dark:text-gray-200">
                                    {{ ucfirst($project->status->value) }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @forelse ($enabledFeatures as $label => $enabled)
                                    <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700 dark:border-white/10 dark:text-gray-200">
                                        {{ $label }}
                                    </span>
                                @empty
                                    <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700 dark:border-white/10 dark:text-gray-200">
                                        Default auth
                                    </span>
                                @endforelse
                            </div>

                            <dl class="mt-4 grid grid-cols-2 gap-3">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-slate-950/40">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Users</dt>
                                    <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($project->project_users_count) }}</dd>
                                </div>

                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-slate-950/40">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Requests</dt>
                                    <dd class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($project->api_request_logs_count) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
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
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-8 text-center shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">No projects yet</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        Create a project to open its Developer Docs and API Reference.
                    </p>
                    <a
                        href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('create') }}"
                        class="mt-4 inline-flex items-center justify-center rounded-xl bg-gray-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                    >
                        New Project
                    </a>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
