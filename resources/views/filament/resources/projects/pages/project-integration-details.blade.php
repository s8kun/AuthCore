<x-filament-panels::page>
    @php
        $project = $this->getProject();
        $credentials = $this->getQuickStartCredentials();
        $flowExamples = $this->getFirstSuccessfulFlowExamples();
        $featureCards = $this->getProjectBehaviorCards();
        $customFieldRows = $this->getCustomFieldRows();
        $userSchemaUrl = \App\Filament\Resources\Projects\ProjectResource::getUrl('project-user-schema', ['record' => $project]);
        $apiReferenceUrl = \App\Filament\Resources\Projects\ProjectResource::getUrl('api-reference', ['record' => $project]);
        $anchors = [
            ['href' => '#quick-start', 'label' => 'Quick Start'],
            ['href' => '#first-flow', 'label' => 'First Flow'],
            ['href' => '#project-behavior', 'label' => 'Features'],
            ['href' => '#custom-fields', 'label' => 'Custom Fields'],
        ];
    @endphp

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <article class="min-w-0 flex-1 space-y-10">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 dark:border-white/20 dark:text-gray-300">
                        {{ ucfirst($project->status->value) }} project
                    </span>
                </div>

                <p class="mt-4 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Use these values to make your first project-scoped auth request.
                </p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ $apiReferenceUrl }}" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">
                        Open API Reference
                    </a>
                    <a href="{{ $userSchemaUrl }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white/50 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-950 dark:border-white/10 dark:bg-slate-900/30 dark:text-gray-200 dark:hover:border-white/20 dark:hover:text-white">
                        Review User Schema
                    </a>
                </div>
            </section>

            <section id="quick-start" class="scroll-mt-24 pt-8">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-gray-950 dark:text-white">
                    <x-heroicon-o-bolt class="h-6 w-6 text-gray-500 dark:text-gray-400" /> Quick Start
                </h2>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Copy the credentials below into your first request.
                </p>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @foreach ($credentials as $credential)
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ $credential['label'] }}</p>
                            <p class="mb-4 break-all font-mono text-sm font-medium text-gray-950 dark:text-white">{{ $credential['value'] }}</p>
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

            <section id="first-flow" class="scroll-mt-24 pt-12">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">First Flow</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Start with <code>POST /login</code>, then call <code>GET /me</code>.
                </p>

                <div class="mt-6" x-data="{ activeTab: '{{ $flowExamples[0]['label'] }}' }">
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

                    <div class="mt-6">
                        @foreach ($flowExamples as $example)
                            <div x-show="activeTab === '{{ $example['label'] }}'" x-cloak class="animate-in fade-in slide-in-from-bottom-2 duration-300">
                                <div class="group relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50/50 shadow-sm dark:border-white/10 dark:bg-slate-900/30">
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

            <section id="project-behavior" class="scroll-mt-24 pt-12">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Features</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Current behavior for this project.
                </p>

                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($featureCards as $card)
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $card['title'] }}
                                </span>
                                <span class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:border-white/10 dark:text-gray-200">
                                    {{ $card['enabled'] ? 'Enabled' : 'Off' }}
                                </span>
                            </div>

                            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $card['summary'] }}</p>

                            @if ($card['enabled'] && isset($card['impact']))
                                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                    {{ $card['impact'] }}
                                </p>
                            @endif

                            @if (filled($card['endpoints'] ?? []))
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($card['endpoints'] as $endpoint)
                                        <span class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:border-white/10 dark:text-gray-200">
                                            {{ $endpoint }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section id="custom-fields" class="scroll-mt-24 pt-12">
                <h2 class="mb-4 text-2xl font-bold text-gray-950 dark:text-white">Custom Fields</h2>
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Fields available in the <code>custom_fields</code> payload.
                </p>

                <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/20">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">Field Key</th>
                                <th class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">Constraints</th>
                                <th class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">Visibility</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse($customFieldRows as $row)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                    <td class="px-5 py-3 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $row['key'] }}</td>
                                    <td class="max-w-sm px-5 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row['rules'] }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center rounded-full border border-gray-200 px-2.5 py-1 text-[11px] font-medium text-gray-700 dark:border-white/10 dark:text-gray-200">
                                            {{ $row['show_in_api'] ? 'Public' : 'Protected' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-6 text-center text-gray-500 dark:text-gray-400">No custom fields defined.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </article>

        <aside class="sticky top-24 hidden w-72 shrink-0 self-start lg:block">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                <h3 class="mb-4 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">On this page</h3>
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
                            :class="activeAnchor === '{{ $anchor['href'] }}' ? 'font-semibold text-gray-950 dark:text-white bg-gray-100 dark:bg-gray-800' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800'"
                            class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors"
                        >
                            {{ $anchor['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
