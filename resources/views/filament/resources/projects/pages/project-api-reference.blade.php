<x-filament-panels::page>
    @php
        $project = $this->getProject();
        $endpoints = $this->getApiReferenceEndpoints();
        $anchors = collect($endpoints)
            ->map(fn (array $endpoint): array => [
                'href' => '#'.\Illuminate\Support\Str::slug($endpoint['label']),
                'label' => $endpoint['label'],
                'method' => $endpoint['method'],
            ])
            ->all();
    @endphp

    <div class="flex flex-col lg:flex-row gap-8 items-start">
        
        <!-- Main Content -->
        <article class="flex-1 min-w-0 prose prose-slate dark:prose-invert max-w-none">
            <div class="mb-10 space-y-4">
                <span class="inline-flex rounded-full bg-sky-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-sky-950 dark:text-sky-100">
                    API Reference
                </span>
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-950 dark:text-white mt-2 mb-4">
                    {{ $project->name }} API Specification
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-300">
                    Exact request and response shapes for {{ $project->name }}. Use this page for deeper endpoint detail, feature-aware notes, and failure cases.
                </p>
                <div class="not-prose !mt-6 rounded-2xl border border-gray-200 bg-white/50 p-6 shadow-sm dark:border-white/10 dark:bg-slate-900/50">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Reference Scope</h3>
                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300 list-disc list-inside ml-4">
                        <li>All examples are project-scoped and include the <code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">X-Project-Key</code> header.</li>
                        <li>Feature notes reflect this project’s current auth settings, not a generic API assumption.</li>
                        <li>Custom field payloads mirror the active project user schema and API visibility rules.</li>
                    </ul>
                </div>
            </div>

            <div class="space-y-16">
                @foreach ($endpoints as $endpoint)
                    @php
                        $slug = \Illuminate\Support\Str::slug($endpoint['label']);
                        $methodClasses = $endpoint['method'] === 'GET'
                            ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/30 dark:text-sky-300'
                            : 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-300';
                    @endphp

                    <section id="{{ $slug }}" class="scroll-mt-24 pt-8 border-t border-gray-200 dark:border-white/10">
                        <div class="not-prose mb-6">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="rounded-md px-2.5 py-1 text-xs font-bold font-mono {{ $methodClasses }}">
                                    {{ $endpoint['method'] }}
                                </span>
                                <span class="font-mono text-base font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $endpoint['path'] }}
                                </span>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-950 dark:text-white mt-1">{{ $endpoint['label'] }}</h2>
                            <p class="mt-2 text-base text-gray-600 dark:text-gray-400">{{ $endpoint['purpose'] }}</p>
                        </div>

                        @if ($endpoint['note'])
                            <div class="not-prose mb-8 rounded-xl border border-sky-200 bg-sky-50/50 p-4 text-sm leading-relaxed text-sky-950 dark:border-sky-900/50 dark:bg-sky-900/20 dark:text-sky-200">
                                <div class="flex items-start gap-3">
                                    <x-heroicon-s-information-circle class="w-5 h-5 text-sky-500 shrink-0 mt-0.5" />
                                    <div>{{ $endpoint['note'] }}</div>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-6 not-prose items-start">
                            <!-- Request Section -->
                            <div class="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-slate-900/30 overflow-hidden shadow-sm">
                                <div class="border-b border-gray-200 dark:border-white/10 bg-gray-100/50 dark:bg-slate-800/50 px-4 py-2.5 flex justify-between items-center">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Request</span>
                                    <button 
                                        x-data="{ copied: false }" 
                                        @click="navigator.clipboard.writeText($refs.reqCode.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
                                    >
                                        <span x-show="!copied"><x-heroicon-o-clipboard-document class="w-4 h-4"/></span>
                                        <span x-show="copied" class="text-green-500"><x-heroicon-o-check class="w-4 h-4"/></span>
                                    </button>
                                </div>
                                <div class="p-4 bg-[#0d1117] overflow-x-auto text-[13px] leading-relaxed relative">
                                    <pre><code x-ref="reqCode" class="text-gray-300 font-mono">{{ $endpoint['request'] }}</code></pre>
                                </div>
                            </div>

                            <!-- Response Section -->
                            <div class="rounded-xl border border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-slate-900/30 overflow-hidden shadow-sm">
                                <div class="border-b border-gray-200 dark:border-white/10 bg-gray-100/50 dark:bg-slate-800/50 px-4 py-2.5 flex justify-between items-center">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Response</span>
                                    <button 
                                        x-data="{ copied: false }" 
                                        @click="navigator.clipboard.writeText($refs.resCode.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
                                    >
                                        <span x-show="!copied"><x-heroicon-o-clipboard-document class="w-4 h-4"/></span>
                                        <span x-show="copied" class="text-green-500"><x-heroicon-o-check class="w-4 h-4"/></span>
                                    </button>
                                </div>
                                <div class="p-4 bg-[#0d1117] overflow-x-auto text-[13px] leading-relaxed relative">
                                    <pre><code x-ref="resCode" class="text-green-400 font-mono">{{ $endpoint['response'] }}</code></pre>
                                </div>
                            </div>
                        </div>

                        <div class="not-prose mt-8">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Possible Errors</h4>
                            <ul class="space-y-2">
                                @foreach ($endpoint['failures'] as $failure)
                                    <li class="flex items-start gap-2.5 text-sm text-gray-600 dark:text-gray-400">
                                        <x-heroicon-s-x-circle class="w-4 h-4 text-red-500 mt-0.5 shrink-0" />
                                        <span>{{ $failure }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
                @endforeach
            </div>
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
                            :class="activeAnchor === '{{ $anchor['href'] }}' ? 'font-semibold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/5'"
                            class="group rounded-md flex items-center gap-3 px-3 py-2 text-sm transition-all"
                        >
                            <span class="rounded-[4px] px-1.5 py-0.5 text-[10px] font-bold font-mono 
                                {{ $anchor['method'] === 'GET' ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' }}
                            ">
                                {{ $anchor['method'] }}
                            </span>
                            {{ $anchor['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

    </div>
</x-filament-panels::page>
