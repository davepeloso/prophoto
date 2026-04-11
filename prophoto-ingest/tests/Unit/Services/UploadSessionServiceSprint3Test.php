<?php

namespace ProPhoto\Ingest\Tests\Unit\Services;

use Orchestra\Testbench\TestCase;
use ProPhoto\Ingest\IngestServiceProvider;
use ProPhoto\Ingest\Models\IngestFile;
use ProPhoto\Ingest\Models\IngestImageTag;
use ProPhoto\Ingest\Models\UploadSession;
use ProPhoto\Ingest\Services\UploadSessionService;

/**
 * UploadSessionService — Sprint 3 Tests
 *
 * Covers the new capabilities added in Sprint 3:
 *   - registerFile() accepts 'filename' alias for 'original_filename'
 *   - registerFile() accepts 'file_size' alias for 'file_size_bytes'
 *   - IngestFile model: filename accessor + is_culled alias
 *   - IngestFile model: rating field persisted and cast
 *   - batchUpdateFiles() logic via direct model updates
 *   - removeTag() cleans up correctly
 *   - getProgress() reflects uploaded count accurately
 *
 * Sprint 3
 */
class UploadSessionServiceSprint3Test extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [IngestServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function service(): UploadSessionService
    {
        return $this->app->make(UploadSessionService::class);
    }

    protected function makeSession(): UploadSession
    {
        return $this->service()->createSession([
            'studio_id' => 1,
            'user_id'   => 1,
        ]);
    }

    // ─── registerFile() — filename aliases ────────────────────────────────────

    /** @test */
    public function it_accepts_filename_alias_for_original_filename(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'filename'  => 'IMG_001.CR2',   // short alias
            'file_size' => 52_000_000,       // short alias
            'file_type' => 'raw',
        ]);

        $this->assertEquals('IMG_001.CR2', $file->original_filename);
        $this->assertEquals(52_000_000, $file->file_size_bytes);
    }

    /** @test */
    public function it_accepts_original_filename_directly(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
        ]);

        $this->assertEquals('IMG_001.CR2', $file->original_filename);
    }

    // ─── IngestFile — filename accessor ───────────────────────────────────────

    /** @test */
    public function ingest_file_filename_accessor_returns_original_filename(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'filename'  => 'DSC_0042.NEF',
            'file_size' => 35_000_000,
            'file_type' => 'raw',
        ]);

        // Reload from DB
        $fresh = IngestFile::find($file->id);

        $this->assertEquals('DSC_0042.NEF', $fresh->filename);
        $this->assertEquals('DSC_0042.NEF', $fresh->original_filename);
    }

    // ─── IngestFile — is_culled alias ─────────────────────────────────────────

    /** @test */
    public function ingest_file_is_culled_setter_maps_to_culled_column(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'filename'  => 'IMG_002.CR2',
            'file_size' => 50_000_000,
            'file_type' => 'raw',
        ]);

        $this->assertFalse($file->culled);

        // Update using the is_culled alias (as the batch endpoint does)
        $file->update(['is_culled' => true]);
        $fresh = IngestFile::find($file->id);

        $this->assertTrue($fresh->culled);
        $this->assertTrue($fresh->isCulled());
    }

    /** @test */
    public function ingest_file_is_culled_can_be_toggled_back_to_false(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'filename'  => 'IMG_003.CR2',
            'file_size' => 50_000_000,
            'file_type' => 'raw',
        ]);

        $file->update(['is_culled' => true]);
        $file->update(['is_culled' => false]);

        $fresh = IngestFile::find($file->id);
        $this->assertFalse($fresh->culled);
    }

    // ─── IngestFile — rating ──────────────────────────────────────────────────

    /** @test */
    public function ingest_file_rating_defaults_to_zero(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'filename'  => 'IMG_004.CR2',
            'file_size' => 50_000_000,
            'file_type' => 'raw',
        ]);

        $fresh = IngestFile::find($file->id);
        $this->assertEquals(0, $fresh->rating);
    }

    /** @test */
    public function ingest_file_rating_is_persisted_and_cast_to_integer(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'filename'  => 'IMG_005.CR2',
            'file_size' => 50_000_000,
            'file_type' => 'raw',
        ]);

        $file->update(['rating' => 5]);
        $fresh = IngestFile::find($file->id);

        $this->assertSame(5, $fresh->rating);
        $this->assertIsInt($fresh->rating);
    }

    // ─── removeTag() ──────────────────────────────────────────────────────────

    /** @test */
    public function remove_tag_deletes_the_specified_tag(): void
    {
        $session = $this->makeSession();
        $service = $this->service();

        $file = $service->registerFile($session->id, [
            'filename'  => 'IMG_006.CR2',
            'file_size' => 50_000_000,
            'file_type' => 'raw',
        ]);

        $service->applyTag($file->id, 'wedding', IngestImageTag::TYPE_USER);
        $service->applyTag($file->id, 'bride',   IngestImageTag::TYPE_USER);

        $this->assertCount(2, $service->getTagsForFile($file->id));

        $service->removeTag($file->id, 'wedding');

        $remaining = $service->getTagsForFile($file->id);
        $this->assertCount(1, $remaining);
        $this->assertEquals('bride', $remaining[0]['tag']);
    }

    /** @test */
    public function remove_tag_is_silent_when_tag_does_not_exist(): void
    {
        $session = $this->makeSession();
        $service = $this->service();

        $file = $service->registerFile($session->id, [
            'filename'  => 'IMG_007.CR2',
            'file_size' => 50_000_000,
            'file_type' => 'raw',
        ]);

        // Should not throw
        $service->removeTag($file->id, 'nonexistent-tag');

        $this->assertCount(0, $service->getTagsForFile($file->id));
    }

    // ─── getProgress() ────────────────────────────────────────────────────────

    /** @test */
    public function get_progress_reflects_accurate_upload_counts(): void
    {
        $session = $this->makeSession();
        $service = $this->service();

        $f1 = $service->registerFile($session->id, ['filename' => 'A.CR2', 'file_size' => 1000, 'file_type' => 'raw']);
        $f2 = $service->registerFile($session->id, ['filename' => 'B.CR2', 'file_size' => 1000, 'file_type' => 'raw']);
        $f3 = $service->registerFile($session->id, ['filename' => 'C.CR2', 'file_size' => 1000, 'file_type' => 'raw']);

        $service->recordFileUploaded($f1->id);
        $service->recordFileUploaded($f2->id);
        $service->recordFileFailed($f3->id, 'network timeout');

        $progress = $service->getProgress($session->id);

        $this->assertEquals(3, $progress['file_count']);
        $this->assertEquals(2, $progress['completed_file_count']);
        $this->assertEquals(1, $progress['failed_file_count']);
    }

    /** @test */
    public function get_progress_percent_complete_is_calculated_correctly(): void
    {
        $session = $this->makeSession();
        $service = $this->service();

        $f1 = $service->registerFile($session->id, ['filename' => 'A.CR2', 'file_size' => 1000, 'file_type' => 'raw']);
        $f2 = $service->registerFile($session->id, ['filename' => 'B.CR2', 'file_size' => 1000, 'file_type' => 'raw']);
        $f3 = $service->registerFile($session->id, ['filename' => 'C.CR2', 'file_size' => 1000, 'file_type' => 'raw']);
        $f4 = $service->registerFile($session->id, ['filename' => 'D.CR2', 'file_size' => 1000, 'file_type' => 'raw']);

        $service->recordFileUploaded($f1->id);
        $service->recordFileUploaded($f2->id);

        $session->refresh();
        $this->assertEquals(50, $session->percent_complete);
    }

    // ─── storage_path persistence ─────────────────────────────────────────────

    /** @test */
    public function ingest_file_storage_path_can_be_set_and_retrieved(): void
    {
        $session = $this->makeSession();

        $file = $this->service()->registerFile($session->id, [
            'filename'  => 'IMG_010.CR2',
            'file_size' => 50_000_000,
            'file_type' => 'raw',
        ]);

        $expectedPath = "ingest/{$session->id}/{$file->id}_IMG_010.CR2";
        $file->update(['storage_path' => $expectedPath]);

        $fresh = IngestFile::find($file->id);
        $this->assertEquals($expectedPath, $fresh->storage_path);
    }
}
