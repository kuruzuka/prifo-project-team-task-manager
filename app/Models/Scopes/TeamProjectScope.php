<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope to filter projects based on team membership.
 *
 * Admins see all projects.
 * Other users only see projects belonging to their teams.
 *
 * Security: Prevents IDOR attacks and cross-team data leakage.
 */
class TeamProjectScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // No user = no results (safe default)
        if (! $user) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Admins see all projects
        if ($user->isAdmin()) {
            return;
        }

        // Get the user's team IDs
        $teamIds = $user->teams()->pluck('teams.id');

        // Filter to projects that belong to the user's teams
        $builder->whereExists(function ($query) use ($teamIds) {
            $query->select('team_projects.id')
                ->from('team_projects')
                ->whereColumn('team_projects.project_id', 'projects.id')
                ->whereIn('team_projects.team_id', $teamIds);
        });
    }
}
