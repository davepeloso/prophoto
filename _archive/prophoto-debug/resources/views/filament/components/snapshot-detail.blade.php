<div class="space-y-6">
    @if($snapshot->description)
    <div class="flex items-start gap-3 p-4 rounded-lg bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-primary-500 flex-shrink-0 mt-0.5" style="width: 20px; height: 20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
        </svg>
        <p class="text-sm text-primary-700 dark:text-primary-300 italic">{{ $snapshot->description }}</p>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Thumbnail Settings --}}
        <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-white/10">
            <div class="flex items-center gap-2 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-amber-500" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                <h4 class="font-semibold text-gray-900 dark:text-white">Thumbnail Settings</h4>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Quality</dt>
                    <dd>
                        <span class="inline-flex items-center rounded-md bg-amber-50 dark:bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-400 ring-1 ring-inset ring-amber-600/20 dark:ring-amber-500/30">
                            {{ $snapshot->thumbnail_quality ?? 'N/A' }}%
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Dimensions</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $snapshot->thumbnail_dimensions ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Preview Settings --}}
        <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-white/10">
            <div class="flex items-center gap-2 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-blue-500" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <h4 class="font-semibold text-gray-900 dark:text-white">Preview Settings</h4>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Quality</dt>
                    <dd>
                        <span class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-500/10 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 ring-1 ring-inset ring-blue-600/20 dark:ring-blue-500/30">
                            {{ $snapshot->preview_quality ?? 'N/A' }}%
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Max Dimension</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $snapshot->preview_max_dimension ?? 'N/A' }}px</dd>
                </div>
            </dl>
        </div>

        {{-- ExifTool Settings --}}
        <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-white/10">
            <div class="flex items-center gap-2 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-purple-500" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <h4 class="font-semibold text-gray-900 dark:text-white">ExifTool Settings</h4>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between items-center gap-2">
                    <dt class="text-gray-500 dark:text-gray-400">Binary</dt>
                    <dd class="font-mono text-xs text-gray-700 dark:text-gray-300 truncate max-w-[180px]" title="{{ $snapshot->exiftool_binary ?? 'N/A' }}">
                        {{ $snapshot->exiftool_binary ?? 'N/A' }}
                    </dd>
                </div>
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Speed Mode</dt>
                    <dd>
                        @php
                            $speedColor = match($snapshot->exiftool_speed_mode ?? '') {
                                'fast2' => 'green',
                                'fast' => 'blue',
                                'full' => 'amber',
                                default => 'gray'
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-md bg-{{ $speedColor }}-50 dark:bg-{{ $speedColor }}-500/10 px-2 py-1 text-xs font-medium text-{{ $speedColor }}-700 dark:text-{{ $speedColor }}-400 ring-1 ring-inset ring-{{ $speedColor }}-600/20 dark:ring-{{ $speedColor }}-500/30">
                            {{ $snapshot->exiftool_speed_mode ?? 'N/A' }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Queue Settings --}}
        <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-white/10">
            <div class="flex items-center gap-2 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-green-500" style="width: 20px; height: 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                </svg>
                <h4 class="font-semibold text-gray-900 dark:text-white">Queue Settings</h4>
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Connection</dt>
                    <dd>
                        <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-500/10 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20 dark:ring-green-500/30">
                            {{ $snapshot->queue_connection ?? 'N/A' }}
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Worker Count</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $snapshot->worker_count ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Environment Variables --}}
    @if($snapshot->environment)
    <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-4 ring-1 ring-gray-200 dark:ring-white/10">
        <div class="flex items-center gap-2 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-gray-500" style="width: 20px; height: 20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <h4 class="font-semibold text-gray-900 dark:text-white">Environment Variables</h4>
        </div>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            @foreach($snapshot->environment as $key => $value)
            <div class="flex items-center justify-between gap-2 bg-white dark:bg-gray-800 rounded-lg px-3 py-2">
                <dt class="text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $key }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white font-mono text-xs">{{ $value }}</dd>
            </div>
            @endforeach
        </dl>
    </div>
    @endif

    {{-- Raw Config Data (collapsible) --}}
    <details class="bg-gray-50 dark:bg-white/5 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden">
        <summary class="font-semibold text-gray-900 dark:text-white cursor-pointer p-4 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-gray-500" style="width: 20px; height: 20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
            </svg>
            Raw Configuration Data
        </summary>
        <pre class="text-xs bg-gray-900 text-gray-100 p-4 overflow-x-auto border-t border-gray-200 dark:border-white/10">{{ json_encode($snapshot->config_data, JSON_PRETTY_PRINT) }}</pre>
    </details>

    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-500">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
        </svg>
        Created: {{ $snapshot->created_at->format('F j, Y \a\t g:i A') }}
    </div>
</div>