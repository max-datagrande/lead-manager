<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanupOldLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up log files older than 15 days and remove resulting empty directories.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('logs');
        $cutoffDate = Carbon::now()->subDays(15);

        $this->info('Starting cleanup of log files older than 15 days...');

        $allFiles = File::allFiles($logPath);

        foreach ($allFiles as $file) {
            // We only care about .log files
            if (strtolower($file->getExtension()) !== 'log') {
                continue;
            }

            if (Carbon::createFromTimestamp($file->getMTime())->lt($cutoffDate)) {
                File::delete($file->getPathname());
                $this->line('Deleted file: ' . $file->getPathname());
            }
        }

        $this->info('Finished cleaning up log files.');
        $this->info('Now cleaning up empty directories...');

        $allDirectories = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($logPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $path) {
            if ($path->isDir()) {
                $allDirectories[] = $path->getPathname();
            }
        }

        // Process directories from deepest to shallowest
        foreach ($allDirectories as $directory) {
            // Check if the directory is empty
            // Using scandir to check for actual files/directories, excluding . and ..
            $items = array_diff(scandir($directory), ['.', '..']);
            if (empty($items)) {
                File::deleteDirectory($directory);
                $this->line('Deleted empty directory: ' . $directory);
            }
        }

        $this->info('Log cleanup complete.');
    }
}
