<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Activity Log Policy.
 *
 * Activity logs are immutable audit records.
 * Only Admins can view logs. No one can modify or delete logs.
 */
class ActivityLogPolicy
{
    /**
     * Determine whether the user can view any models.
     * Only Admins can view activity logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     * Only Admins can view individual logs.
     */
    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     * Logs are created by the system, not users.
     */
    public function create(User $user): bool
    {
        return false; // System-created only
    }

    /**
     * Determine whether the user can update the model.
     * Logs are immutable.
     */
    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false; // Immutable
    }

    /**
     * Determine whether the user can delete the model.
     * Logs cannot be deleted.
     */
    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false; // Never delete logs
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ActivityLog $activityLog): bool
    {
        return false; // Logs don't use soft deletes
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Never allowed - audit protection.
     */
    public function forceDelete(User $user, ActivityLog $activityLog): bool
    {
        return false; // Never allow hard deletes
    }
}
