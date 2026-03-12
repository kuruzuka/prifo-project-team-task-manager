<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure the user has access to the team in the route.
 *
 * This middleware prevents IDOR attacks by verifying team membership
 * before allowing access to team-scoped resources.
 *
 * Usage:
 *   Route::middleware('team.access')  // Uses 'team' route parameter
 *   Route::middleware('team.access:team_id')  // Uses custom parameter name
 */
class EnsureTeamAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $parameterName  The route parameter containing the team ID
     */
    public function handle(Request $request, Closure $next, string $parameterName = 'team'): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized($request);
        }

        // Admins bypass team checks
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Get team from route parameter
        $team = $request->route($parameterName);

        if (! $team) {
            // No team parameter - allow request to continue
            return $next($request);
        }

        // Resolve team if it's an ID
        if (! $team instanceof Team) {
            $team = Team::find($team);
        }

        if (! $team) {
            abort(404, 'Team not found.');
        }

        // Verify user belongs to the team
        if (! $user->belongsToTeam($team)) {
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
                'message' => 'You do not have access to this team.',
            ], 403);
        }

        abort(403, 'You do not have access to this team.');
    }
}
