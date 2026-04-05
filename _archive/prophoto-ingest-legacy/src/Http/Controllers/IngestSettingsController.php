<?php

namespace ProPhoto\Ingest\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use ProPhoto\Ingest\Services\IngestSettingsService;
use ProPhoto\Ingest\Services\MetadataKeyService;

class IngestSettingsController
{
    protected IngestSettingsService $settings;
    protected MetadataKeyService $metadataKeys;

    public function __construct(IngestSettingsService $settings, MetadataKeyService $metadataKeys)
    {
        $this->settings = $settings;
        $this->metadataKeys = $metadataKeys;
    }

    /**
     * Display the settings page.
     */
    public function edit()
    {
        $dbSettings = $this->settings->getAll();

        // Get current config values (which may be from DB or config file)
        $config = config('ingest');

        // Build settings data with only user-editable settings
        // Removed: core settings (route_prefix, middleware - moved to ENV)
        // Removed: storage settings (disks, paths - moved to ENV)
        // Removed: imageProcessing settings (thumbnail, preview, final - developer-only)
        // Removed: appearance settings (moved to ENV)
        // Removed: associations.models (developer-only)
        $settingsData = [
            'schema' => [
                'path' => $dbSettings['schema.path'] ?? $config['schema']['path'] ?? 'shoots/{date:Y}/{date:m}/{camera}',
                'filename' => $dbSettings['schema.filename'] ?? $config['schema']['filename'] ?? '{sequence}-{original}',
                'sequence_start' => $dbSettings['schema.sequence_start'] ?? $config['schema']['sequence_start'] ?? 1,
                'sequence_padding' => $dbSettings['schema.sequence_padding'] ?? $config['schema']['sequence_padding'] ?? 3,
            ],
            'metadata' => [
                'chart_fields' => $dbSettings['metadata.chart_fields'] ?? $config['metadata']['chart_fields'] ?? ['iso', 'aperture', 'shutterSpeed', 'focalLength'],
                'default_chart_field' => $dbSettings['metadata.default_chart_field'] ?? $config['metadata']['default_chart_field'] ?? 'iso',
                'tagging' => [
                    'quick_tags' => $dbSettings['tagging.quick_tags'] ?? $config['tagging']['quick_tags'] ?? [],
                ],
            ],
        ];

        // Get available metadata keys for the chart fields dropdown
        $availableChartKeys = $this->metadataKeys->getAvailableChartKeys();

        return Inertia::render('Ingest/Settings', [
            'settings' => $settingsData,
            'availableChartKeys' => $availableChartKeys,
        ])->rootView('ingest::app');
    }

    /**
     * Update settings.
     *
     * Only user-editable settings are validated and persisted:
     * - Schema: path, filename, sequence_start, sequence_padding
     * - Metadata: chart_fields, default_chart_field, quick_tags
     *
     * Removed from UI/validation/persistence (moved to ENV or deleted):
     * - Core: route_prefix, middleware (ENV)
     * - Storage: temp_disk, temp_path, final_disk, final_path (ENV)
     * - Image Processing: thumbnail, preview, final settings (deleted)
     * - Appearance: accent_color, border_radius, spacing_scale (ENV)
     * - Associations: models (deleted)
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            // Schema settings (user-editable)
            'schema.path' => 'sometimes|string|max:255',
            'schema.filename' => 'sometimes|string|max:255',
            'schema.sequence_start' => 'sometimes|integer|min:0',
            'schema.sequence_padding' => 'sometimes|integer|min:1|max:10',

            // Metadata settings (user-editable)
            'metadata.chart_fields' => 'sometimes|array',
            'metadata.chart_fields.*' => 'string',
            'metadata.default_chart_field' => 'sometimes|string',
            'metadata.tagging.quick_tags' => 'sometimes|array',
            'metadata.tagging.quick_tags.*' => 'string',
        ]);

        // Flatten and map the nested validated data to dotted keys for storage
        $flatSettings = [];

        if (isset($validated['schema'])) {
            foreach ($validated['schema'] as $key => $value) {
                $flatSettings["schema.{$key}"] = $value;
            }
        }

        if (isset($validated['metadata'])) {
            if (isset($validated['metadata']['chart_fields'])) {
                $flatSettings['metadata.chart_fields'] = $validated['metadata']['chart_fields'];
            }
            if (isset($validated['metadata']['default_chart_field'])) {
                $flatSettings['metadata.default_chart_field'] = $validated['metadata']['default_chart_field'];
            }
            if (isset($validated['metadata']['tagging']['quick_tags'])) {
                $flatSettings['tagging.quick_tags'] = $validated['metadata']['tagging']['quick_tags'];
            }
        }

        // Save all settings using the service
        $this->settings->setMultiple($flatSettings);

        // Clear the settings cache
        $this->settings->clearCache();

        return redirect()
            ->route('ingest.settings.edit')
            ->with('success', 'Settings updated successfully!');
    }
}
