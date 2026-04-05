# ProPhoto Ingest - Theming & Appearance System

## Overview

The ProPhoto Ingest package includes a robust theming system that supports light mode, dark mode, and automatic system preference detection. All appearance settings are configurable via environment variables and can be overridden via database settings.

## Features

- ✅ **Light/Dark/System Modes** - Three theme modes with automatic OS preference detection
- ✅ **Customizable Colors** - Separate color schemes for light and dark modes
- ✅ **CSS Variable Injection** - Server-side rendering prevents theme flash
- ✅ **User Toggle Component** - Built-in UI control for theme switching
- ✅ **Production Ready** - Optimized for performance with HSL color conversion
- ✅ **Database Override Support** - Runtime configuration via settings table

## Configuration

### Environment Variables

Add these to your Laravel application's `.env` file:

```env
# Theme Mode: 'light', 'dark', or 'system'
INGEST_THEME_MODE=system

# Light Mode Colors
INGEST_LIGHT_ACCENT=#3b82f6
INGEST_LIGHT_BG=#ffffff
INGEST_LIGHT_FG=#0f172a

# Dark Mode Colors
INGEST_DARK_ACCENT=#60a5fa
INGEST_DARK_BG=#0f172a
INGEST_DARK_FG=#f8fafc

# Shared Settings
INGEST_RADIUS=8
INGEST_SPACING=medium
```

### Database Override

You can override appearance settings at runtime by inserting into the `ingest_settings` table:

```php
use Prophoto\Ingest\Models\IngestSetting;

IngestSetting::updateOrCreate(
    ['key' => 'appearance.theme_mode'],
    ['value' => 'dark']
);

IngestSetting::updateOrCreate(
    ['key' => 'appearance.dark.accent_color'],
    ['value' => '#8b5cf6']
);
```

## Architecture

### 1. Configuration Layer (`config/ingest.php`)

Defines the appearance structure with defaults and environment variable fallbacks:

```php
'appearance' => [
    'theme_mode' => env('INGEST_THEME_MODE', 'system'),
    'light' => [
        'accent_color' => env('INGEST_LIGHT_ACCENT', '#3b82f6'),
        'background' => env('INGEST_LIGHT_BG', '#ffffff'),
        'foreground' => env('INGEST_LIGHT_FG', '#0f172a'),
    ],
    'dark' => [
        'accent_color' => env('INGEST_DARK_ACCENT', '#60a5fa'),
        'background' => env('INGEST_DARK_BG', '#0f172a'),
        'foreground' => env('INGEST_DARK_FG', '#f8fafc'),
    ],
    'border_radius' => (int) env('INGEST_RADIUS', 8),
    'spacing_scale' => env('INGEST_SPACING', 'medium'),
],
```

### 2. Service Provider (`IngestServiceProvider.php`)

Loads database overrides during application boot:

```php
protected function overrideConfig(array $dbSettings): void
{
    if (isset($dbSettings['appearance.theme_mode'])) {
        config(['ingest.appearance.theme_mode' => $dbSettings['appearance.theme_mode']]);
    }
    
    if (isset($dbSettings['appearance.light.accent_color'])) {
        config(['ingest.appearance.light.accent_color' => $dbSettings['appearance.light.accent_color']]);
    }
    // ... more overrides
}
```

### 3. Blade Template (`resources/views/app.blade.php`)

Injects CSS variables server-side with hex-to-HSL conversion:

```blade
@php
    $themeMode = config('ingest.appearance.theme_mode', 'system');
    $lightAccent = config('ingest.appearance.light.accent_color', '#3b82f6');
    // ... load other colors
    
    function hexToHsl($hex) {
        // Converts hex colors to HSL format for Tailwind
    }
@endphp

<style>
    :root {
        --ingest-accent: {{ hexToHsl($lightAccent) }};
        --ingest-background: {{ hexToHsl($lightBg) }};
        --ingest-foreground: {{ hexToHsl($lightFg) }};
    }
    
    .dark {
        --ingest-accent: {{ hexToHsl($darkAccent) }};
        --ingest-background: {{ hexToHsl($darkBg) }};
        --ingest-foreground: {{ hexToHsl($darkFg) }};
    }
</style>
```

### 4. CSS Layer (`resources/css/app.css`)

Maps ingest variables to Tailwind's semantic color system:

```css
:root {
    --background: var(--ingest-background);
    --foreground: var(--ingest-foreground);
    --primary: var(--ingest-accent);
    --accent: var(--ingest-accent);
    --ring: var(--ingest-accent);
    --radius: var(--ingest-radius);
}
```

