<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SandboxSeeder
 *
 * Seeds a minimal but complete set of data for smoke-testing the
 * ProPhoto ingest workflow end-to-end:
 *
 *   1. Studio          — "Peloso Photography"
 *   2. User            — dave@example.com / password
 *   3. Gallery         — "April 2026 Shoot"
 *   4. UploadSession   — STATUS_UPLOADING with 3 pre-registered files
 *   5. IngestFiles     — 2 uploaded (STATUS_COMPLETED), 1 pending
 *   6. IngestImageTags — a few EXIF + user tags on the completed files
 *
 * The session is ready to be confirmed via:
 *   POST /api/ingest/sessions/{id}/confirm
 *
 * Credentials:
 *   Email:    dave@example.com
 *   Password: password
 */
class SandboxSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding ProPhoto sandbox data...');

        DB::transaction(function () {

            // ── 1. Studio ──────────────────────────────────────────────────
            $studioId = DB::table('studios')->insertGetId([
                'name'           => 'Peloso Photography',
                'business_name'  => 'Peloso Photography LLC',
                'subdomain'      => 'peloso',
                'business_city'  => 'San Diego',
                'business_state' => 'CA',
                'timezone'       => 'America/Los_Angeles',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $this->command->line("  ✔ Studio created (id: $studioId)");

            // ── 2. User ────────────────────────────────────────────────────
            $userId = DB::table('users')->insertGetId([
                'name'       => 'Dave Peloso',
                'email'      => 'dave@example.com',
                'password'   => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->line("  ✔ User created (id: $userId, email: dave@example.com)");

            // ── 3. Organization + Gallery ──────────────────────────────────
            // Only insert if prophoto-gallery / prophoto-access tables exist
            $galleryId = null;
            if ($this->tableExists('organizations') && $this->tableExists('galleries')) {
                $orgId = DB::table('organizations')->insertGetId([
                    'studio_id'  => $studioId,
                    'name'       => 'Peloso Photography LLC',
                    'type'       => 'individual',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $galleryId = DB::table('galleries')->insertGetId([
                    'studio_id'      => $studioId,
                    'organization_id' => $orgId,
                    'subject_name'   => 'April 2026 Shoot',
                    'status'         => 'active',
                    'image_count'    => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $this->command->line("  ✔ Gallery created (id: $galleryId)");
            } else {
                $this->command->warn("  ⚠ galleries table not found — skipping gallery seed");
            }

            // ── 4. Upload Session ──────────────────────────────────────────
            $sessionId = (string) Str::uuid();

            DB::table('upload_sessions')->insert([
                'id'                   => $sessionId,
                'studio_id'            => $studioId,
                'user_id'              => $userId,
                'status'               => 'uploading',
                'file_count'           => 3,
                'completed_file_count' => 2,
                'total_size_bytes'     => 15_728_640, // ~15MB
                'gallery_id'           => $galleryId,
                'upload_started_at'    => now()->subMinutes(3),
                'created_at'           => now()->subMinutes(5),
                'updated_at'           => now(),
            ]);

            $this->command->line("  ✔ UploadSession created (id: $sessionId)");

            // ── 5. Ingest Files ────────────────────────────────────────────
            $file1Id = (string) Str::uuid();
            $file2Id = (string) Str::uuid();
            $file3Id = (string) Str::uuid();

            $now = now();

            DB::table('ingest_files')->insert([
                // File 1 — uploaded Canon RAW, with full EXIF
                [
                    'id'                => $file1Id,
                    'upload_session_id' => $sessionId,
                    'original_filename' => 'IMG_0042.CR3',
                    'file_size_bytes'   => 7_340_032, // ~7MB RAW
                    'file_type'         => 'image/x-canon-cr3',
                    'exif_data'         => json_encode([
                        'iso'         => 400,
                        'aperture'    => 2.8,
                        'focalLength' => 85,
                        'camera'      => 'Canon EOS R5',
                        'dateTime'    => now()->subHours(2)->toIso8601String(),
                    ]),
                    'upload_status'     => 'completed',
                    'storage_path'      => "ingest/$sessionId/{$file1Id}_IMG_0042.CR3",
                    'culled'            => false,
                    'rating'            => 4,
                    'uploaded_at'       => $now->copy()->subMinutes(2),
                    'created_at'        => $now->copy()->subMinutes(4),
                    'updated_at'        => $now->copy()->subMinutes(2),
                ],
                // File 2 — uploaded JPEG, minimal EXIF
                [
                    'id'                => $file2Id,
                    'upload_session_id' => $sessionId,
                    'original_filename' => 'IMG_0043.jpg',
                    'file_size_bytes'   => 5_242_880, // ~5MB
                    'file_type'         => 'image/jpeg',
                    'exif_data'         => json_encode([
                        'iso'         => 800,
                        'aperture'    => 4.0,
                        'focalLength' => 50,
                        'camera'      => 'Canon EOS R5',
                        'dateTime'    => now()->subHours(2)->addMinutes(5)->toIso8601String(),
                    ]),
                    'upload_status'     => 'completed',
                    'storage_path'      => "ingest/$sessionId/{$file2Id}_IMG_0043.jpg",
                    'culled'            => false,
                    'rating'            => 3,
                    'uploaded_at'       => $now->copy()->subMinutes(1),
                    'created_at'        => $now->copy()->subMinutes(4),
                    'updated_at'        => $now->copy()->subMinutes(1),
                ],
                // File 3 — still pending (simulates in-flight upload)
                [
                    'id'                => $file3Id,
                    'upload_session_id' => $sessionId,
                    'original_filename' => 'IMG_0044.CR3',
                    'file_size_bytes'   => 7_340_032,
                    'file_type'         => 'image/x-canon-cr3',
                    'exif_data'         => null,
                    'upload_status'     => 'pending',
                    'storage_path'      => null,
                    'culled'            => false,
                    'rating'            => 0,
                    'uploaded_at'       => null,
                    'created_at'        => $now->copy()->subMinutes(4),
                    'updated_at'        => $now->copy()->subMinutes(4),
                ],
            ]);

            $this->command->line("  ✔ IngestFiles created (2 uploaded, 1 pending)");

            // ── 6. Tags ────────────────────────────────────────────────────
            DB::table('ingest_image_tags')->insert([
                ['id' => (string) Str::uuid(), 'ingest_file_id' => $file1Id, 'tag' => 'iso-400',  'tag_type' => 'metadata', 'created_at' => $now],
                ['id' => (string) Str::uuid(), 'ingest_file_id' => $file1Id, 'tag' => 'f2.8',     'tag_type' => 'metadata', 'created_at' => $now],
                ['id' => (string) Str::uuid(), 'ingest_file_id' => $file1Id, 'tag' => '85mm',     'tag_type' => 'metadata', 'created_at' => $now],
                ['id' => (string) Str::uuid(), 'ingest_file_id' => $file1Id, 'tag' => 'portrait', 'tag_type' => 'user',     'created_at' => $now],
                ['id' => (string) Str::uuid(), 'ingest_file_id' => $file2Id, 'tag' => 'iso-800',  'tag_type' => 'metadata', 'created_at' => $now],
                ['id' => (string) Str::uuid(), 'ingest_file_id' => $file2Id, 'tag' => 'f4.0',     'tag_type' => 'metadata', 'created_at' => $now],
                ['id' => (string) Str::uuid(), 'ingest_file_id' => $file2Id, 'tag' => '50mm',     'tag_type' => 'metadata', 'created_at' => $now],
            ]);

            $this->command->line("  ✔ IngestImageTags created (7 tags)");

            // ── Sanctum API token ──────────────────────────────────────────
            // Generate a token so you can hit auth:sanctum protected routes
            // immediately without going through the login flow.
            $apiToken = null;
            if (class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
                $user = \App\Models\User::find($userId);
                $apiToken = $user->createToken('sandbox')->plainTextToken;
                $this->command->line("  ✔ Sanctum API token created");
            } else {
                $this->command->warn("  ⚠ Sanctum not installed — run: composer require laravel/sanctum");
            }

            // ── Print API smoke-test hints ─────────────────────────────────
            $this->command->newLine();
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->command->info('Smoke-test endpoints:');
            $this->command->newLine();
            $this->command->line("  GET  /api/ingest/sessions/$sessionId/progress");
            $this->command->line("  POST /api/ingest/sessions/$sessionId/confirm");
            $this->command->line("  GET  /api/ingest/sessions/$sessionId/preview-status");
            $this->command->newLine();
            $this->command->info("Login:  dave@example.com / password");
            if ($apiToken) {
                $this->command->newLine();
                $this->command->info('API Token (Bearer):');
                $this->command->line("  $apiToken");
                $this->command->newLine();
                $this->command->info('Quick curl test:');
                $this->command->line("  curl -s \\");
                $this->command->line("    -H \"Authorization: Bearer $apiToken\" \\");
                $this->command->line("    -H \"Accept: application/json\" \\");
                $this->command->line("    http://prophoto-app.test/api/ingest/sessions/$sessionId/progress | jq");
            }
            $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        }); // end transaction
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
