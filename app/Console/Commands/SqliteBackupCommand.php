<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SqliteBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqlite:backup
                            {--restore= : Path to backup SQLite file to restore}
                            {--keep=14 : Number of backup snapshots to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a consistent, hot SQLite backup using VACUUM INTO with 14-snapshot rotation, or restore a snapshot';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $restorePath = $this->option('restore');

        if ($restorePath) {
            return $this->handleRestore($restorePath);
        }

        return $this->handleBackup();
    }

    /**
     * Perform VACUUM INTO backup and prune older snapshots.
     */
    protected function handleBackup(): int
    {
        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y_m_d_His');
        $backupFile = "{$backupDir}/inkwell_backup_{$timestamp}.sqlite";

        $this->info("Creating SQLite backup snapshot using VACUUM INTO...");

        try {
            // VACUUM INTO creates a transactionally consistent, vacuumed copy
            DB::statement('VACUUM INTO ?', [$backupFile]);

            if (! File::exists($backupFile) || File::size($backupFile) === 0) {
                $this->error("Backup file was not created or is empty: {$backupFile}");

                return Command::FAILURE;
            }

            $sizeMb = round(File::size($backupFile) / (1024 * 1024), 2);
            $this->info("Backup successfully written: {$backupFile} ({$sizeMb} MB)");

            // Prune snapshots beyond the retention threshold
            $this->pruneOldBackups($backupDir, (int) $this->option('keep'));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("SQLite VACUUM INTO failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Prune backups older than the retention count.
     */
    protected function pruneOldBackups(string $backupDir, int $keepCount): void
    {
        $files = File::glob("{$backupDir}/inkwell_backup_*.sqlite");

        if (count($files) <= $keepCount) {
            $this->line("Retention: " . count($files) . " of max {$keepCount} backups stored.");

            return;
        }

        // Sort ascending by modification time (oldest first)
        usort($files, fn ($a, $b) => File::lastModified($a) <=> File::lastModified($b));

        $toDeleteCount = count($files) - $keepCount;
        $filesToDelete = array_slice($files, 0, $toDeleteCount);

        foreach ($filesToDelete as $file) {
            File::delete($file);
            $this->line("Pruned old backup: " . basename($file));
        }

        $this->info("Pruning complete: retained {$keepCount} most recent backups.");
    }

    /**
     * Restore database from a backup file and verify integrity.
     */
    protected function handleRestore(string $restorePath): int
    {
        if (! File::exists($restorePath)) {
            $this->error("Restore file does not exist at: {$restorePath}");

            return Command::FAILURE;
        }

        $targetDbPath = database_path('database.sqlite');
        $this->warn("Restoring database from: {$restorePath} to {$targetDbPath}");

        try {
            // Disconnect current database connection to release file locks
            DB::disconnect();

            // Copy restored backup file to database path
            File::copy($restorePath, $targetDbPath);

            // Reconnect and verify integrity
            DB::reconnect();
            $check = DB::select('PRAGMA integrity_check;');
            $result = $check[0]->integrity_check ?? 'failed';

            if ($result !== 'ok') {
                $this->error("Integrity check failed after restore: {$result}");

                return Command::FAILURE;
            }

            $this->info("Database restored successfully! PRAGMA integrity_check: ok.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Restore failed: " . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
