<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    /**
     * Perform pre-authorization checks.
     * Admins bypass all checks except delete.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() && ! in_array($ability, ['delete', 'forceDelete'])) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     * Route-level middleware enforces admin-only access to global list.
     * Data is filtered by global scope based on role.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filtered by global scope
    }

    /**
     * Determine whether the user can view the model.
     * Users can only view teams they belong to.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     * Only Admins can create teams.
     */
    public function create(User $user): bool
    {
        return false; // Only admins (handled by before)
    }

    /**
     * Determine whether the user can update the model.
     * Only Admins can update teams.
     */
    public function update(User $user, Team $team): bool
    {
        return false; // Only admins (handled by before)
    }

    /**
     * Determine whether the user can delete the model.
     * No one can delete teams.
     */
    public function delete(User $user, Team $team): bool
    {
        return false; // No deletes allowed
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Team $team): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Never allowed - audit protection.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        return false; // Never allow hard deletes
    }

    /**
     * Determine whether the user can add members to the team.
     * Admins: can add to any team.
     * Managers: can add to teams they belong to.
     */
    public function addMember(User $user, Team $team): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Managers can add members to their own teams
        return $user->isManager() && $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can remove members from the team.
     * Only Admins can remove members.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $user->isAdmin();
    }
}
