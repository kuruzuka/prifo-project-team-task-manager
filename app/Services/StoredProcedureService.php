<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service: StoredProcedureService
 *
 * Provides a clean Laravel interface to database stored procedures.
 * Wraps raw SQL calls with type safety and error handling.
 *
 * Available procedures:
 * - assignTaskWithAudit: Atomically assign user to task with audit trail
 * - bulkUpdateTaskStatus: Update multiple tasks' status in one transaction
 * - transferProjectOwnership: Transfer project manager atomically
 * - archiveCompletedTasks: Batch archive old completed tasks
 */
class StoredProcedureService
{
    /**
     * Result from stored procedure calls.
     */
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_NOT_FOUND = 'NOT_FOUND';
    public const STATUS_ALREADY_ASSIGNED = 'ALREADY_ASSIGNED';
    public const STATUS_USER_NOT_FOUND = 'USER_NOT_FOUND';
    public const STATUS_NO_CHANGE = 'NO_CHANGE';
    public const STATUS_ERROR = 'ERROR';

    /**
     * Assign a user to a task using stored procedure.
     *
     * Benefits over controller-level implementation:
     * - Single database round-trip
     * - Atomic assignment + audit log creation
     * - Race condition protection
     *
     * @param  int  $taskId
     * @param  int  $userId
     * @param  int  $assignedBy
     * @return array{status: string, message: string}
     */
    public function assignTaskWithAudit(int $taskId, int $userId, int $assignedBy): array
    {
        $results = DB::select('
            CALL sp_assign_task_with_audit(?, ?, ?, @status, @message);
            SELECT @status AS status, @message AS message;
        ', [$taskId, $userId, $assignedBy]);

        // MySQL returns multiple result sets; get the last one with our OUT params
        $output = collect($results)->last();

        return [
            'status' => $output->status ?? self::STATUS_ERROR,
            'message' => $output->message ?? 'Unknown error',
        ];
    }

    /**
     * Bulk update task statuses using stored procedure.
     *
     * All tasks are updated in a single transaction. If any task
     * has an invalid transition, the entire batch is rolled back.
     *
     * @param  array<int>  $taskIds
     * @param  int  $newStatusId
     * @param  int  $actorId
     * @return array{success_count: int, failed_count: int, failed_tasks: array}
     */
    public function bulkUpdateTaskStatus(array $taskIds, int $newStatusId, int $actorId): array
    {
        $taskIdsJson = json_encode($taskIds);

        $results = DB::select('
            CALL sp_bulk_update_task_status(?, ?, ?, @success, @failed, @failed_tasks);
            SELECT @success AS success_count, @failed AS failed_count, @failed_tasks AS failed_tasks;
        ', [$taskIdsJson, $newStatusId, $actorId]);

        $output = collect($results)->last();

        return [
            'success_count' => (int) ($output->success_count ?? 0),
            'failed_count' => (int) ($output->failed_count ?? 0),
            'failed_tasks' => json_decode($output->failed_tasks ?? '[]', true),
        ];
    }

    /**
     * Transfer project ownership using stored procedure.
     *
     * @param  int  $projectId
     * @param  int  $newManagerId
     * @param  int  $actorId
     * @return array{status: string, message: string}
     */
    public function transferProjectOwnership(int $projectId, int $newManagerId, int $actorId): array
    {
        $results = DB::select('
            CALL sp_transfer_project_ownership(?, ?, ?, @status, @message);
            SELECT @status AS status, @message AS message;
        ', [$projectId, $newManagerId, $actorId]);

        $output = collect($results)->last();

        return [
            'status' => $output->status ?? self::STATUS_ERROR,
            'message' => $output->message ?? 'Unknown error',
        ];
    }

    /**
     * Archive completed tasks older than specified days.
     *
     * @param  int  $daysOld
     * @param  int  $actorId
     * @return int Number of archived tasks
     */
    public function archiveCompletedTasks(int $daysOld, int $actorId): int
    {
        $results = DB::select('
            CALL sp_archive_completed_tasks(?, ?, @count);
            SELECT @count AS archived_count;
        ', [$daysOld, $actorId]);

        $output = collect($results)->last();

        return (int) ($output->archived_count ?? 0);
    }

    /**
     * Check if a stored procedure call was successful.
     */
    public function isSuccess(array $result): bool
    {
        return ($result['status'] ?? '') === self::STATUS_SUCCESS;
    }

    /**
     * Get user-friendly error message from stored procedure result.
     */
    public function getErrorMessage(array $result): string
    {
        $status = $result['status'] ?? self::STATUS_ERROR;
        $message = $result['message'] ?? '';

        return match ($status) {
            self::STATUS_NOT_FOUND => 'The requested record was not found.',
            self::STATUS_ALREADY_ASSIGNED => 'This user is already assigned.',
            self::STATUS_USER_NOT_FOUND => 'The specified user was not found.',
            self::STATUS_NO_CHANGE => 'No changes were made.',
            self::STATUS_ERROR => 'An error occurred: ' . $message,
            default => $message,
        };
    }
}
