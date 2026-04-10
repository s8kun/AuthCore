<x-filament-panels::page>
    @php
        $project = $this->getProject();
        $authSettings = $this->getAuthSettings();
        $credentials = $this->getQuickStartCredentials();
        $checklist = $this->getQuickStartChecklist();
        $flowExamples = $this->getFirstSuccessfulFlowExamples();
        $featureCards = $this->getProjectBehaviorCards();
        $customFieldRows = $this->getCustomFieldRows();
        $otpPurposes = $this->getOtpPurposes();
        $commonErrors = $this->getCommonErrorScenarios();
        $userSchemaUrl = \App\Filament\Resources\Projects\ProjectResource::getUrl('project-user-schema', ['record' => $project]);
        $apiReferenceUrl = \App\Filament\Resources\Projects\ProjectResource::getUrl('api-reference', ['record' => $project]);
        $anchors = [
            ['href' => '#quick-start', 'label' => 'Quick Start'],
            ['href' => '#first-flow', 'label' => 'First Flow'],
            ['href' => '#project-behavior', 'label' => 'Project Behavior'],
            ['href' => '#user-contract', 'label' => 'User Contract'],
            ['href' => '#custom-fields', 'label' => 'Custom Fields'],
            ['href' => '#common-errors', 'label' => 'Common Errors'],
        ];
    @endphp

    <div class="flex flex-col lg:flex-row gap-8 items-start">

        <!-- Main Content -->
        <article class="flex-1 min-w-0 prose prose-slate dark:prose-invert max-w-none">
            <div class="mb-10 space-y-4">
                <div class="flex flex-wrap items-center gap-2 not-prose mb-4">
                    <span class="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-amber-950 dark:text-amber-100">
                        Developer Docs
                    </span>
                    <span class="rounded-full border border-gray-300 dark:border-white/20 px-3 py-1 text-xs text-gray-700 dark:text-gray-300 font-medium">
                        {{ ucfirst($project->status->value) }} project
                    </span>
                </div>
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-950 dark:text-white mt-1 mb-4">
                    Quick Start Integration
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-300">
                    Help a developer make their first successful auth request in one sitting. This page contains project-specific pieces: headers, feature flags, and token behaviors.
                </p>

                <div class="not-prose !mt-6 flex flex-wrap gap-4">
                    <a href="{{ $apiReferenceUrl }}" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">
                        Open API Reference
                    </a>
                    <a href="{{ $userSchemaUrl }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white/50 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:bg-slate-900/30 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white">
                        Review User Schema
                    </a>
                </div>
            </div>

            <!-- Quick Start -->
            <section id="quick-start" class="scroll-mt-24 pt-8">
                <h2 class="text-2xl font-bold flex items-center gap-3">
                    <x-heroicon-o-bolt class="w-6 h-6 text-amber-500" /> Quick Start
                </h2>
                <p>Use these exact values to make your first project-scoped auth request.</p>
                <div class="not-prose grid gap-4 md:grid-cols-2 mt-6">
                    @foreach ($credentials as $credential)
                        <div class="rounded-2xl border border-gray-200 bg-white/50 p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/30">
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">{{ $credential['label'] }}</p>
                            <p class="font-mono text-sm font-medium text-gray-950 dark:text-white break-all mb-4">{{ $credential['value'] }}</p>
                            <button
                                x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ addslashes($credential['value']) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 hover:text-gray-900 transition dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                <span x-show="!copied"><x-heroicon-o-clipboard class="w-4 h-4 inline" /> Copy value</span>
                                <span x-show="copied" class="text-green-600 dark:text-green-400"><x-heroicon-o-check class="w-4 h-4 inline" /> Copied!</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- First Flow (Tabs) -->
            <section id="first-flow" class="scroll-mt-24 pt-12">
                <h2 class="text-2xl font-bold">First Flow</h2>
                <p>We recommend testing a <code>POST /login</code> followed by a <code>GET /me</code> to verify project wiring before adding sign-up logic.</p>

                <div class="not-prose mt-6" x-data="{ activeTab: '{{ $flowExamples[0]['label'] }}' }">
                    <!-- Tab Headers -->
                    <div class="flex overflow-x-auto border-b border-gray-200 dark:border-white/10 hide-scrollbar gap-6">
                        @foreach ($flowExamples as $example)
                            <button
                                @click="activeTab = '{{ $example['label'] }}'"
                                :class="activeTab === '{{ $example['label'] }}' ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-700'"
                                class="whitespace-nowrap border-b-2 py-3 text-sm font-medium transition-colors"
                            >
                                {{ $example['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Tab Content -->
                    <div class="mt-6">
                        @foreach ($flowExamples as $example)
                            <div x-show="activeTab === '{{ $example['label'] }}'" x-cloak class="animate-in fade-in slide-in-from-bottom-2 duration-300">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $example['description'] }}</p>

                                <div class="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-slate-900/30 overflow-hidden shadow-sm relative group">
                                    <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            x-data="{ copied: false }"
                                            @click="navigator.clipboard.writeText($refs.code{{ str($example['label'])->slug() }}.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="rounded bg-white/90 dark:bg-gray-800/90 hover:bg-gray-100 dark:hover:bg-gray-700 p-2 text-gray-500 shadow-sm transition backdrop-blur-md"
                                        >
                                            <span x-show="!copied"><x-heroicon-o-clipboard-document class="w-4 h-4"/></span>
                                            <span x-show="copied" class="text-green-500"><x-heroicon-o-check class="w-4 h-4"/></span>
                                        </button>
                                    </div>
                                    <div class="p-5 bg-[#0d1117] overflow-x-auto text-[13px] leading-relaxed">
                                        <pre><code x-ref="code{{ str($example['label'])->slug() }}" class="text-gray-300 font-mono">{!! htmlspecialchars_decode($example['code']) !!}</code></pre>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- Project Behavior -->
            <section id="project-behavior" class="scroll-mt-24 pt-12">
                <h2 class="text-2xl font-bold">Configured Behavior</h2>
                <div class="not-prose grid gap-4 grid-cols-1 sm:grid-cols-2 mt-6">
                    @foreach ($featureCards as $card)
                        <div class="rounded-2xl border {{ $card['enabled'] ? 'border-sky-300/50 bg-sky-50/50 dark:border-sky-500/20 dark:bg-sky-900/20' : 'border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-slate-900/20' }} p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-2 h-2 rounded-full {{ $card['enabled'] ? 'bg-sky-500' : 'bg-gray-300 dark:bg-gray-600' }}"></div>
                                <span class="font-semibold text-sm {{ $card['enabled'] ? 'text-sky-900 dark:text-sky-200' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $card['title'] }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                                {{ $card['summary'] }}
                                @if($card['enabled'] && isset($card['impact']))
                                    <span class="block mt-2 font-medium text-sky-800 dark:text-sky-300">Impact: {{ $card['impact'] }}</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Rest of documentation omitted for brevity but keeping styling consistent -->
            <section id="custom-fields" class="scroll-mt-24 pt-12">
                 <h2 class="text-2xl font-bold mb-4">Custom Fields</h2>
                 <p>This project enforces these constraints on the dynamic <code>custom_fields</code> JSON blob.</p>
                 <div class="not-prose overflow-x-auto mt-6 rounded-xl border border-gray-200 dark:border-white/10 shadow-sm bg-white dark:bg-slate-900/30">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-white/10">
                            <tr>
                                <th class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">Field Key</th>
                                <th class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">Constraints</th>
                                <th class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">Visibility</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse($customFieldRows as $row)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3 font-mono text-xs text-amber-600 dark:text-amber-400">{{ $row['key'] }}</td>
                                    <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-400 max-w-sm font-mono truncate">{{ $row['rules'] }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-600 dark:text-gray-300">
                                            {{ $row['show_in_api'] ? 'Public' : 'Protected' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-6 text-center text-gray-400">No custom fields defined.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </article>

        <!-- Sticky Sidebar (Table of Contents) -->
        <aside class="hidden lg:block w-72 shrink-0 self-start sticky top-24">
            <div class="rounded-2xl border border-gray-200 bg-white/50 p-5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/50">
                <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">On this page</h3>
                <nav class="space-y-1" x-data="{ activeAnchor: '' }" @scroll.window.passive="
                    let sections = document.querySelectorAll('section[id]');
                    for (let i = sections.length - 1; i >= 0; i--) {
                        if (window.scrollY >= sections[i].offsetTop - 150) {
                            activeAnchor = '#' + sections[i].id;
                            break;
                        }
                    }
                ">
                    @foreach ($anchors as $anchor)
                        <a
                            href="{{ $anchor['href'] }}"
                            :class="activeAnchor === '{{ $anchor['href'] }}' ? 'font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/5'"
                            class="group rounded-md flex items-center gap-3 px-3 py-2 text-sm transition-colors"
                        >
                            {{ $anchor['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

    </div>
</x-filament-panels::page>
