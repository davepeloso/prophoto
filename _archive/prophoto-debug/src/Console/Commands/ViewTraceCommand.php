<?php

namespace ProPhoto\Debug\Console\Commands;

use Illuminate\Console\Command;
use ProPhoto\Debug\Services\IngestTracer;

class ViewTraceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'debug:trace
                            {uuid : The UUID of the ProxyImage to view traces for}
                            {--summary : Show only a summary instead of all traces}';

    /**
     * The console command description.
     */
    protected $description = 'View debug traces for a specific upload';

    /**
     * Execute the console command.
     */
    public function handle(IngestTracer $tracer): int
    {
        $uuid = $this->argument('uuid');

        $traces = $tracer->getTrace($uuid);

        if ($traces->isEmpty()) {
            $this->warn("No traces found for UUID: {$uuid}");

            return Command::SUCCESS;
        }

        if ($this->option('summary')) {
            $this->displaySummary($tracer, $uuid);
        } else {
            $this->displayTraces($traces);
        }

        return Command::SUCCESS;
    }

    protected function displayTraces($traces): void
    {
        $rows = $traces->map(function ($trace) {
            return [
                $trace->trace_type,
                $trace->method_tried,
                $trace->method_order,
                $trace->success ? '<fg=green>Yes</>' : '<fg=red>No</>',
                $trace->failure_reason ?? '-',
                $trace->duration_ms ? "{$trace->duration_ms}ms" : '-',
                $trace->created_at->format('H:i:s.v'),
            ];
        })->toArray();

        $this->table(
            ['Type', 'Method', 'Order', 'Success', 'Failure Reason', 'Duration', 'Time'],
            $rows
        );
    }

    protected function displaySummary(IngestTracer $tracer, string $uuid): void
    {
        $summary = $tracer->getTraceSummary($uuid);
        $winningMethods = $tracer->getWinningMethods($uuid);

        $this->info("Trace Summary for {$uuid}");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Attempts', $summary['total_attempts']],
                ['Successful', $summary['successful']],
                ['Failed', $summary['failed']],
            ]
        );

        $this->newLine();
        $this->info('Winning Methods by Type:');

        foreach ($winningMethods as $type => $info) {
            $this->line("  <fg=cyan>{$type}</>:");
            $this->line("    Method: <fg=green>{$info['method']}</> (order: {$info['order']})");
            if ($info['duration_ms']) {
                $this->line("    Duration: {$info['duration_ms']}ms");
            }
            if ($info['size']) {
                $this->line("    Size: " . number_format($info['size']) . " bytes");
            }
            if ($info['dimensions']) {
                $this->line("    Dimensions: {$info['dimensions']}");
            }
        }
    }
}
