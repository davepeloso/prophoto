<!DOCTYPE html>
@php
    $themeMode = config('ingest.appearance.theme_mode', 'system');
    $lightAccent = config('ingest.appearance.light.accent_color', '#3b82f6');
    $lightBg = config('ingest.appearance.light.background', '#ffffff');
    $lightFg = config('ingest.appearance.light.foreground', '#0f172a');
    $darkAccent = config('ingest.appearance.dark.accent_color', '#60a5fa');
    $darkBg = config('ingest.appearance.dark.background', '#0f172a');
    $darkFg = config('ingest.appearance.dark.foreground', '#f8fafc');
    $borderRadius = config('ingest.appearance.border_radius', 8);
    
    // Convert hex to HSL for Tailwind compatibility
    function hexToHsl($hex) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        
        if ($max == $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            
            switch ($max) {
                case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6; break;
                case $g: $h = (($b - $r) / $d + 2) / 6; break;
                case $b: $h = (($r - $g) / $d + 4) / 6; break;
            }
        }
        
        return sprintf('%.1f %.1f%% %.1f%%', $h * 360, $s * 100, $l * 100);
    }
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $themeMode === 'dark' ? 'dark' : ($themeMode === 'light' ? '' : '') }}" data-theme-mode="{{ $themeMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Photo Ingest</title>

    <style>
        :root {
            /* Light mode - Ingest custom colors */
            --ingest-accent: {{ hexToHsl($lightAccent) }};
            --ingest-background: {{ hexToHsl($lightBg) }};
            --ingest-foreground: {{ hexToHsl($lightFg) }};
            --ingest-radius: {{ $borderRadius }}px;
        }

        .dark {
            /* Dark mode - Ingest custom colors */
            --ingest-accent: {{ hexToHsl($darkAccent) }};
            --ingest-background: {{ hexToHsl($darkBg) }};
            --ingest-foreground: {{ hexToHsl($darkFg) }};
        }

        /* System preference detection */
        @media (prefers-color-scheme: dark) {
            html[data-theme-mode="system"] {
                color-scheme: dark;
            }
            html[data-theme-mode="system"] {
                --ingest-accent: {{ hexToHsl($darkAccent) }};
                --ingest-background: {{ hexToHsl($darkBg) }};
                --ingest-foreground: {{ hexToHsl($darkFg) }};
            }
        }

        @media (prefers-color-scheme: light) {
            html[data-theme-mode="system"] {
                color-scheme: light;
            }
            html[data-theme-mode="system"] {
                --ingest-accent: {{ hexToHsl($lightAccent) }};
                --ingest-background: {{ hexToHsl($lightBg) }};
                --ingest-foreground: {{ hexToHsl($lightFg) }};
            }
        }
    </style>

    <script>
        // Apply system theme immediately to prevent flash
        (function() {
            const themeMode = document.documentElement.getAttribute('data-theme-mode');
            if (themeMode === 'system') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('vendor/ingest/css/app.css') }}?v={{ filemtime(public_path('vendor/ingest/css/app.css')) }}">
    <script type="module" src="{{ asset('vendor/ingest/js/app.js') }}?v={{ filemtime(public_path('vendor/ingest/js/app.js')) }}" defer></script>
    @inertiaHead
</head>
<body class="font-sans antialiased bg-background text-foreground">
    @inertia
</body>
</html>
