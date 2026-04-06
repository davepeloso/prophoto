<?php

namespace ProPhoto\Debug\Console\Commands;

use Illuminate\Console\Command;
use ProPhoto\Debug\Services\IngestTracer;

class CleanupTracesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'debug:cleanup
                            {--days= : Number of days to retain (default: 7)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Delete debug traces older than the retention period';

    /**
     * Execute the console command.
     */
    public function handle(IngestTracer $tracer): int
    {
        $days = $this->option('days') ?? config('debug.retention_days', 7);
        $dryRun = $this->option('dry-run');

        $this->info("Cleaning up debug traces older than {$days} days...");

        if ($dryRun) {
            $count = \ProPhoto\Debug\Models\IngestTrace::expired($days)->count();
            $this->info("Would delete {$count} trace records (dry run).");

            return Command::SUCCESS;
        }

        $deleted = $tracer->cleanup($days);

        $this->info("Deleted {$deleted} trace records.");

        return Command::SUCCESS;
    }
}
