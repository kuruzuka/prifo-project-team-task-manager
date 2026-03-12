<?php

namespace App\Concerns;

use App\Exceptions\StaleModelException;
use App\Models\TransactionLog;
use App\Services\LockManager;
use App\Services\TransactionManager;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

/**
 * Trait: UsesConcurrencyControl
 *
 * Provides controllers with easy access to transaction management,
 * locking, and optimistic concurrency control.
 *
 * Usage example:
 *   use UsesConcurrencyControl;
 *
 *   public function update(Request $request, Task $task)
 *   {
 *       return $this->withTransaction(
 *           operationName: 'Update Task',
 *           entity: $task,
 *           callback: function () use ($request, $task) {
 *               $task->update($request->validated());
 *               return redirect()->back()->with('success', 'Updated');
 *           },
 *       );
 *   }
 */
trait UsesConcurrencyControl
{
    /**
     * Execute callback within a logged transaction.
     *
     * @param  string  $operationName  Human-readable name for audit log
     * @param  Model|null  $entity  Primary entity being modified
     * @param  Closure  $callback  The operation to execute
     * @param  string  $operationType  Type constant (default: update)
     * @param  array  $context  Additional context for logging
     * @return mixed Result from callback
     */
    protected function withTransaction(
        string $operationName,
        ?Model $entity,
        Closure $callback,
        string $operationType = TransactionLog::TYPE_UPDATE,
        array $context = []
    ): mixed {
        $manager = $this->getTransactionManager();

        $oldValues = $entity?->getAttributes();

        return $manager->execute(
            $operationType,
            $operationName,
            $entity,
            $callback,
            $oldValues,
            $context
        );
    }

    /**
     * Execute callback within a logged transaction with row lock.
     *
     * Use for operations with high conflict potential (e.g., assignments).
     *
     * @param  string  $operationName
     * @param  Model  $entity
     * @param  Closure  $callback  Receives locked entity
     * @param  string  $operationType
     * @param  array  $context
     * @return mixed
     */
    protected function withLockedTransaction(
        string $operationName,
        Model $entity,
        Closure $callback,
        string $operationType = TransactionLog::TYPE_UPDATE,
        array $context = []
    ): mixed {
        return $this->getTransactionManager()->executeWithLock(
            $operationType,
            $operationName,
            $entity,
            $callback,
            $context
        );
    }

    /**
     * Execute callback with optimistic locking validation.
     *
     * Checks the version before making changes.
     * Also catches database trigger errors (SQLSTATE 45000) and converts
     * them to validation exceptions for user-friendly error messages.
     *
     * @param  Model  $entity  Entity with HasOptimisticLocking trait
     * @param  int|null  $expectedVersion  Version from client
     * @param  Closure  $callback  The update operation
     * @param  string  $operationName
     * @return mixed
     *
     * @throws StaleModelException If version mismatch
     * @throws ValidationException If database trigger rejects the operation
     */
    protected function withVersionCheck(
        Model $entity,
        ?int $expectedVersion,
        Closure $callback,
        string $operationName = 'Update Record'
    ): mixed {
        // If version is provided and entity supports optimistic locking
        if ($expectedVersion !== null && method_exists($entity, 'isStale')) {
            $currentVersion = $entity->freshVersion();

            if ($currentVersion !== $expectedVersion) {
                throw new StaleModelException(
                    $entity,
                    "This record was modified by another user. Please refresh and try again."
                );
            }
        }

        try {
            return $this->withTransaction($operationName, $entity, $callback);
        } catch (QueryException $e) {
            // Handle database trigger errors (SQLSTATE 45000)
            // Check both the error code and the message for trigger errors
            // as different database drivers may report the code differently
            $isTriggerError = $e->getCode() === '45000'
                || $e->getCode() === 45000
                || str_contains($e->getMessage(), 'SQLSTATE[45000]');

            if ($isTriggerError) {
                $message = $this->extractTriggerErrorMessage($e);
                throw ValidationException::withMessages([
                    'database' => [$message],
                ]);
            }

            throw $e;
        }
    }

    /**
     * Extract user-friendly error message from trigger exception.
     */
    protected function extractTriggerErrorMessage(QueryException $e): string
    {
        $message = $e->getMessage();

        // MySQL format: SQLSTATE[45000]: <<Unknown error>>: 1644 Invalid status transition: To Do -> Done
        // Try to extract the message after the error code
        if (preg_match('/\d{4}\s+(.+?)(?:\s*\(Connection:|$)/i', $message, $matches)) {
            return trim($matches[1]);
        }

        // Try MESSAGE_TEXT format
        if (preg_match('/MESSAGE_TEXT[:\s]+(.+?)(?:\s*\(|$)/i', $message, $matches)) {
            return trim($matches[1]);
        }

        // Try to extract message after SQLSTATE info
        if (preg_match('/SQLSTATE\[45000\]:[^:]*:\s*\d*\s*(.+)/i', $message, $matches)) {
            $extracted = trim($matches[1]);
            // Clean up connection info
            $extracted = preg_replace('/\s*\(Connection:.*$/i', '', $extracted);
            return $extracted ?: 'This operation is not allowed.';
        }

        return 'This operation violates a business rule. Please check your input.';
    }

    /**
     * Execute a batch of operations in a single transaction.
     *
     * @param  string  $operationName
     * @param  array<Closure>  $operations
     * @param  array  $context
     * @return array Results from each operation
     */
    protected function withBatchTransaction(
        string $operationName,
        array $operations,
        array $context = []
    ): array {
        return $this->getTransactionManager()->executeBatch(
            $operationName,
            $operations,
            $context
        );
    }

    /**
     * Execute a simple transaction without full logging.
     *
     * Use for lower-priority operations.
     *
     * @param  Closure  $callback
     * @param  int  $attempts  Retry count for deadlocks
     * @return mixed
     */
    protected function withSimpleTransaction(Closure $callback, int $attempts = 1): mixed
    {
        return $this->getTransactionManager()->executeSimple($callback, $attempts);
    }

    /**
     * Get the transaction manager instance.
     */
    protected function getTransactionManager(): TransactionManager
    {
        return App::make(TransactionManager::class);
    }

    /**
     * Get the lock manager instance.
     */
    protected function getLockManager(): LockManager
    {
        return App::make(LockManager::class);
    }
}
