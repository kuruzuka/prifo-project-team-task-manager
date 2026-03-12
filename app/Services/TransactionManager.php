<?php

namespace App\Services;

use App\Models\TransactionLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service: TransactionManager
 *
 * Provides high-level transaction management with automatic audit logging.
 * Wraps database transactions with comprehensive logging, timing, and error tracking.
 *
 * FEATURES:
 * - Automatic transaction wrapping
 * - Complete audit trail with before/after values
 * - Duration tracking for performance monitoring
 * - Detailed error logging on failures
 * - Context capture (request info, route, etc.)
 *
 * USAGE:
 *   $transactionManager->execute(
 *       operationType: 'update',
 *       operationName: 'Update Task Status',
 *       entity: $task,
 *       callback: function () use ($task, $newStatus) {
 *           $task->update(['status_id' => $newStatus]);
 *           return $task;
 *       },
 *       oldValues: ['status_id' => $task->status_id],
 *   );
 */
class TransactionManager
{
    /**
     * Execute a callback within a logged transaction.
     *
     * @param  string  $operationType  Type constant from TransactionLog
     * @param  string  $operationName  Human-readable operation name
     * @param  Model|null  $entity  The primary entity being modified
     * @param  Closure  $callback  The database operations to execute
     * @param  array|null  $oldValues  State before changes (for update/delete)
     * @param  array  $context  Additional context information
     * @param  int  $attempts  Number of transaction retry attempts on deadlock
     * @return mixed Result of the callback
     *
     * @throws Throwable Re-throws any exception after logging
     */
    public function execute(
        string $operationType,
        string $operationName,
        ?Model $entity,
        Closure $callback,
        ?array $oldValues = null,
        array $context = [],
        int $attempts = 1
    ): mixed {
        $transactionId = Str::uuid()->toString();
        $startTime = microtime(true);

        // Create the transaction log entry
        $log = $this->createLogEntry(
            $transactionId,
            $operationType,
            $operationName,
            $entity,
            $oldValues,
            $context
        );

        try {
            // Execute within database transaction
            $result = DB::transaction(function () use ($callback) {
                return $callback();
            }, $attempts);

            // Calculate duration
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Capture new values from result if it's a model
            $newValues = $this->captureNewValues($result, $entity);

            // Update log with success
            $log->update([
                'status' => TransactionLog::STATUS_COMMITTED,
                'new_values' => $newValues,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            return $result;

        } catch (Throwable $e) {
            // Calculate duration even on failure
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Determine if rolled back or failed
            $status = $this->isDeadlockError($e)
                ? TransactionLog::STATUS_ROLLED_BACK
                : TransactionLog::STATUS_FAILED;

            // Update log with failure
            $log->update([
                'status' => $status,
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute a simple transaction without full logging.
     *
     * Use this for lower-priority operations that don't need audit trails.
     *
     * @param  Closure  $callback
     * @param  int  $attempts
     * @return mixed
     */
    public function executeSimple(Closure $callback, int $attempts = 1): mixed
    {
        return DB::transaction($callback, $attempts);
    }

    /**
     * Execute with pessimistic locking (SELECT FOR UPDATE).
     *
     * Use for high-conflict operations requiring exclusive access.
     *
     * @param  string  $operationType
     * @param  string  $operationName
     * @param  Model  $entity
     * @param  Closure  $callback  Receives locked entity as parameter
     * @param  array  $context
     * @return mixed
     */
    public function executeWithLock(
        string $operationType,
        string $operationName,
        Model $entity,
        Closure $callback,
        array $context = []
    ): mixed {
        return $this->execute(
            $operationType,
            $operationName,
            $entity,
            function () use ($entity, $callback) {
                // Acquire row lock
                $lockedEntity = $entity::query()
                    ->where($entity->getKeyName(), $entity->getKey())
                    ->lockForUpdate()
                    ->first();

                if (!$lockedEntity) {
                    throw new \RuntimeException('Entity not found or was deleted');
                }

                return $callback($lockedEntity);
            },
            $entity->getAttributes(),
            array_merge($context, ['lock_type' => 'pessimistic'])
        );
    }

    /**
     * Batch execute multiple operations in a single transaction.
     *
     * @param  string  $operationName
     * @param  array  $operations  Array of callbacks
     * @param  array  $context
     * @return array Results from each operation
     */
    public function executeBatch(
        string $operationName,
        array $operations,
        array $context = []
    ): array {
        $transactionId = Str::uuid()->toString();
        $startTime = microtime(true);
        $results = [];

        $log = TransactionLog::create([
            'transaction_id' => $transactionId,
            'operation_type' => TransactionLog::TYPE_BATCH,
            'operation_name' => $operationName,
            'actor_id' => auth()->id(),
            'actor_ip' => request()->ip(),
            'context' => array_merge($context, [
                'operation_count' => count($operations),
                'route' => request()->route()?->getName(),
            ]),
            'status' => TransactionLog::STATUS_STARTED,
            'started_at' => now(),
        ]);

        try {
            $results = DB::transaction(function () use ($operations) {
                $results = [];
                foreach ($operations as $key => $operation) {
                    $results[$key] = $operation();
                }
                return $results;
            });

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $log->update([
                'status' => TransactionLog::STATUS_COMMITTED,
                'new_values' => ['completed_operations' => count($results)],
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            return $results;

        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $log->update([
                'status' => TransactionLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Create the initial log entry.
     */
    protected function createLogEntry(
        string $transactionId,
        string $operationType,
        string $operationName,
        ?Model $entity,
        ?array $oldValues,
        array $context
    ): TransactionLog {
        return TransactionLog::create([
            'transaction_id' => $transactionId,
            'operation_type' => $operationType,
            'operation_name' => $operationName,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
            'actor_id' => auth()->id(),
            'actor_ip' => request()->ip(),
            'actor_user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'context' => array_merge($context, [
                'route' => request()->route()?->getName(),
                'url' => request()->path(),
            ]),
            'status' => TransactionLog::STATUS_STARTED,
            'started_at' => now(),
        ]);
    }

    /**
     * Capture new values from result.
     */
    protected function captureNewValues($result, ?Model $originalEntity): ?array
    {
        if ($result instanceof Model) {
            return $result->getAttributes();
        }

        if ($originalEntity) {
            return $originalEntity->fresh()?->getAttributes();
        }

        return null;
    }

    /**
     * Check if the exception is a deadlock error.
     */
    protected function isDeadlockError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout');
    }
}
