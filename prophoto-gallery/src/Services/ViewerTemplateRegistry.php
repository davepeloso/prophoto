<?php

namespace ProPhoto\Gallery\Services;

use ProPhoto\Gallery\Models\Gallery;

/**
 * Story 7.4 — Viewer Template Registry.
 *
 * Config-driven registry of available viewer templates. Each template has a slug,
 * display name, description, font stack, and list of supported gallery types.
 *
 * Templates are resolved to Blade paths at render time:
 *   prophoto-gallery::viewer.{type}.{slug}
 *
 * Adding a new template = drop a Blade file + add to config.
 */
class ViewerTemplateRegistry
{
    /**
     * Get all registered templates.
     *
     * @return array<string, array{name: string, description: string, types: string[], fonts: string[]}>
     */
    public function all(): array
    {
        return config('prophoto-gallery.viewer_templates', []);
    }

    /**
     * Get templates available for a specific gallery type.
     *
     * @param  string  $type  Gallery::TYPE_PROOFING or Gallery::TYPE_PRESENTATION
     * @return array<string, array>
     */
    public function forType(string $type): array
    {
        return array_filter($this->all(), function (array $template) use ($type) {
            return in_array($type, $template['types'] ?? [], true);
        });
    }

    /**
     * Get a single template by slug.
     *
     * @return array{name: string, description: string, types: string[], fonts: string[]}|null
     */
    public function get(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /**
     * Check if a template slug is valid for a given gallery type.
     */
    public function isValidForType(string $slug, string $type): bool
    {
        if ($slug === 'default') {
            return true;
        }

        $template = $this->get($slug);

        if ($template === null) {
            return false;
        }

        return in_array($type, $template['types'] ?? [], true);
    }

    /**
     * Resolve the Blade view name for a gallery and template slug.
     *
     * Resolution order:
     *   1. prophoto-gallery::viewer.{type}.{slug}
     *   2. prophoto-gallery::viewer.{type}.default
     *   3. prophoto-gallery::viewer.{type} (legacy fallback)
     *
     * @param  string  $type  'presentation' or 'proofing'
     * @param  string|null  $slug  Template slug (null = default)
     * @return string  Fully qualified Blade view name
     */
    public function resolveView(string $type, ?string $slug = null): string
    {
        $slug = $slug ?: 'default';

        // Try the specific template first
        $specific = "prophoto-gallery::viewer.{$type}.{$slug}";
        if (view()->exists($specific)) {
            return $specific;
        }

        // Fall back to the default template for this type
        $default = "prophoto-gallery::viewer.{$type}.default";
        if (view()->exists($default)) {
            return $default;
        }

        // Legacy fallback — flat directory (pre-7.4)
        return "prophoto-gallery::viewer.{$type}";
    }

    /**
     * Build Filament-compatible options array for a template picker.
     *
     * @param  string  $type  Gallery type to filter by
     * @return array<string, string>  slug => "Name — Description"
     */
    public function filamentOptions(string $type): array
    {
        $templates = $this->forType($type);

        $options = ['default' => 'Default — Clean, balanced gallery layout'];

        foreach ($templates as $slug => $template) {
            $options[$slug] = $template['name'] . ' — ' . $template['description'];
        }

        return $options;
    }

    /**
     * Get the Google Fonts import URL for a template.
     *
     * @return string|null  Google Fonts CSS URL, or null if no custom fonts
     */
    public function fontsUrl(string $slug): ?string
    {
        $template = $this->get($slug);

        if ($template === null || empty($template['fonts'])) {
            return null;
        }

        $families = array_map(function (string $font) {
            return 'family=' . str_replace(' ', '+', $font) . ':wght@300;400;500;600;700';
        }, $template['fonts']);

        return 'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap';
    }
}
