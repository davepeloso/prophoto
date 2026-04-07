<?php

namespace ProPhoto\Ingest\Database\Seeders;

use Illuminate\Database\Seeder;
use ProPhoto\Ingest\Models\IngestSetting;

class IngestSettingsSeeder extends Seeder
{
    /**
     * Seed the ingest_settings table with example configuration values.
     *
     * This seeder demonstrates how to populate custom configuration settings
     * that will override the default values in config/ingest.php.
     *
     * Usage:
     *   php artisan db:seed --class=prophoto\\Ingest\\Database\\Seeders\\IngestSettingsSeeder
     */
    public function run(): void
    {
        $settings = [
            // Core Settings
            // 'route_prefix' => 'photo-ingest',
            // 'middleware' => ['web', 'auth', 'verified'],

            // Schema Settings
            // 'schema.path' => 'photos/{date:Y}/{date:m}/{camera}',
            // 'schema.filename' => '{sequence}-{original}',
            // 'schema.sequence_start' => 1,
            // 'schema.sequence_padding' => 4,

            // Storage Settings
            // 'storage.temp_disk' => 'public',
            // 'storage.temp_path' => 'uploads/temp',
            // 'storage.final_disk' => 's3',
            // 'storage.final_path' => 'images/final',

            // Processing Settings - Thumbnail
            // 'exif.thumbnail.enabled' => true,
            // 'exif.thumbnail.width' => 400,
            // 'exif.thumbnail.height' => 400,
            // 'exif.thumbnail.quality' => 85,

            // Processing Settings - Preview
            // 'exif.preview.enabled' => true,
            // 'exif.preview.max_dimension' => 2048,
            // 'exif.preview.quality' => 90,

            // Processing Settings - Final
            // 'exif.final.enabled' => false,
            // 'exif.final.max_dimension' => 4096,
            // 'exif.final.quality' => 95,

            // Metadata Settings
            // 'metadata.chart_fields' => ['iso', 'aperture', 'shutter_speed'],

            // Tagging Settings
            // 'tagging.quick_tags' => ['landscape', 'portrait', 'event', 'product'],
            // 'tagging.sources' => [
            //     'Clients' => 'App\\Models\\Client',
            //     'Events' => 'App\\Models\\Event',
            // ],

            // Association Settings
            // 'associations.models' => ['App\\Models\\Shoot', 'App\\Models\\Project'],

            // Appearance Settings
            // 'appearance.accent_color' => '#3B82F6',
            // 'appearance.border_radius' => 8,
            // 'appearance.spacing_scale' => 'medium',
        ];

        foreach ($settings as $key => $value) {
            IngestSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->command->info('Ingest settings seeded successfully!');
    }
}
