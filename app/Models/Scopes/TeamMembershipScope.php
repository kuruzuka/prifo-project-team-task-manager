<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope to filter teams based on user membership.
 *
 * Admins see all teams.
 * Other users only see teams they belong to.
 *
 * Security: Prevents unauthorized access to team data via direct queries.
 */
class TeamMembershipScope implements Scope
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

        // Admins see all teams
        if ($user->isAdmin()) {
            return;
        }

        // Filter to teams the user belongs to
        $builder->whereIn('teams.id', function ($query) use ($user) {
            $query->select('team_id')
                ->from('user_teams')
                ->where('user_id', $user->id);
        });
    }
}
