{{-- Story 3.2 — Presentation gallery viewer --}}
{{-- Self-contained public page: Tailwind CDN + Alpine.js, no build step --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $gallery->subject_name }} — Gallery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen" x-data="galleryViewer()">

    {{-- Header --}}
    <header class="max-w-7xl mx-auto px-4 py-8 sm:py-12">
        <h1 class="text-2xl sm:text-3xl font-light tracking-wide text-center">
            {{ $gallery->subject_name }}
        </h1>
        <p class="text-gray-400 text-sm text-center mt-2">
            {{ $images->count() }} {{ Str::plural('image', $images->count()) }}
        </p>
    </header>

    {{-- Photographer's Message --}}
    @if($share->message)
        <div class="max-w-2xl mx-auto px-4 pb-8">
            <div class="border-l-2 border-gray-700 pl-4 py-2">
                <p class="text-gray-300 text-sm leading-relaxed italic">{!! nl2br(e($share->message)) !!}</p>
            </div>
        </div>
    @endif

    {{-- Image Grid --}}
    @if($images->isEmpty())
        <div class="max-w-7xl mx-auto px-4 py-16 text-center">
            <p class="text-gray-500 text-lg">No images in this gallery yet.</p>
        </div>
    @else
        <main class="max-w-7xl mx-auto px-4 pb-16">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($images as $index => $image)
                    <div
                        class="group relative cursor-pointer overflow-hidden rounded-lg bg-gray-900 aspect-[4/3]"
                        @click="openLightbox({{ $index }})"
                    >
                        @php
                            $thumbUrl = $image->resolvedThumbnailUrl();
                            $altText  = $image->alt_text ?? $image->title ?? $image->original_filename;
                        @endphp

                        @if($thumbUrl)
                            <img
                                src="{{ $thumbUrl }}"
                                alt="{{ $altText }}"
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                loading="lazy"
                            >
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        @endif

                        {{-- Hover overlay with title --}}
                        @if($image->title || $image->caption)
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                @if($image->title)
                                    <p class="text-sm font-medium">{{ $image->title }}</p>
                                @endif
                                @if($image->caption)
                                    <p class="text-xs text-gray-300 mt-1">{{ $image->caption }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </main>
    @endif

    {{-- Lightbox --}}
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

    {{-- Alpine.js component --}}
    <script>
        function galleryViewer() {
            return {
                lightboxOpen: false,
                currentIndex: 0,
                images: @json($lightboxData),

                get currentImage() {
                    return this.images[this.currentIndex] || null;
                },

                openLightbox(index) {
                    this.currentIndex = index;
                    this.lightboxOpen = true;
                    document.body.style.overflow = 'hidden';
                },

                closeLightbox() {
                    this.lightboxOpen = false;
                    document.body.style.overflow = '';
                },

                next() {
                    this.currentIndex = (this.currentIndex + 1) % this.images.length;
                },

                prev() {
                    this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
                },
            };
        }
    </script>

</body>
</html>
