<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure the user has one of the required roles.
 *
 * Usage:
 *   Route::middleware('role:Admin')
 *   Route::middleware('role:Admin,Manager')
 */
class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Comma-separated role names
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized($request);
        }

        // Flatten roles in case comma-separated string was passed
        $requiredRoles = collect($roles)
            ->flatMap(fn ($role) => explode(',', $role))
            ->map(fn ($role) => trim($role))
            ->filter()
            ->toArray();

        if (empty($requiredRoles)) {
            return $next($request);
        }

        // Check if user has any of the required roles
        if (! $user->hasAnyRole($requiredRoles)) {
            return $this->unauthorized($request);
        }

        return $next($request);
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}
