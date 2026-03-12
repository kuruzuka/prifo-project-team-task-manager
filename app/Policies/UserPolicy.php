<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Perform pre-authorization checks.
     * Admins bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     * All authenticated users can see list of users (filtered by scope).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Users can view other users within their teams (for commenting).
     */
    public function view(User $user, User $targetUser): bool
    {
        return $user->sharesTeamWith($targetUser);
    }

    /**
     * Determine whether the user can create models.
     * Only Admins can create users.
     */
    public function create(User $user): bool
    {
        return false; // Only admins (handled by before)
    }

    /**
     * Determine whether the user can update the model.
     * Users can only update their own profile.
     */
    public function update(User $user, User $targetUser): bool
    {
        return $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can delete the model.
     * Only Admins can delete users.
     */
    public function delete(User $user, User $targetUser): bool
    {
        return false; // Only admins (handled by before)
    }

    /**
     * Determine whether the user can restore the model.
     * Only Admins can restore users.
     */
    public function restore(User $user, User $targetUser): bool
    {
        return false; // Only admins (handled by before)
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Never allowed - audit protection.
     */
    public function forceDelete(User $user, User $targetUser): bool
    {
        return false; // Never allow hard deletes
    }
}
