{{-- Story 7.4 — Editorial presentation template --}}
{{-- Asymmetric cinematic layout. Mixed aspect ratios with hero image. --}}
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
        .font-display { font-family: 'Cormorant Garamond', serif; }
        .font-body { font-family: 'Montserrat', sans-serif; }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen font-body" x-data="galleryViewer()">

    {{-- Minimal header --}}
    <header class="max-w-6xl mx-auto px-6 pt-12 pb-6">
        <h1 class="font-display text-4xl sm:text-5xl font-light tracking-wide">
            {{ $gallery->subject_name }}
        </h1>
        <p class="text-gray-500 text-xs uppercase tracking-widest mt-3 font-body">
            {{ $images->count() }} {{ Str::plural('image', $images->count()) }}
        </p>
    </header>

    @if($share->message)
        <div class="max-w-6xl mx-auto px-6 pb-8">
            <div class="border-l-2 border-gray-700 pl-4 py-2">
                <p class="text-gray-300 text-sm leading-relaxed italic font-body">{!! nl2br(e($share->message)) !!}</p>
            </div>
        </div>
    @endif

    @if($images->isEmpty())
        <div class="max-w-6xl mx-auto px-6 py-16 text-center">
            <p class="text-gray-500 text-lg font-body">No images in this gallery yet.</p>
        </div>
    @else
        <main class="max-w-6xl mx-auto px-6 pb-16">
            {{-- Hero image (first image, full width) --}}
            @if($images->count() > 0)
                @php $hero = $images->first(); @endphp
                <div
                    class="group relative cursor-pointer overflow-hidden rounded-lg bg-gray-900 aspect-[16/9] mb-4"
                    @click="openLightbox(0)"
                >
                    @if($hero->resolvedThumbnailUrl())
                        <img
                            src="{{ $hero->resolvedThumbnailUrl() }}"
                            alt="{{ $hero->alt_text ?? $hero->title ?? $hero->original_filename }}"
                            class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.02]"
                        >
                    @endif
                </div>
            @endif

            {{-- Asymmetric grid: alternating 2/3 + 1/3 and 1/3 + 2/3 --}}
            @php $remaining = $images->slice(1)->values(); @endphp
            @foreach($remaining->chunk(2) as $chunkIndex => $pair)
                <div class="grid grid-cols-3 gap-4 mb-4">
                    @foreach($pair as $pairIndex => $image)
                        @php
                            $globalIndex = 1 + ($chunkIndex * 2) + $pairIndex;
                            $isWide = ($chunkIndex % 2 === 0) ? ($pairIndex === 0) : ($pairIndex === 1);
                            $span = $isWide ? 'col-span-2' : 'col-span-1';
                            $aspect = $isWide ? 'aspect-[16/10]' : 'aspect-[3/4]';
                        @endphp
                        <div
                            class="group relative cursor-pointer overflow-hidden rounded-lg bg-gray-900 {{ $span }} {{ $aspect }}"
                            @click="openLightbox({{ $globalIndex }})"
                        >
                            @if($image->resolvedThumbnailUrl())
                                <img
                                    src="{{ $image->resolvedThumbnailUrl() }}"
                                    alt="{{ $image->alt_text ?? $image->title ?? $image->original_filename }}"
                                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                    loading="lazy"
                                >
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </main>
    @endif

    @include('prophoto-gallery::viewer.partials._lightbox', ['canDownload' => $canDownload])

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
