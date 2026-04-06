<x-filament-panels::page>
    @php
        $queueStatus = $this->getQueueStatus();
        $worker = $queueStatus['worker'] ?? [];
        $jobs = $queueStatus['jobs'] ?? [];
        $horizon = $queueStatus['horizon'] ?? [];
    @endphp

    {{-- System Health Bar --}}
    <div class="mb-4 px-4 py-3 bg-gray-50 dark:bg-white/5 rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                {{-- Worker Status --}}
                @switch($worker['status'] ?? 'unknown')
                    @case('processing')
                        <x-filament::badge color="success" icon="heroicon-m-play-circle">
                            Processing
                        </x-filament::badge>
                        @break
                    @case('idle')
                        <x-filament::badge color="info" icon="heroicon-m-clock">
                            Idle
                        </x-filament::badge>
                        @break
                    @case('stalled')
                        <x-filament::badge color="danger" icon="heroicon-m-stop-circle">
                            Stalled
                        </x-filament::badge>
                        @break
                    @case('pending')
                        <x-filament::badge color="warning" icon="heroicon-m-arrow-path">
                            Pending
                        </x-filament::badge>
                        @break
                    @default
                        <x-filament::badge color="gray" icon="heroicon-m-question-mark-circle">
                            Unknown
                        </x-filament::badge>
                @endswitch

                {{-- Pending Jobs --}}
                <x-filament::badge color="gray" icon="heroicon-m-queue-list">
                    {{ $jobs['pending'] ?? 0 }} pending
                </x-filament::badge>

                @if(($jobs['ingest_pending'] ?? 0) > 0)
                    <x-filament::badge color="info">
                        {{ $jobs['ingest_pending'] }} ingest
                    </x-filament::badge>
                @endif

                {{-- Failed Jobs --}}
                @if(($jobs['failed'] ?? 0) > 0)
                    <x-filament::badge color="danger" icon="heroicon-m-exclamation-triangle">
                        {{ $jobs['failed'] }} failed
                    </x-filament::badge>
                @else
                    <x-filament::badge color="success" icon="heroicon-m-check-badge">
                        No failures
                    </x-filament::badge>
                @endif

                {{-- Horizon Status (if installed) --}}
                @if($horizon['installed'] ?? false)
                    @if($horizon['status'] === 'running')
                        <x-filament::badge color="purple" icon="heroicon-m-bolt">
                            Horizon
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="gray" icon="heroicon-m-bolt-slash">
                            Horizon inactive
                        </x-filament::badge>
                    @endif
                @endif
            </div>

            {{-- Driver Info --}}
            <div class="flex items-center gap-2">
                <x-filament::badge color="gray" size="sm">
                    {{ ucfirst($jobs['driver'] ?? 'unknown') }} driver
                </x-filament::badge>
                <x-filament::badge color="gray" size="sm">
                    Auto-refresh 10s
                </x-filament::badge>
            </div>
        </div>
    </div>

    {{-- Trace Stats Bar --}}
    <div class="flex flex-wrap items-center gap-3 mb-4 px-4 py-3 bg-gray-50 dark:bg-white/5 rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Traces:</span>

        <x-filament::badge color="gray" icon="heroicon-m-document-text">
            {{ number_format(\ProPhoto\Debug\Models\IngestTrace::count()) }} Total
        </x-filament::badge>

        <x-filament::badge color="success" icon="heroicon-m-check-circle">
            {{ number_format(\ProPhoto\Debug\Models\IngestTrace::where('success', true)->count()) }} Pass
        </x-filament::badge>

        <x-filament::badge color="danger" icon="heroicon-m-x-circle">
            {{ number_format(\ProPhoto\Debug\Models\IngestTrace::where('success', false)->count()) }} Fail
        </x-filament::badge>
    </div>

    {{-- Table --}}
    {{ $this->table }}
</x-filament-panels::page>