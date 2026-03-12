<?php

namespace App\Concerns;

use App\Exceptions\StaleModelException;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait: HasOptimisticLocking
 *
 * Implements optimistic locking using a version column. This prevents
 * "lost updates" when multiple users edit the same record concurrently.
 *
 * How it works:
 * - When reading a record, the current version is stored
 * - When updating, we check if the version in the database matches
 * - If versions differ, someone else modified the record → reject update
 * - If versions match, update proceeds and version is incremented (via trigger)
 *
 * Usage:
 *   use HasOptimisticLocking;
 *
 *   // In controller:
 *   $task = Task::find($id);
 *   $task->fill($validated);
 *   $task->saveWithVersionCheck($request->input('version'));
 *
 * Why optimistic over pessimistic locking?
 * - Better for web apps with longer read-modify-write cycles
 * - No blocking of concurrent readers
 * - More scalable under high load
 * - Graceful conflict handling (can show user-friendly message)
 *
 * @mixin Model
 */
trait HasOptimisticLocking
{
    /**
     * The version column name.
     */
    protected static function getVersionColumn(): string
    {
        return 'version';
    }

    /**
     * Initialize the trait for the model instance.
     * Sets default version for new models.
     */
    public function initializeHasOptimisticLocking(): void
    {
        // Set default version for new models
        if (!$this->exists && !isset($this->attributes[static::getVersionColumn()])) {
            $this->attributes[static::getVersionColumn()] = 1;
        }
    }

    /**
     * Save the model with version check.
     *
     * @param  int|null  $expectedVersion  The version the caller expects (from frontend)
     * @return bool
     *
     * @throws StaleModelException When versions don't match (concurrent modification)
     */
    public function saveWithVersionCheck(?int $expectedVersion = null, array $options = []): bool
    {
        // For new models, just save normally
        if (!$this->exists) {
            return $this->save($options);
        }

        // Get version column
        $versionColumn = static::getVersionColumn();

        // If no expected version provided, use the model's current version
        if ($expectedVersion === null) {
            $expectedVersion = $this->getOriginal($versionColumn);
        }

        // Check current database version
        $currentVersion = static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->value($versionColumn);

        if ($currentVersion === null) {
            throw new StaleModelException(
                $this,
                'Record no longer exists (may have been deleted)'
            );
        }

        if ($currentVersion != $expectedVersion) {
            throw new StaleModelException(
                $this,
                "Record was modified by another user. Expected version {$expectedVersion}, found {$currentVersion}."
            );
        }

        // Version column will be auto-incremented by database trigger
        // Don't manually set it here to avoid conflicts
        return $this->save($options);
    }

    /**
     * Update with version check.
     *
     * @param  array<string, mixed>  $attributes
     * @param  int|null  $expectedVersion
     * @return bool
     *
     * @throws StaleModelException
     */
    public function updateWithVersionCheck(array $attributes, ?int $expectedVersion = null): bool
    {
        $this->fill($attributes);
        return $this->saveWithVersionCheck($expectedVersion);
    }

    /**
     * Get the current version from the database (fresh read).
     */
    public function freshVersion(): ?int
    {
        return static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->value(static::getVersionColumn());
    }

    /**
     * Check if the model is stale (version differs from database).
     */
    public function isStale(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $currentVersion = $this->freshVersion();

        return $currentVersion !== null
            && $currentVersion != $this->{static::getVersionColumn()};
    }

    /**
     * Scope to find by ID and version (for atomic operations).
     */
    public function scopeWhereVersion($query, int $version)
    {
        return $query->where(static::getVersionColumn(), $version);
    }

    /**
     * Attempt atomic update with version check.
     *
     * Returns affected row count (0 if version mismatch, 1 if successful).
     * This is a lower-level method that uses UPDATE ... WHERE for true atomicity.
     *
     * @param  array<string, mixed>  $attributes
     * @param  int  $expectedVersion
     * @return int Affected rows (0 or 1)
     */
    public function atomicUpdate(array $attributes, int $expectedVersion): int
    {
        $versionColumn = static::getVersionColumn();

        return static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->where($versionColumn, $expectedVersion)
            ->update($attributes);
    }
}
