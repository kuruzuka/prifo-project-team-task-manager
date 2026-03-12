<?php

namespace App\Services;

use App\Exceptions\LockAcquisitionException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service: LockManager
 *
 * Provides pessimistic locking mechanisms for high-conflict operations.
 * Supports both database-level locks (SELECT FOR UPDATE) and
 * application-level advisory locks (using cache).
 *
 * WHEN TO USE:
 * - Database locks: Short operations within a transaction
 * - Advisory locks: Longer operations or cross-transaction coordination
 *
 * WHEN NOT TO USE:
 * - Simple read operations
 * - Low-conflict updates (use optimistic locking instead)
 * - Operations that can tolerate eventual consistency
 */
class LockManager
{
    /**
     * Default lock timeout in seconds.
     */
    protected int $defaultTimeout = 30;

    /**
     * Default wait timeout for lock acquisition.
     */
    protected int $defaultWaitTimeout = 5;

    /**
     * Execute callback with database row lock (SELECT FOR UPDATE).
     *
     * The row is locked for the duration of the transaction.
     * Other transactions will wait (up to timeout) or fail.
     *
     * @param  Model  $model  The model to lock
     * @param  Closure  $callback  Receives locked model as parameter
     * @param  int|null  $waitTimeout  Max seconds to wait for lock
     * @return mixed Result from callback
     *
     * @throws LockAcquisitionException If lock cannot be acquired
     */
    public function withRowLock(Model $model, Closure $callback, ?int $waitTimeout = null): mixed
    {
        $waitTimeout = $waitTimeout ?? $this->defaultWaitTimeout;

        return DB::transaction(function () use ($model, $callback, $waitTimeout) {
            // Set lock wait timeout for this session
            DB::statement("SET innodb_lock_wait_timeout = {$waitTimeout}");

            try {
                // Acquire exclusive lock on the row
                $lockedModel = $model::query()
                    ->where($model->getKeyName(), $model->getKey())
                    ->lockForUpdate()
                    ->first();

                if (!$lockedModel) {
                    throw new LockAcquisitionException(
                        "Cannot lock {$model->getTable()} #{$model->getKey()}: record not found"
                    );
                }

                return $callback($lockedModel);

            } catch (\Illuminate\Database\QueryException $e) {
                if ($this->isLockTimeoutError($e)) {
                    throw new LockAcquisitionException(
                        "Cannot lock {$model->getTable()} #{$model->getKey()}: lock timeout exceeded",
                        previous: $e
                    );
                }
                throw $e;
            }
        });
    }

    /**
     * Execute callback with shared (read) lock.
     *
     * Allows other readers but blocks writers.
     * Use for read-heavy operations that need consistent data.
     *
     * @param  Model  $model
     * @param  Closure  $callback
     * @return mixed
     */
    public function withSharedLock(Model $model, Closure $callback): mixed
    {
        return DB::transaction(function () use ($model, $callback) {
            $lockedModel = $model::query()
                ->where($model->getKeyName(), $model->getKey())
                ->sharedLock()
                ->first();

            if (!$lockedModel) {
                throw new LockAcquisitionException(
                    "Cannot lock {$model->getTable()} #{$model->getKey()}: record not found"
                );
            }

            return $callback($lockedModel);
        });
    }

    /**
     * Acquire an advisory lock using cache.
     *
     * Advisory locks are application-level and work across transactions.
     * Useful for longer operations or distributed systems.
     *
     * @param  string  $key  Unique lock identifier
     * @param  Closure  $callback  Execute while holding lock
     * @param  int|null  $timeout  Lock timeout in seconds
     * @param  int|null  $waitTimeout  Max seconds to wait for lock
     * @return mixed Result from callback
     *
     * @throws LockAcquisitionException If lock cannot be acquired
     */
    public function withAdvisoryLock(
        string $key,
        Closure $callback,
        ?int $timeout = null,
        ?int $waitTimeout = null
    ): mixed {
        $timeout = $timeout ?? $this->defaultTimeout;
        $waitTimeout = $waitTimeout ?? $this->defaultWaitTimeout;

        $lock = Cache::lock("advisory_lock:{$key}", $timeout);

        $acquired = $lock->block($waitTimeout);

        if (!$acquired) {
            throw new LockAcquisitionException(
                "Cannot acquire advisory lock '{$key}': timeout after {$waitTimeout} seconds"
            );
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Generate advisory lock key for a model.
     *
     * @param  Model  $model
     * @return string
     */
    public function modelLockKey(Model $model): string
    {
        return "{$model->getTable()}:{$model->getKey()}";
    }

    /**
     * Execute callback with model advisory lock.
     *
     * Combines model key generation with advisory locking.
     *
     * @param  Model  $model
     * @param  Closure  $callback
     * @param  int|null  $timeout
     * @param  int|null  $waitTimeout
     * @return mixed
     */
    public function withModelLock(
        Model $model,
        Closure $callback,
        ?int $timeout = null,
        ?int $waitTimeout = null
    ): mixed {
        return $this->withAdvisoryLock(
            $this->modelLockKey($model),
            $callback,
            $timeout,
            $waitTimeout
        );
    }

    /**
     * Try to acquire a lock without waiting.
     *
     * Returns null if lock cannot be acquired immediately.
     *
     * @param  string  $key
     * @param  Closure  $callback
     * @param  int|null  $timeout
     * @return mixed|null
     */
    public function tryLock(string $key, Closure $callback, ?int $timeout = null): mixed
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        $lock = Cache::lock("advisory_lock:{$key}", $timeout);

        if (!$lock->get()) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Lock multiple rows for update in a single query.
     *
     * @param  string  $modelClass
     * @param  array<int>  $ids
     * @param  Closure  $callback  Receives collection of locked models
     * @return mixed
     */
    public function withMultiRowLock(string $modelClass, array $ids, Closure $callback): mixed
    {
        return DB::transaction(function () use ($modelClass, $ids, $callback) {
            // Lock all rows at once, sorted by ID to prevent deadlocks
            $lockedModels = $modelClass::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedModels->count() !== count($ids)) {
                $missingIds = array_diff($ids, $lockedModels->pluck('id')->toArray());
                throw new LockAcquisitionException(
                    "Cannot lock all records: missing IDs " . implode(', ', $missingIds)
                );
            }

            return $callback($lockedModels);
        });
    }

    /**
     * Check if error is a lock timeout.
     */
    protected function isLockTimeoutError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'lock wait timeout')
            || str_contains($message, 'deadlock');
    }
}
