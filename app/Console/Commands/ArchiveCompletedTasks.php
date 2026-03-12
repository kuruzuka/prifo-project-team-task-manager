<?php

namespace App\Console\Commands;

use App\Services\StoredProcedureService;
use Illuminate\Console\Command;

/**
 * Command: ArchiveCompletedTasks
 *
 * Archives (soft-deletes) completed tasks that are older than a specified
 * number of days. Uses a stored procedure for atomic batch operation.
 *
 * USAGE:
 *   php artisan tasks:archive              # Archive tasks older than 30 days
 *   php artisan tasks:archive --days=60    # Archive tasks older than 60 days
 *   php artisan tasks:archive --dry-run    # Preview without archiving
 *
 * SCHEDULING:
 *   Add to routes/console.php:
 *   Schedule::command('tasks:archive')->daily();
 */
class ArchiveCompletedTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:archive
                            {--days=30 : Archive tasks completed more than N days ago}
                            {--dry-run : Preview without archiving}';

    /**
     * The console command description.
     */
    protected $description = 'Archive completed tasks older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(StoredProcedureService $procedureService): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Looking for tasks completed more than {$days} days ago...");

        if ($dryRun) {
            // Count tasks that would be archived
            $count = \App\Models\Task::query()
                ->whereHas('status', fn ($q) => $q->where('name', 'Done'))
                ->whereNull('deleted_at')
                ->where('updated_at', '<', now()->subDays($days))
                ->count();

            $this->warn("[DRY RUN] Would archive {$count} tasks.");
            return self::SUCCESS;
        }

        // Use stored procedure for atomic operation
        // System actor ID = 0 (could be a dedicated system user)
        $archivedCount = $procedureService->archiveCompletedTasks($days, auth()->id() ?? 0);

        if ($archivedCount > 0) {
            $this->info("Successfully archived {$archivedCount} tasks.");
        } else {
            $this->info('No tasks to archive.');
        }

        return self::SUCCESS;
    }
}
