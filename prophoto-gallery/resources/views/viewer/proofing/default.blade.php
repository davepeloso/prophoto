{{-- Story 3.4 + 4.1 — Proofing gallery viewer with unified action modal --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $gallery->subject_name }} — Proofing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen" x-data="proofingViewer()">

    {{-- Header --}}
    <header class="max-w-7xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-2xl sm:text-3xl font-light tracking-wide text-center">
            {{ $gallery->subject_name }}
        </h1>
        <p class="text-gray-400 text-sm text-center mt-2">
            {{ $share->confirmed_email }}
        </p>

        {{-- Photographer's Message --}}
        @if($share->message)
            <div class="max-w-2xl mx-auto mt-4">
                <div class="border-l-2 border-gray-700 pl-4 py-2">
                    <p class="text-gray-300 text-sm leading-relaxed italic">{!! nl2br(e($share->message)) !!}</p>
                </div>
            </div>
        @endif

        {{-- Progress bar --}}
        <div class="max-w-md mx-auto mt-6">
            <div class="flex justify-between text-xs text-gray-400 mb-1">
                <span x-text="approvalLabel"></span>
                @if($modeConfig['min_approvals'])
                    <span>Min: {{ $modeConfig['min_approvals'] }}</span>
                @endif
                <span>{{ $images->count() }} total</span>
            </div>
            <div class="w-full bg-gray-800 rounded-full h-2">
                <div
                    class="bg-emerald-500 h-2 rounded-full transition-all duration-300"
                    :style="'width: ' + progressPercent + '%'"
                ></div>
            </div>
        </div>

        {{-- Submit button --}}
        @if(!$isLocked)
            <div class="text-center mt-6">
                <button
                    @click="submitSelections()"
                    :disabled="submitting || !canSubmit"
                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium rounded-lg transition-colors"
                    :title="submitTooltip"
                >
                    <span x-show="!submitting" x-text="submitLabel"></span>
                    <span x-show="submitting" x-cloak>Submitting...</span>
                </button>
            </div>
        @else
            <div class="text-center mt-6">
                <span class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-gray-400 text-sm rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Selections submitted — this gallery is now read-only
                </span>
            </div>
        @endif
    </header>

    {{-- Image Grid --}}
    @if($images->isEmpty())
        <div class="max-w-7xl mx-auto px-4 py-16 text-center">
            <p class="text-gray-500 text-lg">No images in this gallery yet.</p>
        </div>
    @else
        <main class="max-w-7xl mx-auto px-4 pb-16">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <template x-for="(image, index) in images" :key="image.id">
                    <div
                        class="relative rounded-lg overflow-hidden bg-gray-900 cursor-pointer group"
                        @click="openModal(index)"
                    >
                        {{-- Image --}}
                        <div class="aspect-[4/3]">
                            <img
                                :src="image.thumbUrl"
                                :alt="image.alt"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                loading="lazy"
                            >
                        </div>

                        {{-- Status badge --}}
                        <div class="absolute top-2 right-2">
                            <span
                                x-show="image.status === 'approved'"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-emerald-600/90 rounded"
                            >✓ Approved</span>
                            <span
                                x-show="image.status === 'approved_pending'"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-amber-600/90 rounded"
                            >⟳ Pending</span>
                            <span
                                x-show="image.status === 'cleared'"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-gray-600/90 rounded"
                            >Cleared</span>
                        </div>

                        {{-- Rating stars overlay --}}
                        @if($modeConfig['ratings_enabled'])
                            <div class="absolute bottom-2 left-2" x-show="image.rating > 0">
                                <div class="flex gap-0.5">
                                    <template x-for="star in 5" :key="star">
                                        <span
                                            class="text-xs"
                                            :class="star <= image.rating ? 'text-yellow-400' : 'text-gray-600'"
                                        >★</span>
                                    </template>
                                </div>
                            </div>
                        @endif

                        {{-- Hover overlay --}}
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors"></div>
                    </div>
                </template>
            </div>
        </main>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Story 4.1 — Unified Action Modal                                  --}}
    {{-- Image (left/top) + Action Panel (right/bottom)                    --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div
        x-cloak
        x-show="modalOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/95"
        @keydown.escape.window="closeModal()"
        @keydown.left.window="prevImage()"
        @keydown.right.window="nextImage()"
    >
        {{-- Close button --}}
        <button @click="closeModal()" class="absolute top-4 right-4 z-10 text-white/70 hover:text-white" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="h-full flex flex-col md:flex-row">
            {{-- Left: Full image + navigation --}}
            <div class="flex-1 relative flex items-center justify-center p-4 md:p-8 min-h-0">
                {{-- Nav arrows --}}
                <button
                    x-show="images.length > 1"
                    @click.stop="prevImage()"
                    class="absolute left-2 md:left-4 top-1/2 -translate-y-1/2 z-10 text-white/40 hover:text-white transition-colors"
                    aria-label="Previous"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button
                    x-show="images.length > 1"
                    @click.stop="nextImage()"
                    class="absolute right-2 md:right-4 top-1/2 -translate-y-1/2 z-10 text-white/40 hover:text-white transition-colors"
                    aria-label="Next"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                {{-- Image --}}
                <img
                    :src="currentImage?.fullUrl"
                    :alt="currentImage?.alt"
                    class="max-w-full max-h-full object-contain"
                >

                {{-- Counter --}}
                <span class="absolute bottom-2 md:bottom-4 left-1/2 -translate-x-1/2 text-sm text-gray-400 bg-black/60 px-3 py-1 rounded-full"
                    x-text="(modalIndex + 1) + ' / ' + images.length"
                ></span>
            </div>

            {{-- Right: Action Panel --}}
            <div class="w-full md:w-96 bg-gray-900 border-t md:border-t-0 md:border-l border-gray-800 overflow-y-auto flex-shrink-0">
                <div class="p-6 space-y-5">

                    {{-- Filename + status --}}
                    <div>
                        <h3 class="text-sm font-medium text-gray-300 truncate" x-text="currentImage?.filename"></h3>
                        <div class="mt-2">
                            <span
                                x-show="currentImage?.status === 'unapproved'"
                                class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-gray-700 text-gray-300 rounded"
                            >Unapproved</span>
                            <span
                                x-show="currentImage?.status === 'approved'"
                                class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-emerald-600/80 rounded"
                            >✓ Approved</span>
                            <span
                                x-show="currentImage?.status === 'approved_pending'"
                                class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-amber-600/80 rounded"
                            >⟳ Approved — Pending</span>
                            <span
                                x-show="currentImage?.status === 'cleared'"
                                class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-gray-600 rounded"
                            >Cleared</span>
                        </div>
                    </div>

                    {{-- Star rating --}}
                    @if($modeConfig['ratings_enabled'])
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider mb-2">Rating</label>
                            <div class="flex gap-1">
                                <template x-for="star in 5" :key="star">
                                    <button
                                        @click="rateImage(currentImage, star)"
                                        class="text-2xl transition-colors focus:outline-none"
                                        :class="star <= (currentImage?.rating || 0) ? 'text-yellow-400' : 'text-gray-600 hover:text-gray-400'"
                                        :disabled="isLocked"
                                    >★</button>
                                </template>
                            </div>
                        </div>
                    @endif

                    {{-- Action buttons (only when not locked) --}}
                    @if(!$isLocked)
                        <div class="space-y-3 pt-2 border-t border-gray-800">

                            {{-- Approve --}}
                            <button
                                x-show="currentImage?.status !== 'approved' && currentImage?.status !== 'approved_pending'"
                                @click="approveImage(currentImage)"
                                :disabled="approveDisabled"
                                class="w-full px-4 py-2.5 text-sm font-medium bg-emerald-700 hover:bg-emerald-600 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg transition-colors"
                                :title="approveDisabled ? approveDisabledReason : ''"
                            >
                                <span x-show="!approveDisabled">Mark as Approved</span>
                                <span x-show="approveDisabled" x-text="approveDisabledReason"></span>
                            </button>

                            {{-- Pending dropdown --}}
                            <div>
                                <button
                                    @click="togglePendingPanel()"
                                    :disabled="pendingDisabled"
                                    class="w-full px-4 py-2.5 text-sm font-medium bg-amber-700 hover:bg-amber-600 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg transition-colors text-left flex items-center justify-between"
                                    :title="pendingDisabled ? pendingDisabledReason : ''"
                                >
                                    <span x-show="!pendingDisabled">Approved Pending →</span>
                                    <span x-show="pendingDisabled" x-text="pendingDisabledReason" class="text-xs"></span>
                                    <svg x-show="!pendingDisabled" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                {{-- Inline pending type picker --}}
                                <div x-show="pendingPanelOpen" x-transition class="mt-2 space-y-2">
                                    @foreach($pendingTypes as $pt)
                                        <button
                                            @click="selectedPendingTypeId = {{ $pt->id }}"
                                            :class="selectedPendingTypeId === {{ $pt->id }} ? 'border-white bg-gray-800' : 'border-gray-700 hover:border-gray-500'"
                                            class="w-full text-left px-3 py-2 border rounded-lg transition-colors"
                                        >
                                            <span class="block text-sm font-medium">{{ $pt->name }}</span>
                                            @if($pt->description)
                                                <span class="block text-xs text-gray-400 mt-0.5">{{ $pt->description }}</span>
                                            @endif
                                        </button>
                                    @endforeach

                                    <textarea
                                        x-model="pendingNote"
                                        placeholder="Optional note (max 500 chars)"
                                        maxlength="500"
                                        rows="2"
                                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/20"
                                    ></textarea>

                                    <div class="flex gap-2">
                                        <button
                                            @click="closePendingPanel()"
                                            class="flex-1 px-3 py-2 text-sm bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
                                        >Cancel</button>
                                        <button
                                            @click="confirmPending()"
                                            :disabled="!selectedPendingTypeId"
                                            class="flex-1 px-3 py-2 text-sm bg-amber-600 hover:bg-amber-500 disabled:opacity-50 rounded-lg transition-colors"
                                        >Confirm</button>
                                    </div>
                                </div>
                            </div>

                            {{-- Clear --}}
                            <button
                                x-show="currentImage?.status === 'approved' || currentImage?.status === 'approved_pending'"
                                @click="clearImage(currentImage)"
                                class="w-full px-4 py-2.5 text-sm font-medium bg-gray-700 hover:bg-gray-600 text-red-400 rounded-lg transition-colors"
                            >Clear Selection</button>
                        </div>
                    @endif

                    {{-- Utilities --}}
                    <div class="space-y-3 pt-2 border-t border-gray-800">
                        {{-- Download --}}
                        @if($canDownload)
                            <a
                                :href="currentImage?.fullUrl"
                                :download="currentImage?.filename"
                                class="block w-full px-4 py-2.5 text-sm font-medium bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors text-center"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download
                                </span>
                            </a>
                        @endif

                        {{-- Copy link --}}
                        <button
                            @click="copyLink()"
                            class="w-full px-4 py-2.5 text-sm font-medium bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            <span x-show="!linkCopied" class="inline-flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Copy Link
                            </span>
                            <span x-show="linkCopied" x-cloak class="text-emerald-400">Copied!</span>
                        </button>
                    </div>

                    {{-- Helper text --}}
                    @if(!$isLocked)
                        <p class="text-xs text-gray-600 text-center pt-2">Changes are saved automatically</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Alpine.js component --}}
    <script>
        function proofingViewer() {
            const token = @json($share->share_token);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            return {
                images: @json($imageData),
                modeConfig: @json($modeConfig),
                approvedCount: {{ $approvedCount }},
                pendingCount: {{ $pendingCount }},
                isLocked: {{ $isLocked ? 'true' : 'false' }},
                submitting: false,

                // Modal state
                modalOpen: false,
                modalIndex: 0,

                get currentImage() { return this.images[this.modalIndex] || null; },

                openModal(i) {
                    this.modalIndex = i;
                    this.closePendingPanel();
                    this.modalOpen = true;
                    document.body.style.overflow = 'hidden';

                    // Deep link: scroll to image on load
                    history.replaceState(null, '', '#image-' + this.images[i].id);
                },
                closeModal() {
                    this.modalOpen = false;
                    this.closePendingPanel();
                    document.body.style.overflow = '';
                    history.replaceState(null, '', window.location.pathname);
                },
                prevImage() {
                    if (!this.modalOpen) return;
                    this.modalIndex = (this.modalIndex - 1 + this.images.length) % this.images.length;
                    this.closePendingPanel();
                },
                nextImage() {
                    if (!this.modalOpen) return;
                    this.modalIndex = (this.modalIndex + 1) % this.images.length;
                    this.closePendingPanel();
                },

                // Pending picker (inline in panel)
                pendingPanelOpen: false,
                selectedPendingTypeId: null,
                pendingNote: '',

                togglePendingPanel() {
                    this.pendingPanelOpen = !this.pendingPanelOpen;
                    if (this.pendingPanelOpen) {
                        this.selectedPendingTypeId = null;
                        this.pendingNote = '';
                    }
                },
                closePendingPanel() {
                    this.pendingPanelOpen = false;
                    this.selectedPendingTypeId = null;
                    this.pendingNote = '';
                },

                // Copy link
                linkCopied: false,
                copyLink() {
                    const url = window.location.origin + '/g/' + token + '#image-' + this.currentImage.id;
                    navigator.clipboard.writeText(url).then(() => {
                        this.linkCopied = true;
                        setTimeout(() => this.linkCopied = false, 2000);
                    });
                },

                // ── Constraint computed properties ───────────────────────

                get approvalLabel() {
                    const max = this.modeConfig.max_approvals;
                    return max ? this.approvedCount + ' / ' + max + ' approved' : this.approvedCount + ' approved';
                },

                get progressPercent() {
                    const max = this.modeConfig.max_approvals || this.images.length;
                    return max ? (this.approvedCount / max * 100) : 0;
                },

                get approveDisabled() {
                    const max = this.modeConfig.max_approvals;
                    return max !== null && max !== undefined && this.approvedCount >= max;
                },

                get approveDisabledReason() {
                    const max = this.modeConfig.max_approvals;
                    return 'Selection limit reached (' + this.approvedCount + '/' + max + ')';
                },

                get pendingDisabled() {
                    // Sequential: must be approved first
                    if ((this.modeConfig.pipeline_sequential ?? true) && this.currentImage?.status !== 'approved') {
                        return true;
                    }
                    // Max pending cap
                    const max = this.modeConfig.max_pending;
                    if (max !== null && max !== undefined && this.pendingCount >= max) {
                        return true;
                    }
                    return false;
                },

                get pendingDisabledReason() {
                    if ((this.modeConfig.pipeline_sequential ?? true) && this.currentImage?.status !== 'approved') {
                        return 'Approve this image first';
                    }
                    const max = this.modeConfig.max_pending;
                    if (max !== null && max !== undefined && this.pendingCount >= max) {
                        return 'Pending limit reached';
                    }
                    return '';
                },

                get canSubmit() {
                    const min = this.modeConfig.min_approvals;
                    if (min !== null && min !== undefined && this.approvedCount < min) return false;
                    return this.approvedCount > 0 || min === null || min === undefined;
                },

                get submitTooltip() {
                    const min = this.modeConfig.min_approvals;
                    if (min !== null && min !== undefined && this.approvedCount < min) {
                        const remaining = min - this.approvedCount;
                        return 'Select at least ' + remaining + ' more image' + (remaining > 1 ? 's' : '');
                    }
                    return '';
                },

                get submitLabel() {
                    const min = this.modeConfig.min_approvals;
                    if (min !== null && min !== undefined && this.approvedCount < min) {
                        const remaining = min - this.approvedCount;
                        return 'Select ' + remaining + ' more to submit';
                    }
                    return 'Submit My Selections';
                },

                // ── Actions ──────────────────────────────────────────────

                async approveImage(image) {
                    if (!image) return;
                    const res = await this.postAction('/g/' + token + '/approve/' + image.id);
                    if (res.ok) {
                        const data = await res.json();
                        image.status = data.status;
                        this.recountApproved();
                    } else if (res.status === 422) {
                        const data = await res.json();
                        if (data.constraint) this.recountApproved();
                    }
                },

                async clearImage(image) {
                    if (!image) return;
                    const res = await this.postAction('/g/' + token + '/clear/' + image.id);
                    if (res.ok) {
                        const data = await res.json();
                        image.status = data.status;
                        image.pendingTypeId = null;
                        image.pendingNote = null;
                        this.recountApproved();
                    }
                },

                async confirmPending() {
                    if (!this.currentImage || !this.selectedPendingTypeId) return;
                    const res = await this.postAction(
                        '/g/' + token + '/pending/' + this.currentImage.id,
                        { pending_type_id: this.selectedPendingTypeId, pending_note: this.pendingNote }
                    );
                    if (res.ok) {
                        const data = await res.json();
                        this.currentImage.status = data.status;
                        this.currentImage.pendingTypeId = data.pendingTypeId;
                        this.closePendingPanel();
                        this.recountApproved();
                    } else if (res.status === 422) {
                        const data = await res.json();
                        if (data.constraint) this.recountApproved();
                    }
                },

                async rateImage(image, rating) {
                    if (!image) return;
                    const res = await this.postAction('/g/' + token + '/rate/' + image.id, { rating });
                    if (res.ok) {
                        image.rating = rating;
                    }
                },

                async submitSelections() {
                    if (!confirm('Submit your selections? This cannot be undone.')) return;
                    this.submitting = true;
                    const res = await this.postAction('/g/' + token + '/submit');
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        this.submitting = false;
                        if (res.status === 422) {
                            const data = await res.json();
                            if (data.constraint === 'min_approvals') {
                                alert(data.error);
                            }
                        }
                    }
                },

                recountApproved() {
                    this.approvedCount = this.images.filter(i =>
                        i.status === 'approved' || i.status === 'approved_pending'
                    ).length;
                    this.pendingCount = this.images.filter(i =>
                        i.status === 'approved_pending'
                    ).length;
                },

                async postAction(url, body = {}) {
                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(body),
                    });
                },

                // ── Deep link support ────────────────────────────────────
                init() {
                    const hash = window.location.hash;
                    if (hash && hash.startsWith('#image-')) {
                        const targetId = parseInt(hash.replace('#image-', ''), 10);
                        const idx = this.images.findIndex(i => i.id === targetId);
                        if (idx >= 0) {
                            this.$nextTick(() => this.openModal(idx));
                        }
                    }
                },
            };
        }
    </script>

</body>
</html>
