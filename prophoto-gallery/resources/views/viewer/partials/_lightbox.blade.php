{{-- Story 7.4 — Shared lightbox Alpine.js component --}}
{{-- Usage: @include('prophoto-gallery::viewer.partials._lightbox', ['canDownload' => $canDownload]) --}}
{{-- Requires Alpine.js x-data parent with: lightboxOpen, currentIndex, images[], currentImage, openLightbox(), closeLightbox(), next(), prev() --}}

<div
    x-cloak
    x-show="lightboxOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center"
    @keydown.escape.window="closeLightbox()"
    @keydown.left.window="prev()"
    @keydown.right.window="next()"
>
    {{-- Close button --}}
    <button
        @click="closeLightbox()"
        class="absolute top-4 right-4 z-10 text-white/70 hover:text-white transition-colors"
        aria-label="Close lightbox"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    {{-- Previous button --}}
    <button
        x-show="images.length > 1"
        @click="prev()"
        class="absolute left-4 top-1/2 -translate-y-1/2 z-10 text-white/50 hover:text-white transition-colors"
        aria-label="Previous image"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    {{-- Next button --}}
    <button
        x-show="images.length > 1"
        @click="next()"
        class="absolute right-4 top-1/2 -translate-y-1/2 z-10 text-white/50 hover:text-white transition-colors"
        aria-label="Next image"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </button>

    {{-- Image display --}}
    <div class="max-w-5xl max-h-[85vh] px-16">
        <img
            :src="currentImage?.fullUrl"
            :alt="currentImage?.alt"
            class="max-w-full max-h-[85vh] object-contain mx-auto"
        >
    </div>

    {{-- Bottom bar: caption + counter + download --}}
    <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/80 to-transparent p-6">
        <div class="max-w-5xl mx-auto flex items-end justify-between">
            <div>
                <p x-show="currentImage?.title" x-text="currentImage?.title" class="text-sm font-medium"></p>
                <p x-show="currentImage?.caption" x-text="currentImage?.caption" class="text-xs text-gray-400 mt-1"></p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-400" x-text="(currentIndex + 1) + ' / ' + images.length"></span>

                @if($canDownload)
                    <a
                        :href="currentImage?.fullUrl"
                        :download="currentImage?.filename"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white/10 hover:bg-white/20 rounded transition-colors"
                        @click.stop
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
