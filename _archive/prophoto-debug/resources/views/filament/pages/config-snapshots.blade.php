<x-filament-panels::page>
    {{-- Compact Stats Bar --}}
    <div class="flex items-center justify-between gap-4 mb-4 px-4 py-3 bg-gray-50 dark:bg-white/5 rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <div class="flex items-center gap-6">
            {{-- Total --}}
            <div class="flex items-center gap-2">
                <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format(\ProPhoto\Debug\Models\ConfigSnapshot::count()) }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">Snapshots</span>
            </div>

            @php
                $latest = \ProPhoto\Debug\Models\ConfigSnapshot::latest()->first();
            @endphp

            @if($latest)
                <div class="h-6 w-px bg-gray-300 dark:bg-white/20"></div>

                {{-- Latest --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate max-w-[200px]">{{ $latest->name }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $latest->created_at->diffForHumans() }}</span>
                </div>
            @endif
        </div>

        <span class="text-xs text-gray-400 dark:text-gray-500">Click + to create</span>
    </div>

    {{-- Table --}}
    {{ $this->table }}
</x-filament-panels::page>