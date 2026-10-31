<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SqliteCheckpointCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqlite:checkpoint {--mode=truncate : Checkpoint mode: passive, full, restart, or truncate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a SQLite WAL checkpoint to flush and truncate the write-ahead log';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mode = strtoupper($this->option('mode'));
        $validModes = ['PASSIVE', 'FULL', 'RESTART', 'TRUNCATE'];

        if (! in_array($mode, $validModes, true)) {
            $this->error("Invalid mode '{$mode}'. Valid modes: " . implode(', ', $validModes));

            return Command::FAILURE;
        }

        $this->info("Executing PRAGMA wal_checkpoint({$mode})...");

        try {
            $result = DB::select("PRAGMA wal_checkpoint({$mode});");

            if (! empty($result)) {
                $row = (array) $result[0];
                $busy = $row['busy'] ?? 0;
                $logPages = $row['log'] ?? 0;
                $checkpointedPages = $row['checkpointed'] ?? 0;

                $this->table(['Busy Status', 'WAL Log Pages', 'Checkpointed Pages'], [
                    [$busy ? 'BUSY (blocked)' : 'CLEARED (0)', $logPages, $checkpointedPages],
                ]);

                if ($busy) {
                    $this->warn("Checkpoint completed with active reader/writer locks remaining.");
                } else {
                    $this->info("WAL checkpoint ({$mode}) completed cleanly.");
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Checkpoint failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
