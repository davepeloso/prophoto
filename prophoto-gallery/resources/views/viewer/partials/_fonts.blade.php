{{-- Story 7.4 — Google Fonts loader partial --}}
{{-- Usage: @include('prophoto-gallery::viewer.partials._fonts', ['fontsUrl' => $fontsUrl]) --}}
@if(!empty($fontsUrl))
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $fontsUrl }}" rel="stylesheet">
@endif
