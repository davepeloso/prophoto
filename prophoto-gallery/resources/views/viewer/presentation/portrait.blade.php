{{-- Story 7.4 — Portrait presentation template --}}
{{-- Two-column tall cards. Intimate & warm. Best for headshots/portraits. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $gallery->subject_name }} — Gallery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('prophoto-gallery::viewer.partials._fonts', ['fontsUrl' => $fontsUrl ?? null])
    <style>
        [x-cloak] { display: none !important; }
        .font-display { font-family: 'Playfair Display', serif; }
        .font-body { font-family: 'Lato', sans-serif; }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen font-body" x-data="galleryViewer()">

    {{-- Header --}}
    <header class="max-w-5xl mx-auto px-6 py-12 sm:py-16 text-center">
        <h1 class="font-display text-3xl sm:text-4xl font-normal tracking-wide">
            {{ $gallery->subject_name }}
        </h1>
        <p class="text-gray-400 text-sm mt-3">
            {{ $images->count() }} {{ Str::plural('image', $images->count()) }}
        </p>
    </header>

    {{-- Photographer's Message --}}
    @if($share->message)
        <div class="max-w-2xl mx-auto px-6 pb-10">
            <div class="border-l-2 border-gray-700 pl-4 py-2">
                <p class="text-gray-300 text-sm leading-relaxed italic font-body">{!! nl2br(e($share->message)) !!}</p>
            </div>
        </div>
    @endif

    {{-- Two-column tall card grid --}}
    @if($images->isEmpty())
        <div class="max-w-5xl mx-auto px-6 py-16 text-center">
            <p class="text-gray-500 text-lg font-body">No images in this gallery yet.</p>
        </div>
    @else
        <main class="max-w-5xl mx-auto px-6 pb-16">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                @foreach($images as $index => $image)
                    <div
                        class="group relative cursor-pointer overflow-hidden rounded-lg bg-gray-900 aspect-[3/4]"
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
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                            >
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        @endif

                        @if($image->title)
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-5 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <p class="font-display text-sm">{{ $image->title }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </main>
    @endif

    {{-- Shared lightbox --}}
    @include('prophoto-gallery::viewer.partials._lightbox', ['canDownload' => $canDownload])

    {{-- Alpine.js component --}}
    <script>
        function galleryViewer() {
            return {
                lightboxOpen: false,
                currentIndex: 0,
                images: @json($lightboxData),
                get currentImage() { return this.images[this.currentIndex] || null; },
                openLightbox(index) { this.currentIndex = index; this.lightboxOpen = true; document.body.style.overflow = 'hidden'; },
                closeLightbox() { this.lightboxOpen = false; document.body.style.overflow = ''; },
                next() { this.currentIndex = (this.currentIndex + 1) % this.images.length; },
                prev() { this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length; },
            };
        }
    </script>
</body>
</html>