### 5. Theme Toggle Component (`ThemeToggle.tsx`)

React component for user-controlled theme switching:

```tsx
import { ThemeToggle } from './ThemeToggle';

// Usage in any component
<ThemeToggle />
```

## How It Works

### Server-Side Rendering (SSR)

1. **Config Load** - Laravel loads appearance config from file + database overrides
2. **Blade Processing** - Template converts hex colors to HSL and injects CSS variables
3. **HTML Output** - Browser receives fully-themed HTML with no flash
4. **System Detection** - JavaScript applies system preference for 'system' mode

### Client-Side Interaction

1. **User Clicks Toggle** - Cycles through light → dark → system
2. **State Update** - React updates `data-theme-mode` attribute
3. **CSS Application** - Browser applies corresponding CSS variables
4. **Persistence** - Theme preference stored in component state

### Color Format Flow

```
ENV/DB (hex) → Blade (hex→HSL) → CSS Variables (HSL) → Tailwind (hsl()) → Browser (RGB)
#3b82f6      → 217.2 91.2% 59.8% → var(--accent)  → hsl(...)    → rgb(59, 130, 246)
```

## Customization Examples

### Brand Colors

```env
# Purple brand theme
INGEST_LIGHT_ACCENT=#8b5cf6
INGEST_DARK_ACCENT=#a78bfa
```

### High Contrast

```env
# Maximum contrast for accessibility
INGEST_LIGHT_BG=#ffffff
INGEST_LIGHT_FG=#000000
INGEST_DARK_BG=#000000
INGEST_DARK_FG=#ffffff
```

### Warm Dark Mode

```env
# Warmer dark mode (less blue)
INGEST_DARK_BG=#1a1612
INGEST_DARK_FG=#faf8f5
INGEST_DARK_ACCENT=#f59e0b
```

## Performance Considerations

### Why HSL?

Tailwind CSS uses HSL format for its color system. Converting hex to HSL server-side:
- ✅ Prevents client-side conversion overhead
- ✅ Enables Tailwind's opacity modifiers (`bg-primary/50`)
- ✅ Maintains semantic color relationships

### Why Server-Side Injection?

Injecting CSS variables in Blade instead of JavaScript:
- ✅ Eliminates theme flash on page load
- ✅ Works without JavaScript enabled
- ✅ Better SEO (correct colors in initial render)
- ✅ Faster perceived performance

## Troubleshooting

### Colors Not Updating

1. **Clear Laravel caches:**
   ```bash
   php artisan config:clear
   php artisan view:clear
   php artisan cache:clear
   ```

2. **Rebuild and republish assets:**
   ```bash
   cd prophoto-ingest
   npm run build
   cd ../sandbox
   php artisan vendor:publish --tag=ingest-assets --force
   ```

3. **Hard refresh browser:** Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows)

### Theme Toggle Not Working

Check browser console for JavaScript errors. Ensure:
- React is loaded
- `ThemeToggle` component is imported
- No conflicting theme scripts

### System Mode Not Detecting

Verify browser supports `prefers-color-scheme` media query:
```javascript
window.matchMedia('(prefers-color-scheme: dark)').matches
```

## Integration with Host Application

The ingest package's theme system is isolated and won't conflict with your main application's theme. However, you can synchronize them:

```php
// In your HandleInertiaRequests middleware
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'ingestTheme' => [
            'mode' => config('ingest.appearance.theme_mode'),
            'colors' => config('ingest.appearance'),
        ],
    ];
}
```

## Future Enhancements

Potential additions for future versions:
- [ ] User-specific theme preferences (stored per user)
- [ ] Settings UI for appearance customization
- [ ] Additional color scheme presets
- [ ] Gradient accent support
- [ ] Animation preferences (reduced motion)

## Technical Reference

### CSS Variable Naming Convention

- `--ingest-*` - Package-specific variables (injected from config)
- `--background`, `--foreground`, etc. - Tailwind semantic variables
- All colors use HSL format: `H S% L%` (no `hsl()` wrapper in variables)

### Supported Theme Modes

| Mode | Behavior |
|------|----------|
| `light` | Always light mode |
| `dark` | Always dark mode |
| `system` | Follows OS preference, updates on change |

### Color Requirements

- **Format:** Hex colors (`#RRGGBB` or `#RGB`)
- **Validation:** None (ensure valid hex in production)
- **Contrast:** Maintain WCAG AA standards for accessibility
