import { Moon, Sun, Monitor } from 'lucide-react';
import { useEffect, useState } from 'react';

type ThemeMode = 'light' | 'dark' | 'system';

export function ThemeToggle() {
    const [themeMode, setThemeMode] = useState<ThemeMode>(() => {
        return (document.documentElement.getAttribute('data-theme-mode') as ThemeMode) || 'system';
    });

    useEffect(() => {
        const applyTheme = (mode: ThemeMode) => {
            document.documentElement.setAttribute('data-theme-mode', mode);

            if (mode === 'system') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', isDark);
            } else {
                document.documentElement.classList.toggle('dark', mode === 'dark');
            }
        };

        applyTheme(themeMode);

        if (themeMode === 'system') {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handler = (e: MediaQueryListEvent) => {
                document.documentElement.classList.toggle('dark', e.matches);
            };
            mediaQuery.addEventListener('change', handler);
            return () => mediaQuery.removeEventListener('change', handler);
        }
    }, [themeMode]);

    const cycleTheme = () => {
        setThemeMode((current) => {
            if (current === 'light') return 'dark';
            if (current === 'dark') return 'system';
            return 'light';
        });
    };

    const getIcon = () => {
        if (themeMode === 'light') return <Sun className="h-4 w-4" />;
        if (themeMode === 'dark') return <Moon className="h-4 w-4" />;
        return <Monitor className="h-4 w-4" />;
    };

    const getLabel = () => {
        if (themeMode === 'light') return 'Light';
        if (themeMode === 'dark') return 'Dark';
        return 'System';
    };

    return (
        <button
            onClick={cycleTheme}
            className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md border border-input bg-background hover:bg-accent hover:text-accent-foreground transition-colors"
            title={`Theme: ${getLabel()} (click to cycle)`}
        >
            {getIcon()}
            <span className="hidden sm:inline">{getLabel()}</span>
        </button>
    );
}
