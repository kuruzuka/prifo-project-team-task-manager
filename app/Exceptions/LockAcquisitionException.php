<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when a lock cannot be acquired.
 *
 * This occurs when:
 * - Row lock timeout: Another transaction holds the row too long
 * - Advisory lock timeout: Another process holds the lock
 * - Deadlock: Circular lock dependency detected
 *
 * The frontend should handle this by:
 * 1. Showing "The system is busy, please try again"
 * 2. Optionally auto-retrying after a short delay
 */
class LockAcquisitionException extends Exception
{
    /**
     * Create a new lock acquisition exception.
     */
    public function __construct(
        string $message = 'Could not acquire lock',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report this exception for monitoring.
     */
    public function report(): bool
    {
        // Log lock conflicts for monitoring
        logger()->warning('Lock acquisition failed', [
            'message' => $this->getMessage(),
            'user_id' => auth()->id(),
            'url' => request()->path(),
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
                'message' => 'The system is busy processing another request. Please try again.',
                'error' => 'LOCK_TIMEOUT',
                'retry_after' => 2, // Suggest retry after 2 seconds
            ], 423); // 423 Locked
        }

        return back()
            ->withInput()
            ->withErrors([
                'error' => 'The system is busy. Please try again in a moment.',
            ]);
    }
}
