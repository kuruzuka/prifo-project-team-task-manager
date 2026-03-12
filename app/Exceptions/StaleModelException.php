<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Exception thrown when attempting to update a stale model.
 *
 * This occurs when:
 * 1. User A reads record (version = 5)
 * 2. User B updates record (version → 6)
 * 3. User A tries to save their changes (expects version 5, finds 6)
 *
 * The frontend should handle this by:
 * 1. Showing "This record was modified by another user"
 * 2. Offering to refresh and merge changes
 * 3. Or discarding local changes
 */
class StaleModelException extends Exception
{
    /**
     * The model that was stale.
     */
    protected Model $model;

    /**
     * Create a new stale model exception.
     */
    public function __construct(Model $model, string $message = 'Record was modified by another user.')
    {
        $this->model = $model;
        parent::__construct($message);
    }

    /**
     * Get the stale model.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the model class name.
     */
    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Get the model's primary key value.
     */
    public function getModelId(): mixed
    {
        return $this->model->getKey();
    }

    /**
     * Report this exception for monitoring.
     */
    public function report(): bool
    {
        // Log stale model conflicts for monitoring
        logger()->info('Optimistic locking conflict', [
            'model' => $this->getModelClass(),
            'id' => $this->getModelId(),
            'user_id' => auth()->id(),
        ]);

        return false; // Allow default handling to continue
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'STALE_MODEL',
                'model' => class_basename($this->getModelClass()),
                'id' => $this->getModelId(),
            ], 409); // 409 Conflict
        }

        return back()
            ->withInput()
            ->withErrors([
                'version' => $this->getMessage(),
            ]);
    }
}
