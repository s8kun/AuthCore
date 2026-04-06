<x-filament-panels::page>
    @php
        $project = $this->getProject();
        $recentLogs = $this->getRecentLogs();
    @endphp

    <div class="grid gap-6 xl:grid-cols-3">
        <x-filament::section class="xl:col-span-2">
            <x-slot name="heading">Connection Details</x-slot>
            <x-slot name="description">Use these values in every project-scoped auth request.</x-slot>

            <dl class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Base API URL</dt>
                    <dd class="mt-2 break-all font-mono text-sm">{{ $this->getBaseApiUrl() }}</dd>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Project Key Header</dt>
                    <dd class="mt-2 break-all font-mono text-sm">{{ $project->api_key }}</dd>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Authorization Header</dt>
                    <dd class="mt-2 font-mono text-sm">Bearer &lt;plain-text-token&gt;</dd>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Token Expiration</dt>
                    <dd class="mt-2 text-sm">{{ $project->authSettings?->access_token_ttl_minutes ?? config('sanctum.expiration') }} minutes</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Project Snapshot</x-slot>

            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Owner</dt>
                    <dd class="mt-1 text-sm">{{ $project->owner?->email }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Rate Limit</dt>
                    <dd class="mt-1 text-sm">{{ $project->rate_limit }} requests / minute</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1 text-sm">{{ ucfirst($project->status->value) }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Project Users</dt>
                    <dd class="mt-1 text-sm">{{ $project->project_users_count }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Logged Requests</dt>
                    <dd class="mt-1 text-sm">{{ $project->api_request_logs_count }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Auth Events</dt>
                    <dd class="mt-1 text-sm">{{ $project->auth_event_logs_count }}</dd>
                </div>
            </dl>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Sample Requests And Responses</x-slot>
        <x-slot name="description">These examples match the actual phase 3 and phase 4 API behavior in this application.</x-slot>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ([
                'Register' => [
                    'request' => $this->renderExampleTemplate($this->getRegisterRequestExample()),
                    'response' => $this->getRegisterResponseExample(),
                ],
                'Login' => [
                    'request' => $this->renderExampleTemplate($this->getLoginRequestExample()),
                    'response' => $this->getLoginResponseExample(),
                ],
                'Me' => [
                    'request' => $this->renderExampleTemplate($this->getMeRequestExample()),
                    'response' => $this->getMeResponseExample(),
                ],
                'Logout' => [
                    'request' => $this->renderExampleTemplate($this->getLogoutRequestExample()),
                    'response' => $this->getLogoutResponseExample(),
                ],
                'Refresh' => [
                    'request' => $this->renderExampleTemplate($this->getRefreshRequestExample()),
                    'response' => $this->getLoginResponseExample(),
                ],
                'Forgot Password' => [
                    'request' => $this->renderExampleTemplate($this->getForgotPasswordRequestExample()),
                    'response' => $this->getAcceptedResponseExample(),
                ],
                'Reset Password' => [
                    'request' => $this->renderExampleTemplate($this->getResetPasswordRequestExample()),
                    'response' => $this->getResetPasswordResponseExample(),
                ],
                'Send OTP' => [
                    'request' => $this->renderExampleTemplate($this->getSendOtpRequestExample()),
                    'response' => $this->getAcceptedResponseExample(),
                ],
                'Verify OTP' => [
                    'request' => $this->renderExampleTemplate($this->getVerifyOtpRequestExample()),
                    'response' => $this->getVerifyOtpResponseExample(),
                ],
                'Claim Ghost Account' => [
                    'request' => $this->renderExampleTemplate($this->getClaimGhostAccountRequestExample()),
                    'response' => $this->getLoginResponseExample(),
                ],
            ] as $label => $example)
                <div class="space-y-4 rounded-2xl border border-gray-200 p-5 dark:border-white/10">
                    <div>
                        <h3 class="text-base font-semibold">{{ $label }}</h3>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Request</p>
                        <pre class="overflow-x-auto rounded-xl bg-gray-950 p-4 text-xs leading-6 text-white"><code>{{ $example['request'] }}</code></pre>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">Response</p>
                        <pre class="overflow-x-auto rounded-xl bg-gray-950 p-4 text-xs leading-6 text-white"><code>{{ $example['response'] }}</code></pre>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Recent Request Activity</x-slot>
        <x-slot name="description">Latest project-scoped API requests captured by the observability middleware.</x-slot>

        <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Endpoint</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Logged At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white text-sm dark:divide-white/10 dark:bg-transparent">
                    @forelse ($recentLogs as $log)
                        <tr class="space-x-10">
                            <td class="px-4 py-3 font-mono text-xs">{{ $log->endpoint }}</td>
                            <td class="px-4 py-3">{{ $log->method }}</td>
                            <td class="px-4 py-3">{{ $log->ip_address ?? 'Unknown' }}</td>
                            <td class="px-4 py-3">{{ $log->created_at?->toDayDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                No project-scoped requests have been logged yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
