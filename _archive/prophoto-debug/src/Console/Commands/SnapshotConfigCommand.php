<?php

namespace ProPhoto\Debug\Console\Commands;

use Illuminate\Console\Command;
use ProPhoto\Debug\Services\ConfigRecorder;

class SnapshotConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'debug:snapshot
                            {name : A name for this configuration snapshot}
                            {--description= : Optional description of what is being tested}';

    /**
     * The console command description.
     */
    protected $description = 'Create a snapshot of the current ingest configuration for testing';

    /**
     * Execute the console command.
     */
    public function handle(ConfigRecorder $recorder): int
    {
        $name = $this->argument('name');
        $description = $this->option('description');

        $this->info("Creating configuration snapshot: {$name}");

        try {
            $snapshot = $recorder->snapshot($name, $description);

            $this->info("Snapshot created successfully!");
            $this->newLine();

            $this->table(
                ['Setting', 'Value'],
                [
                    ['ID', $snapshot->id],
                    ['Name', $snapshot->name],
                    ['Created', $snapshot->created_at->format('Y-m-d H:i:s')],
                    ['Thumbnail Quality', $snapshot->thumbnail_quality ?? 'N/A'],
                    ['Preview Quality', $snapshot->preview_quality ?? 'N/A'],
                    ['Preview Max Dimension', $snapshot->preview_max_dimension ?? 'N/A'],
                    ['ExifTool Binary', $snapshot->exiftool_binary ?? 'N/A'],
                    ['ExifTool Speed Mode', $snapshot->exiftool_speed_mode ?? 'N/A'],
                    ['Queue Connection', $snapshot->queue_connection ?? 'N/A'],
                    ['Worker Count', $snapshot->worker_count ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create snapshot: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
