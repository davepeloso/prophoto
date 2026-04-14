<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ProPhoto\Access\Database\Seeders\RolesAndPermissionsSeeder;
use ProPhoto\Access\Models\PermissionContext;
use ProPhoto\Gallery\Database\Seeders\PendingTypeTemplatesSeeder;
use Spatie\Permission\Models\Permission;

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

        // ── 0. Roles & Permissions ────────────────────────────────────────
        // Must run outside the transaction so Spatie's cache flush works correctly
        if (class_exists(\ProPhoto\Access\Database\Seeders\RolesAndPermissionsSeeder::class)) {
            $this->call(RolesAndPermissionsSeeder::class);
            $this->command->line("  ✔ Roles and permissions seeded");
        } else {
            $this->command->warn("  ⚠ RolesAndPermissionsSeeder not found — is prophoto-access installed?");
        }

        // ── Pending type templates (system defaults) ──────────────────────────
        if (class_exists(\ProPhoto\Gallery\Database\Seeders\PendingTypeTemplatesSeeder::class)) {
            $this->call(PendingTypeTemplatesSeeder::class);
        } else {
            $this->command->warn("  ⚠ PendingTypeTemplatesSeeder not found — is prophoto-gallery installed?");
        }

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
                'studio_id'  => $studioId,
                'role'       => 'studio_user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->line("  ✔ User created (id: $userId, email: dave@example.com)");

            // ── 2b. Client User ────────────────────────────────────────────
            $clientUserId = DB::table('users')->insertGetId([
                'name'       => 'Gabby Rodriguez',
                'email'      => 'client@example.com',
                'password'   => Hash::make('password'),
                'studio_id'  => $studioId,
                'role'       => 'client_user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->line("  ✔ Client user created (id: $clientUserId, email: client@example.com)");

            // ── 2c. Subject / Guest User ───────────────────────────────────
            $subjectUserId = DB::table('users')->insertGetId([
                'name'       => 'Dr. Jessica Haslam',
                'email'      => 'subject@example.com',
                'password'   => Hash::make('password'),
                'studio_id'  => $studioId,
                'role'       => 'guest_user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->line("  ✔ Subject user created (id: $subjectUserId, email: subject@example.com)");

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

                // ── Assign Roles ───────────────────────────────────────────
                // Must use Eloquent models (not DB::table) for Spatie to work
                $dave    = \App\Models\User::find($userId);
                $gabby   = \App\Models\User::find($clientUserId);
                $jessica = \App\Models\User::find($subjectUserId);

                $dave->assignRole('studio_user');
                $this->command->line("  ✔ dave@example.com assigned role: studio_user");

                // Assign org to Gabby via pivot table + give client_user role
                DB::table('organization_user')->insert([
                    'organization_id' => $orgId,
                    'user_id'         => $clientUserId,
                    'role'            => 'marketing_contact',
                    'is_primary'      => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                $gabby->assignRole('client_user');
                $this->command->line("  ✔ client@example.com assigned role: client_user (org: $orgId)");

                $jessica->assignRole('guest_user');
                $this->command->line("  ✔ subject@example.com assigned role: guest_user");

                // ── Contextual Grants for Subject ──────────────────────────
                // Grant subject gallery-scoped permissions so smoke tests work
                $gallery = \ProPhoto\Gallery\Models\Gallery::find($galleryId);
                $proofingPerms = [
                    'can_view_gallery',
                    'can_approve_images',
                    'can_rate_images',
                    'can_comment_on_images',
                    'can_request_edits',
                    'can_download_images',
                    'can_consent_ai_use',
                ];
                foreach ($proofingPerms as $permName) {
                    $perm = Permission::findByName($permName);
                    if ($perm) {
                        PermissionContext::updateOrCreate([
                            'user_id'          => $subjectUserId,
                            'permission_id'    => $perm->id,
                            'contextable_type' => \ProPhoto\Gallery\Models\Gallery::class,
                            'contextable_id'   => $galleryId,
                        ], [
                            'granted_at' => now(),
                            'expires_at' => null,
                        ]);
                    }
                }
                $this->command->line("  ✔ Contextual grants assigned to subject@example.com for gallery $galleryId");

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

            // ── Sanctum API tokens ─────────────────────────────────────────
            $daveToken    = null;
            $clientToken  = null;
            $subjectToken = null;

            if (class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
                $daveToken    = \App\Models\User::find($userId)->createToken('sandbox-studio')->plainTextToken;
                $clientToken  = \App\Models\User::find($clientUserId)->createToken('sandbox-client')->plainTextToken;
                $subjectToken = \App\Models\User::find($subjectUserId)->createToken('sandbox-subject')->plainTextToken;
                $this->command->line("  ✔ Sanctum API tokens created for all 3 users");
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
            $this->command->info('Users (all passwords: password)');
            $this->command->line("  studio_user  dave@example.com    role: studio_user");
            $this->command->line("  client_user  client@example.com  role: client_user");
            $this->command->line("  guest_user   subject@example.com role: guest_user");
            if ($daveToken) {
                $this->command->newLine();
                $this->command->info('API Tokens (Bearer):');
                $this->command->line("  [studio]  $daveToken");
                $this->command->line("  [client]  $clientToken");
                $this->command->line("  [subject] $subjectToken");
                $this->command->newLine();
                $this->command->info('Quick curl test (as studio_user):');
                $this->command->line("  curl -s \\");
                $this->command->line("    -H \"Authorization: Bearer $daveToken\" \\");
                $this->command->line("    -H \"Accept: application/json\" \\");
                $this->command->line("    http://prophoto-app.test/api/ingest/sessions/$sessionId/progress | jq");
                $this->command->newLine();
                $this->command->info('RBAC smoke test (as studio_user):');
                $this->command->line("  php artisan tinker --execute=\"");
                $this->command->line("    \\\$u = App\\\\Models\\\\User::where('email','dave@example.com')->first();");
                $this->command->line("    echo \\\$u->hasRole('studio_user') ? 'PASS: hasRole' : 'FAIL: hasRole';");
                $this->command->line("    echo \\\$u->hasPermissionTo('can_upload_images') ? ' | PASS: hasPermission' : ' | FAIL: hasPermission';");
                $this->command->line("  \"");

                // ── Write sandbox.json for Postman ────────────────────────
                $appName  = env('APP_NAME', 'prophoto-app');
                $appUrl   = env('APP_URL', 'http://prophoto-app.test');
                $baseUrl  = rtrim($appUrl, '/') . '/api';

                // Build the Postman variable list — used both in the readable
                // section and in the importable postman_environment block.
                $postmanVars = [
                    'PROPHOTO_API_BASE_URL'  => $baseUrl,
                    'SESSION_ID'             => $sessionId,
                    // SANDBOX_SESSION_ID is a stable copy of the seeded session ID.
                    // Requests that overwrite SESSION_ID (e.g. Match Calendar) can
                    // restore it via:  pm.environment.set("SESSION_ID",
                    //   pm.environment.get("SANDBOX_SESSION_ID"));
                    'SANDBOX_SESSION_ID'     => $sessionId,
                    'GALLERY_ID'             => (string) $galleryId,
                    'STUDIO_ID'              => (string) $studioId,
                    'PROPHOTO_BEARER_TOKEN'  => $daveToken,   // default = studio user
                    'STUDIO_BEARER_TOKEN'    => $daveToken,
                    'CLIENT_BEARER_TOKEN'    => $clientToken,
                    'SUBJECT_BEARER_TOKEN'   => $subjectToken,
                    'STUDIO_EMAIL'           => 'dave@example.com',
                    'CLIENT_EMAIL'           => 'client@example.com',
                    'SUBJECT_EMAIL'          => 'subject@example.com',
                ];

                // Postman environment import format — File → Import → this file
                // works directly in Postman without any manual variable entry.
                $postmanValues = array_map(fn($key, $val) => [
                    'key'     => $key,
                    'value'   => $val,
                    'enabled' => true,
                    'type'    => 'default',
                ], array_keys($postmanVars), array_values($postmanVars));

                $sandboxJson = json_encode([
                    'sandbox' => [
                        'name'      => $appName,
                        'seeded_at' => now()->toIso8601String(),
                        'base_url'  => $baseUrl,
                    ],
                    'studio' => [
                        'id' => $studioId,
                    ],
                    'gallery' => [
                        'id' => $galleryId,
                    ],
                    'session' => [
                        'id' => $sessionId,
                    ],
                    'users' => [
                        'studio' => [
                            'id'    => $userId,
                            'email' => 'dave@example.com',
                            'role'  => 'studio_user',
                            'token' => $daveToken,
                        ],
                        'client' => [
                            'id'    => $clientUserId,
                            'email' => 'client@example.com',
                            'role'  => 'client_user',
                            'token' => $clientToken,
                        ],
                        'subject' => [
                            'id'    => $subjectUserId,
                            'email' => 'subject@example.com',
                            'role'  => 'guest_user',
                            'token' => $subjectToken,
                        ],
                    ],
                    'smoke_tests' => [
                        'check_session_progress' => "/ingest/sessions/{$sessionId}/progress",
                        'confirm_session'        => "/ingest/sessions/{$sessionId}/confirm",
                        'check_preview_status'   => "/ingest/sessions/{$sessionId}/preview-status",
                    ],
                    'postman' => $postmanVars,
                    // Postman native environment format — import this file directly
                    // via Environments → Import in Postman.
                    'id'     => 'prophoto-sandbox',
                    'name'   => "ProPhoto Sandbox — {$appName}",
                    'values' => $postmanValues,
                    '_postman_variable_scope' => 'environment',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                // Write to base_path() so it's easy to find at the app root
                $jsonPath = base_path('sandbox.json');
                file_put_contents($jsonPath, $sandboxJson);

                // ── Write postman-collection.json ─────────────────────────────
                // Separate from sandbox.json — this is the Collection import.
                // References {{VARIABLE}} placeholders from the environment file.
                // Collection structure never changes between reseeds — only the
                // environment file needs to be re-imported after each sandbox reset.
                $collectionPath = base_path('postman-collection.json');
                file_put_contents($collectionPath, $this->buildPostmanCollection($appName, $postmanVars));

                // ── Write postman-requests.json ───────────────────────────────
                // Flat array of request definition objects — one per request.
                // This is the source-of-truth format for source control and LLM
                // generation. Shape matches Postman's recommended spec:
                //   name, folder, method, url, headers, auth, body, description, tests
                //
                // To add a new endpoint: add an entry here, then run:
                //   php artisan db:seed --class=SandboxSeeder
                // and re-import postman-collection.json into Postman.
                $requestsPath = base_path('postman-requests.json');
                file_put_contents($requestsPath, $this->buildRequestDefinitions($appName));

                $this->command->newLine();
                $this->command->info("Postman files written:");
                $this->command->line("  Environment : $jsonPath");
                $this->command->line("  Collection  : $collectionPath");
                $this->command->line("  Requests    : $requestsPath");
                $this->command->newLine();
                $this->command->info("Import into Postman (one-time setup):");
                $this->command->line("  1. Collections → Import → postman-collection.json");
                $this->command->line("  2. Environments → Import → sandbox.json");
                $this->command->line("  3. Select 'ProPhoto Sandbox' environment in the top-right dropdown.");
                $this->command->newLine();
                $this->command->info("After each sandbox reset — re-import environment only:");
                $this->command->line("  Environments → (hover ProPhoto Sandbox) → ··· → Replace");
                $this->command->newLine();
                $this->command->info("Source control tip:");
                $this->command->line("  Commit postman-requests.json alongside your code.");
                $this->command->line("  Add new request definitions there — the seeder compiles them into postman-collection.json.");
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

    // ─── Postman Output Builders ──────────────────────────────────────────────

    /**
     * Build postman-requests.json — the flat source-of-truth format.
     *
     * Each entry matches Postman's recommended LLM-generation shape:
     *   name, folder, method, url, headers, auth, body, description, tests
     *
     * This file is committed to source control. The collection is compiled
     * from it. To add a new request: add an entry to requestDefinitions(),
     * re-run the seeder, and re-import postman-collection.json.
     */
    private function buildRequestDefinitions(string $appName): string
    {
        $meta = [
            '_meta' => [
                'generated_by' => 'SandboxSeeder',
                'app'          => $appName,
                'generated_at' => now()->toIso8601String(),
                'description'  => implode(' ', [
                    'Flat request definitions for the ProPhoto API.',
                    'Committed to source control — treat like code.',
                    'Compiled into postman-collection.json by the seeder.',
                    'Add new requests here; re-seed to rebuild the collection.',
                ]),
                'variables_required' => [
                    'PROPHOTO_API_BASE_URL',
                    'SESSION_ID',
                    'SANDBOX_SESSION_ID',
                    'GALLERY_ID',
                    'STUDIO_ID',
                    'FILE_ID',
                    'PROPHOTO_BEARER_TOKEN',
                    'STUDIO_BEARER_TOKEN',
                    'CLIENT_BEARER_TOKEN',
                    'SUBJECT_BEARER_TOKEN',
                ],
            ],
        ];

        return json_encode(
            array_merge($meta, ['requests' => $this->requestDefinitions()]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Build a Postman Collection v2.1 JSON string compiled from requestDefinitions().
     *
     * The collection uses {{VARIABLE}} placeholders throughout — it never
     * contains raw tokens or IDs. Only the environment file (sandbox.json)
     * holds real values and needs to be re-imported after each sandbox reset.
     * The collection itself is stable and only needs to be imported once.
     */
    private function buildPostmanCollection(string $appName, array $postmanVars = []): string
    {
        // Group flat definitions by folder, preserving insertion order
        $byFolder = [];
        foreach ($this->requestDefinitions() as $def) {
            $byFolder[$def['folder']][] = $this->defToCollectionItem($def);
        }

        // Inject the real seeded values into the Load Sandbox Context request body.
        // This makes the collection self-loading — import it, run it, no manual paste needed.
        // The pre-request script on that item reads pm.request.body.raw and sets env vars.
        if (!empty($postmanVars) && isset($byFolder['00 — Setup'])) {
            foreach ($byFolder['00 — Setup'] as &$item) {
                if ($item['name'] === 'Load Sandbox Context') {
                    $item['request']['body'] = [
                        'mode'    => 'raw',
                        'raw'     => json_encode($postmanVars, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        'options' => ['raw' => ['language' => 'json']],
                    ];
                    break;
                }
            }
            unset($item);
        }

        $items = [];
        foreach ($byFolder as $folderName => $folderItems) {
            $items[] = $this->folder($folderName, $folderItems);
        }

        $collection = [
            'info' => [
                '_postman_id' => 'prophoto-sandbox-collection',
                'name'        => "ProPhoto API — {$appName}",
                'description' => implode("\n", [
                    "ProPhoto sandbox API collection — self-loading.",
                    "",
                    "**After each reseed:**",
                    "1. Collections → (hover) → ··· → Replace → postman-collection.json",
                    "2. Run collection — '00 Setup → Load Sandbox Context' fires first",
                    "   and loads all fresh tokens/IDs into the active environment automatically.",
                    "",
                    "No manual variable pasting needed. The collection body contains",
                    "the live seeded values baked in at seed time.",
                    "",
                    "**Adding new requests:**",
                    "Edit postman-requests.json, re-seed, replace collection.",
                ]),
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'auth' => [
                'type'   => 'bearer',
                'bearer' => [['key' => 'token', 'value' => '{{PROPHOTO_BEARER_TOKEN}}', 'type' => 'string']],
            ],
            'variable' => [],
            'item'     => $items,
        ];

        return json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Convert a flat request definition into a Postman Collection v2.1 item.
     * This is the compiler step — definitions are the source, items are the output.
     */
    private function defToCollectionItem(array $def): array
    {
        $url    = $def['url'];
        $method = strtoupper($def['method']);

        // Strip the base URL variable prefix to get the path-only portion
        $pathOnly = preg_replace('/^\{\{PROPHOTO_API_BASE_URL\}\}/', '', $url);
        $pathSegments = array_values(
            array_filter(explode('/', $pathOnly), fn($s) => $s !== '')
        );

        // Auth block
        $authDef = $def['auth'] ?? ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'];
        if ($authDef['type'] === 'noauth') {
            $authBlock = ['type' => 'noauth'];
        } else {
            $authBlock = ['type' => 'bearer', 'bearer' => [
                ['key' => 'token', 'value' => $authDef['token'] ?? '{{PROPHOTO_BEARER_TOKEN}}', 'type' => 'string'],
            ]];
        }

        // Headers: merge definition headers with defaults
        $headers = [];
        foreach ($def['headers'] ?? ['Accept' => 'application/json'] as $key => $val) {
            $headers[] = ['key' => $key, 'value' => $val, 'type' => 'text'];
        }
        if (!array_key_exists('Content-Type', $def['headers'] ?? [])) {
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'];
        }

        $item = [
            'name' => $def['name'],
            'request' => [
                'auth'        => $authBlock,
                'method'      => $method,
                'header'      => $headers,
                'url' => [
                    'raw'  => $url,
                    'host' => ['{{PROPHOTO_API_BASE_URL}}'],
                    'path' => $pathSegments,
                ],
                'description' => $def['description'] ?? '',
            ],
            'response' => [],
        ];

        // Body
        if (!empty($def['body'])) {
            $item['request']['body'] = [
                'mode'    => 'raw',
                'raw'     => is_string($def['body'])
                    ? $def['body']
                    : json_encode($def['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        // Events
        $events = [];
        if (!empty($def['prerequest'])) {
            $events[] = ['listen' => 'prerequest', 'script' => [
                'type' => 'text/javascript',
                'exec' => $this->scriptLines($def['prerequest']),
            ]];
        }
        if (!empty($def['tests'])) {
            $events[] = ['listen' => 'test', 'script' => [
                'type' => 'text/javascript',
                'exec' => $this->scriptLines($def['tests']),
            ]];
        }
        if (!empty($events)) {
            $item['event'] = $events;
        }

        return $item;
    }

    /**
     * The canonical list of all request definitions.
     *
     * THIS is the single source of truth. Both postman-requests.json (flat)
     * and postman-collection.json (compiled) are generated from this list.
     *
     * Shape of each entry (all fields match Postman's recommended spec):
     *   name        string   — display name in Postman
     *   folder      string   — folder/group name (determines collection structure)
     *   method      string   — HTTP verb
     *   url         string   — full URL using {{VARIABLE}} placeholders
     *   headers     array    — key => value map (Content-Type added automatically)
     *   auth        array    — ['type'=>'bearer','token'=>'{{VAR}}'] or ['type'=>'noauth']
     *   body        mixed    — string (raw JSON) or array (auto-encoded), null for no body
     *   description string   — shown in Postman request docs tab
     *   tests       string   — JavaScript pm.test() script
     *   prerequest  string   — JavaScript pre-request script (optional)
     */
    private function requestDefinitions(): array
    {
        return [

            // ═══════════════════════════════════════════════════════════════════
            // 00 — Setup
            // Run this first after every reseed. Parses sandbox.json and loads
            // all runtime values into the active Postman environment so every
            // subsequent request can use {{VARIABLE}} placeholders.
            // ═══════════════════════════════════════════════════════════════════

            [
                'name'        => 'Load Sandbox Context',
                'folder'      => '00 — Setup',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/progress',
                'headers'     => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'auth'        => ['type' => 'noauth'],
                'body'        => null,
                'description' => implode("\n", [
                    'Paste the full contents of sandbox.json into this request body, then Send.',
                    '',
                    'The PRE-REQUEST script parses the JSON body and writes all known variables',
                    'into the active Postman environment BEFORE the HTTP call fires. This means',
                    'subsequent requests in the same Runner run will already have correct values.',
                    '',
                    'The HTTP response is irrelevant — the URL just needs to be reachable enough',
                    'for Postman to fire the pre-request script (it always does, even on errors).',
                    '',
                    'Supported payload shapes:',
                    '  • Flat: { "SESSION_ID": "...", "PROPHOTO_BEARER_TOKEN": "..." }',
                    '  • Nested postman key: { "postman": { "SESSION_ID": "..." } }',
                    '  • Nested data.postman: { "data": { "postman": { "SESSION_ID": "..." } } }',
                    '',
                    'After sending, the active environment will have all variables populated.',
                ]),
                'prerequest'  => <<<'JS'
                    // ── Load Sandbox Context — Pre-request script ─────────────────────
                    // Paste sandbox.json into the request body before sending.
                    // This script runs BEFORE the HTTP call, so all subsequent requests
                    // in the same Runner execution will have correct env variables.
                    // ─────────────────────────────────────────────────────────────────

                    const KNOWN_VARS = [
                        "PROPHOTO_API_BASE_URL",
                        "SESSION_ID",
                        "SANDBOX_SESSION_ID",
                        "GALLERY_ID",
                        "STUDIO_ID",
                        "PROPHOTO_BEARER_TOKEN",
                        "STUDIO_BEARER_TOKEN",
                        "CLIENT_BEARER_TOKEN",
                        "SUBJECT_BEARER_TOKEN",
                        "STUDIO_EMAIL",
                        "CLIENT_EMAIL",
                        "SUBJECT_EMAIL",
                    ];

                    const raw = pm.request.body && pm.request.body.raw ? pm.request.body.raw.trim() : "";
                    if (!raw) {
                        console.warn("Load Sandbox Context: request body is empty. Paste sandbox.json and resend.");
                        return;
                    }

                    let payload = {};
                    try {
                        payload = JSON.parse(raw);
                    } catch (e) {
                        console.error("Load Sandbox Context: JSON parse failed —", e.message);
                        return;
                    }

                    // Support flat, { postman: {...} }, and { data: { postman: {...} } }
                    let vars = payload;
                    if (payload.postman && typeof payload.postman === "object") vars = payload.postman;
                    if (payload.data && payload.data.postman && typeof payload.data.postman === "object") vars = payload.data.postman;

                    const loaded = [];
                    KNOWN_VARS.forEach(key => {
                        if (vars[key] !== undefined && vars[key] !== null && vars[key] !== "") {
                            pm.environment.set(key, String(vars[key]));
                            loaded.push(key);
                        }
                    });

                    console.log("Load Sandbox Context: set", loaded.length, "variables —", loaded.join(", "));
                    JS,
                'tests'       => <<<'JS'
                    // Verify variables were loaded by the pre-request script.
                    pm.test("SESSION_ID is a UUID", () => {
                        pm.expect(pm.environment.get("SESSION_ID")).to.match(/^[0-9a-f-]{36}$/);
                    });
                    pm.test("SANDBOX_SESSION_ID is a UUID", () => {
                        pm.expect(pm.environment.get("SANDBOX_SESSION_ID")).to.match(/^[0-9a-f-]{36}$/);
                    });
                    pm.test("PROPHOTO_BEARER_TOKEN is set", () => {
                        pm.expect(pm.environment.get("PROPHOTO_BEARER_TOKEN")).to.be.a("string").and.have.lengthOf.above(10);
                    });
                    pm.test("SESSION_ID and SANDBOX_SESSION_ID match", () => {
                        pm.expect(pm.environment.get("SESSION_ID")).to.eql(pm.environment.get("SANDBOX_SESSION_ID"));
                    });
                    JS,
            ],

            // ═══════════════════════════════════════════════════════════════════
            // 01 — Smoke Tests
            // Read-only, always-safe, idempotent. Run these first after any reset.
            // ═══════════════════════════════════════════════════════════════════

            [
                'name'        => 'Check Session Progress',
                'folder'      => '01 — Smoke Tests',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/progress',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Read-only smoke test. Safe to run at any time. Validates the seeded session exists with the correct shape and counts.',
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("session_id matches env", () => {
                        pm.expect(pm.response.json().session_id).to.eql(pm.environment.get("SESSION_ID"));
                    });
                    pm.test("file_count is 3", () => {
                        pm.expect(pm.response.json().file_count).to.eql(3);
                    });
                    pm.test("completed_file_count is 2", () => {
                        pm.expect(pm.response.json().completed_file_count).to.eql(2);
                    });
                    pm.test("gallery_id is set", () => {
                        pm.expect(pm.response.json().gallery_id).to.not.be.null;
                    });
                    pm.test("Required fields present", () => {
                        pm.expect(pm.response.json()).to.have.all.keys(
                            "session_id","status","file_count","completed_file_count",
                            "failed_file_count","percent_complete","is_uploading","gallery_id"
                        );
                    });
                    JS,
            ],

            [
                'name'        => 'Confirm Session (idempotent)',
                'folder'      => '01 — Smoke Tests',
                'method'      => 'POST',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/confirm',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => implode("\n", [
                    'Idempotent confirm. Safe to run on any sandbox state.',
                    '',
                    '- uploading/tagging → 200, status: confirmed, already_processed: false',
                    '- confirmed/completed → 200, current status, already_processed: true (no duplicate assets)',
                    '- initiated/failed/cancelled → 422 (intentional — needs intervention)',
                ]),
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("session_id matches env", () => {
                        pm.expect(pm.response.json().session_id).to.eql(pm.environment.get("SESSION_ID"));
                    });
                    pm.test("status is confirmed or completed", () => {
                        pm.expect(["confirmed","completed"]).to.include(pm.response.json().status);
                    });
                    pm.test("already_processed is boolean", () => {
                        pm.expect(pm.response.json().already_processed).to.be.a("boolean");
                    });
                    JS,
            ],

            [
                'name'        => 'Check Preview Status',
                'folder'      => '01 — Smoke Tests',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/preview-status',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Poll after Confirm Session while the listener creates assets. is_complete flips to true when done.',
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("session_id matches env", () => {
                        pm.expect(pm.response.json().session_id).to.eql(pm.environment.get("SESSION_ID"));
                    });
                    pm.test("Required fields present", () => {
                        pm.expect(pm.response.json()).to.have.all.keys(
                            "session_id","session_status","total_files","assets_created","is_complete","thumbnails"
                        );
                    });
                    pm.test("total_files is 3", () => {
                        pm.expect(pm.response.json().total_files).to.eql(3);
                    });
                    pm.test("thumbnails is an array", () => {
                        pm.expect(pm.response.json().thumbnails).to.be.an("array");
                    });
                    JS,
            ],

            // ═══════════════════════════════════════════════════════════════════
            // 02 — Ingest: Session Lifecycle
            // Full workflow from session creation through asset creation polling.
            // ═══════════════════════════════════════════════════════════════════

            [
                'name'        => 'Match Calendar (create session)',
                'folder'      => '02 — Ingest: Session Lifecycle',
                'method'      => 'POST',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/match-calendar',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                // studio_id and user_id must be integers — use pm.environment.get() in a
                // pre-request script to cast them, since {{STUDIO_ID}} resolves as a string.
                // metadata requires min:1 item and fileType must be one of the allowed values.
                'body'        => json_encode([
                    'studio_id' => 1,
                    'user_id'   => 1,
                    'metadata'  => [[
                        'filename' => 'IMG_0001.jpg',
                        'fileSize' => 5242880,
                        'fileType' => 'jpg',
                        'exif'     => [
                            'iso'         => 400,
                            'aperture'    => 2.8,
                            'focalLength' => 85,
                            'camera'      => 'Canon EOS R5',
                        ],
                    ]],
                ], JSON_PRETTY_PRINT),
                'description' => implode("\n", [
                    'Create a new UploadSession via calendar matching.',
                    'Saves upload_session_id to SESSION_ID env variable for downstream requests.',
                    '',
                    'Validation rules (422 if violated):',
                    '- studio_id: required integer >= 1',
                    '- user_id: required integer >= 1',
                    '- metadata: required array, min 1 item, max 500',
                    '- metadata.*.fileType: must be one of: raw, jpg, jpeg, heic, tiff, dng, png',
                    '',
                    'Note: studio_id/user_id are hardcoded to 1 in the body because',
                    '{{STUDIO_ID}} resolves as a string and fails the integer validator.',
                    'The pre-request script overrides them with the correct integer values.',
                ]),
                'prerequest'  => <<<'JS'
                    // Cast STUDIO_ID from env string to integer in the request body
                    const studioId = parseInt(pm.environment.get("STUDIO_ID"), 10) || 1;
                    const userId   = parseInt(pm.environment.get("STUDIO_ID"), 10) || 1;
                    const body = JSON.parse(pm.request.body.raw);
                    body.studio_id = studioId;
                    body.user_id   = userId;
                    pm.request.body.update(JSON.stringify(body, null, 2));
                    JS,
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("upload_session_id is a UUID", () => {
                        pm.expect(pm.response.json().upload_session_id).to.match(/^[0-9a-f\-]{36}$/);
                    });
                    pm.test("Required fields present", () => {
                        pm.expect(pm.response.json()).to.have.all.keys(
                            "upload_session_id","matches","no_match","timestamp_range",
                            "images_analyzed","calendar_connected"
                        );
                    });
                    pm.test("images_analyzed is 1", () => {
                        pm.expect(pm.response.json().images_analyzed).to.eql(1);
                    });
                    pm.test("Save SESSION_ID to env", () => {
                        const id = pm.response.json().upload_session_id;
                        if (id) pm.environment.set("SESSION_ID", id);
                    });
                    JS,
            ],

            [
                'name'        => 'Get Session Progress',
                'folder'      => '02 — Ingest: Session Lifecycle',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/progress',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Poll upload progress. Returns current status, file counts, and percent_complete.',
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("status field present", () => {
                        pm.expect(pm.response.json()).to.have.property("status");
                    });
                    pm.test("percent_complete is 0-100", () => {
                        const pct = pm.response.json().percent_complete;
                        pm.expect(pct).to.be.at.least(0).and.at.most(100);
                    });
                    JS,
            ],

            [
                'name'        => 'Confirm Session (seeded)',
                'folder'      => '02 — Ingest: Session Lifecycle',
                'method'      => 'POST',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/confirm',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => json_encode(['gallery_id' => '{{GALLERY_ID}}'], JSON_PRETTY_PRINT),
                'description' => implode("\n", [
                    'Confirm the seeded session ({{SESSION_ID}} from sandbox.json).',
                    '',
                    'Uses the seeded session rather than the one created by Match Calendar above,',
                    'because a freshly-created session has status=initiated (no files uploaded yet)',
                    'and cannot be confirmed until it reaches status=uploading.',
                    '',
                    'The seeded session is pre-populated with 2 completed files and status=uploading,',
                    'so it is always in a confirmable state. This request is idempotent — safe to',
                    'run multiple times against the same sandbox.',
                ]),
                'prerequest'  => <<<'JS'
                    // Restore SESSION_ID to the seeded sandbox session before confirming.
                    // Match Calendar (above) overwrites SESSION_ID with a new initiated session.
                    // We store the original seeded ID in SANDBOX_SESSION_ID at collection startup
                    // so we can restore it here. If not set, SESSION_ID is already correct.
                    const sandboxId = pm.environment.get("SANDBOX_SESSION_ID");
                    if (sandboxId) {
                        pm.environment.set("SESSION_ID", sandboxId);
                    }
                    JS,
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("status is confirmed or completed", () => {
                        pm.expect(["confirmed","completed"]).to.include(pm.response.json().status);
                    });
                    pm.test("already_processed field present", () => {
                        pm.expect(pm.response.json()).to.have.property("already_processed");
                    });
                    JS,
            ],

            [
                'name'        => 'Confirm Session — 422 for unknown session',
                'folder'      => '02 — Ingest: Session Lifecycle',
                'method'      => 'POST',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/00000000-0000-0000-0000-000000000000/confirm',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Negative test. Fake UUID — session does not exist. Expects 422 with an error message.',
                'tests'       => <<<'JS'
                    pm.test("Status 422", () => pm.response.to.have.status(422));
                    pm.test("error field present", () => {
                        pm.expect(pm.response.json()).to.have.property("error");
                    });
                    pm.test("error mentions the session ID", () => {
                        pm.expect(pm.response.json().error).to.include("00000000-0000-0000-0000-000000000000");
                    });
                    JS,
            ],

            [
                'name'        => 'Get Preview Status',
                'folder'      => '02 — Ingest: Session Lifecycle',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/preview-status',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Poll asset creation after Confirm. is_complete: true when listener finishes. assets_created counts created Asset records.',
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("is_complete is boolean", () => {
                        pm.expect(pm.response.json().is_complete).to.be.a("boolean");
                    });
                    pm.test("assets_created >= 0", () => {
                        pm.expect(pm.response.json().assets_created).to.be.at.least(0);
                    });
                    JS,
            ],

            [
                'name'        => 'Unlink Calendar',
                'folder'      => '02 — Ingest: Session Lifecycle',
                'method'      => 'DELETE',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/unlink-calendar',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Removes calendar event linkage from a session. Returns 200 with calendar_event_id: null.',
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("calendar_event_id is null", () => {
                        pm.expect(pm.response.json().calendar_event_id).to.be.null;
                    });
                    JS,
            ],

            [
                'name'        => 'Get Progress — 404 for unknown session',
                'folder'      => '02 — Ingest: Session Lifecycle',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/00000000-0000-0000-0000-000000000000/progress',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Negative test. Expects 404 for a session UUID that does not exist.',
                'tests'       => <<<'JS'
                    pm.test("Status 404", () => pm.response.to.have.status(404));
                    pm.test("error field present", () => {
                        pm.expect(pm.response.json()).to.have.property("error");
                    });
                    JS,
            ],

            // ═══════════════════════════════════════════════════════════════════
            // 03 — Ingest: File Operations
            // Register, tag, and batch-update files within a session.
            // ═══════════════════════════════════════════════════════════════════

            [
                'name'        => 'Register Files',
                'folder'      => '03 — Ingest: File Operations',
                'method'      => 'POST',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/files',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => json_encode([
                    'files' => [[
                        'filename'  => 'IMG_0100.jpg',
                        'file_size' => 5242880,
                        'file_type' => 'image/jpeg',
                        'exif'      => ['iso' => 400, 'aperture' => 2.8, 'focalLength' => 85],
                    ]],
                ], JSON_PRETTY_PRINT),
                'description' => 'Register files before uploading. Returns a UUID per file. Saves FILE_ID env variable for downstream requests.',
                'tests'       => <<<'JS'
                    pm.test("Status 201", () => pm.response.to.have.status(201));
                    pm.test("files array returned", () => {
                        pm.expect(pm.response.json().files).to.be.an("array").with.length.above(0);
                    });
                    pm.test("each file has file_id and filename", () => {
                        pm.response.json().files.forEach(f => {
                            pm.expect(f).to.have.property("file_id");
                            pm.expect(f).to.have.property("filename");
                        });
                    });
                    pm.test("Save FILE_ID to env", () => {
                        const files = pm.response.json().files;
                        if (files && files[0]) pm.environment.set("FILE_ID", files[0].file_id);
                    });
                    JS,
            ],

            [
                'name'        => 'Apply Tag to File',
                'folder'      => '03 — Ingest: File Operations',
                'method'      => 'POST',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/files/{{FILE_ID}}/tags',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => json_encode(['tag' => 'wedding', 'tag_type' => 'user'], JSON_PRETTY_PRINT),
                'description' => 'Apply a user tag to a file. Idempotent — duplicate tags are a no-op. Tag is lowercased automatically.',
                'tests'       => <<<'JS'
                    pm.test("Status 201", () => pm.response.to.have.status(201));
                    pm.test("tag is lowercase", () => {
                        pm.expect(pm.response.json().tag).to.eql("wedding");
                    });
                    pm.test("tag_type is user", () => {
                        pm.expect(pm.response.json().tag_type).to.eql("user");
                    });
                    JS,
            ],

            [
                'name'        => 'Remove Tag from File',
                'folder'      => '03 — Ingest: File Operations',
                'method'      => 'DELETE',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/files/{{FILE_ID}}/tags/wedding',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'Remove a tag from a file. Returns 204 No Content. Safe to call even if the tag does not exist.',
                'tests'       => <<<'JS'
                    pm.test("Status 204", () => pm.response.to.have.status(204));
                    JS,
            ],

            [
                'name'        => 'Batch Update Files',
                'folder'      => '03 — Ingest: File Operations',
                'method'      => 'PATCH',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/files/batch',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{PROPHOTO_BEARER_TOKEN}}'],
                'body'        => json_encode([
                    'ids'     => ['{{FILE_ID}}'],
                    'updates' => ['culled' => false, 'rating' => 4],
                ], JSON_PRETTY_PRINT),
                'description' => 'Apply cull toggle or star rating to multiple files at once. updated reflects number of rows changed.',
                'tests'       => <<<'JS'
                    pm.test("Status 200", () => pm.response.to.have.status(200));
                    pm.test("updated count >= 0", () => {
                        pm.expect(pm.response.json().updated).to.be.at.least(0);
                    });
                    JS,
            ],

            // ═══════════════════════════════════════════════════════════════════
            // 04 — RBAC: Auth & Permissions
            // Verify token auth and role boundaries.
            // ═══════════════════════════════════════════════════════════════════

            [
                'name'        => 'Studio user — session progress',
                'folder'      => '04 — RBAC: Auth & Permissions',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/progress',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{STUDIO_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'studio_user should always pass ingest endpoint checks — has global permissions.',
                'tests'       => <<<'JS'
                    pm.test("Status 200 as studio_user", () => pm.response.to.have.status(200));
                    JS,
            ],

            [
                'name'        => 'Client user — session progress',
                'folder'      => '04 — RBAC: Auth & Permissions',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/progress',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => '{{CLIENT_BEARER_TOKEN}}'],
                'body'        => null,
                'description' => 'client_user hitting a studio-scoped endpoint. Expected: 200 (if shared access) or 403 (if studio-only). Update assertion to match your middleware.',
                'tests'       => <<<'JS'
                    pm.test("200 or 403 as client_user", () => {
                        pm.expect([200, 403]).to.include(pm.response.code);
                    });
                    pm.test("Response is valid JSON", () => { pm.response.json(); });
                    JS,
            ],

            [
                'name'        => 'No auth — expect 401',
                'folder'      => '04 — RBAC: Auth & Permissions',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/progress',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'noauth'],
                'body'        => null,
                'description' => 'No Authorization header. Sanctum returns 401 Unauthenticated.',
                'tests'       => <<<'JS'
                    pm.test("Status 401 without auth", () => pm.response.to.have.status(401));
                    JS,
            ],

            [
                'name'        => 'Invalid token — expect 401',
                'folder'      => '04 — RBAC: Auth & Permissions',
                'method'      => 'GET',
                'url'         => '{{PROPHOTO_API_BASE_URL}}/ingest/sessions/{{SESSION_ID}}/progress',
                'headers'     => ['Accept' => 'application/json'],
                'auth'        => ['type' => 'bearer', 'token' => 'invalid-token-000'],
                'body'        => null,
                'description' => 'Bogus Bearer token. Sanctum rejects it with 401.',
                'tests'       => <<<'JS'
                    pm.test("Status 401 with invalid token", () => pm.response.to.have.status(401));
                    JS,
            ],

        ]; // end requestDefinitions()
    }

    /**
     * All collection folders and their requests — compiled from requestDefinitions().
     * @deprecated Internal use only — call collectionFolders() via buildPostmanCollection().
     */
    // ─── Collection Builder Helpers ───────────────────────────────────────────

    /**
     * Build a folder (Postman ItemGroup).
     */
    private function folder(string $name, array $items): array
    {
        return [
            'name' => $name,
            'item' => $items,
        ];
    }

    /**
     * Build a single request item in Postman Collection v2.1 format.
     *
     * Follows Postman's recommended LLM-generation shape:
     *   name, folder (structural — handled by collectionFolders()), method, url,
     *   headers, auth, body, description, tests, prerequest
     *
     * URL handling: `raw` is the source of truth for display and Runner.
     * The structured `host`/`path` breakdown is derived from `raw` for
     * Postman's internal parser. Variable segments like {{SESSION_ID}} are
     * kept as-is — Postman resolves them at runtime from the active environment.
     *
     * @param  array|null  $body        Postman body object or null for no body
     * @param  array|null  $auth        Override auth:
     *                                    ['type' => 'noauth']
     *                                    ['type' => 'bearer', 'token' => '{{VAR}}']
     * @param  string      $tests       JavaScript test script (heredoc, indentation stripped)
     * @param  string      $prerequest  JavaScript pre-request script (optional)
     */
    private function request(
        string  $name,
        string  $method,
        string  $url,
        string  $description = '',
        ?array  $body        = null,
        ?array  $auth        = null,
        string  $tests       = '',
        string  $prerequest  = '',
    ): array {
        // ── URL structure ─────────────────────────────────────────────────────
        // Postman uses `raw` as the canonical URL string.
        // We also provide a structured breakdown so the Postman UI can display
        // path segments correctly in the address bar.
        //
        // Because the base URL is itself a variable ({{PROPHOTO_API_BASE_URL}}),
        // we strip it from the path so Postman doesn't double-include it.
        $pathOnly = preg_replace('/^\{\{PROPHOTO_API_BASE_URL\}\}/', '', $url);
        $pathSegments = array_values(
            array_filter(explode('/', $pathOnly), fn($s) => $s !== '')
        );

        // ── Auth ──────────────────────────────────────────────────────────────
        // Default: inherit bearer from collection-level auth (PROPHOTO_BEARER_TOKEN).
        // Override per-request for role-specific or noauth tests.
        if ($auth === null) {
            $authBlock = ['type' => 'bearer', 'bearer' => [
                ['key' => 'token', 'value' => '{{PROPHOTO_BEARER_TOKEN}}', 'type' => 'string'],
            ]];
        } elseif ($auth['type'] === 'noauth') {
            $authBlock = ['type' => 'noauth'];
        } else {
            $authBlock = ['type' => 'bearer', 'bearer' => [
                ['key' => 'token', 'value' => $auth['token'], 'type' => 'string'],
            ]];
        }

        // ── Request object ────────────────────────────────────────────────────
        $item = [
            'name' => $name,
            'request' => [
                'auth'        => $authBlock,
                'method'      => strtoupper($method),
                'header'      => [
                    ['key' => 'Accept',       'value' => 'application/json', 'type' => 'text'],
                    ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
                ],
                'url' => [
                    'raw'  => $url,
                    'host' => ['{{PROPHOTO_API_BASE_URL}}'],
                    'path' => $pathSegments,
                ],
                'description' => $description,
            ],
            'response' => [],   // empty examples array — required by v2.1 schema
        ];

        // ── Body ──────────────────────────────────────────────────────────────
        if ($body !== null) {
            $item['request']['body'] = $body;
        }

        // ── Event scripts (test + pre-request) ────────────────────────────────
        // Heredoc indentation is stripped with trim() per line so the JS renders
        // cleanly in Postman's script editor without leading whitespace artifacts.
        $events = [];

        if ($prerequest !== '') {
            $events[] = [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $this->scriptLines($prerequest),
                ],
            ];
        }

        if ($tests !== '') {
            $events[] = [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $this->scriptLines($tests),
                ],
            ];
        }

        if (!empty($events)) {
            $item['event'] = $events;
        }

        return $item;
    }

    /**
     * Convert a heredoc JS string into a clean array of lines for Postman's
     * `exec` field. Strips common leading indentation (dedent) so scripts
     * written with PHP heredoc indentation render without leading spaces
     * in Postman's Monaco editor.
     *
     * @return string[]
     */
    private function scriptLines(string $script): array
    {
        $lines = explode("\n", $script);

        // Find the minimum non-empty indentation to strip (dedent)
        $minIndent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $indent = strlen($line) - strlen(ltrim($line));
            $minIndent = min($minIndent, $indent);
        }
        if ($minIndent === PHP_INT_MAX) $minIndent = 0;

        return array_map(
            fn($line) => rtrim(substr($line, min($minIndent, strlen($line)))),
            $lines
        );
    }
}
