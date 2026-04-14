<?php

namespace ProPhoto\Gallery\Database\Seeders;

use Illuminate\Database\Seeder;
use ProPhoto\Gallery\Models\StudioPendingTypeTemplate;

/**
 * PendingTypeTemplatesSeeder
 *
 * Seeds the four system-default pending type templates used across all studios.
 * These live as rows with studio_id = null and is_system_default = true.
 *
 * Studios can:
 *   - Hide a default by setting is_active = false on their own copy
 *   - Add custom types (studio_id = their studio id)
 *   - Reorder at the gallery level without changing the master list
 *
 * Safe to run multiple times — uses updateOrCreate.
 */
class PendingTypeTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        StudioPendingTypeTemplate::seedSystemDefaults();

        $this->command?->line('  ✔ System pending type templates seeded (Retouch, Background Swap, Awaiting Second Approval, Colour Correction)');
    }
}
