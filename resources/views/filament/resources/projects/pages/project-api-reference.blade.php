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

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <article class="min-w-0 flex-1 space-y-10">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-950/20">
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Project-scoped request and response examples using this project&apos;s current settings and custom fields.
                </p>
            </section>

            <div class="space-y-16">
                @foreach ($endpoints as $endpoint)
                    @php
                        $slug = \Illuminate\Support\Str::slug($endpoint['label']);
                        $methodClasses = $endpoint['method'] === 'GET'
                            ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/30 dark:text-sky-300'
                            : 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-300';
                    @endphp

                    <section id="{{ $slug }}" class="scroll-mt-24 border-t border-gray-200 pt-8 dark:border-white/10">
                        <div class="mb-6">
                            <div class="mb-2 flex items-center gap-3">
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
                            <div class="mb-8 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 dark:border-white/10 dark:bg-slate-900/20 dark:text-gray-300">
                                <div class="flex items-start gap-3">
                                    <x-heroicon-s-information-circle class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" />
                                    <div>{{ $endpoint['note'] }}</div>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-6 items-start">
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50/50 shadow-sm dark:border-white/10 dark:bg-slate-900/30">
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

                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50/50 shadow-sm dark:border-white/10 dark:bg-slate-900/30">
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

                        <div class="mt-8">
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
                            class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-all"
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
