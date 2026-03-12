<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Perform pre-authorization checks.
     * Admins bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Admins can do everything except delete
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
     * User must have team access to the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->hasProjectAccess($project);
    }

    /**
     * Determine whether the user can create models.
     * Only Admins can create projects.
     */
    public function create(User $user): bool
    {
        return false; // Only admins (handled by before)
    }

    /**
     * Determine whether the user can update the model.
     * Admins can update any project.
     * Managers can only update projects they manage.
     */
    public function update(User $user, Project $project): bool
    {
        // Managers can only update projects they manage
        if ($user->isManager()) {
            return $user->managesProject($project);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * No one can delete projects (soft deletes enforced elsewhere).
     */
    public function delete(User $user, Project $project): bool
    {
        return false; // No hard deletes allowed
    }

    /**
     * Determine whether the user can restore the model.
     * Only Admins can restore.
     */
    public function restore(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Never allowed - audit protection.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return false; // Never allow hard deletes
    }

    /**
     * Determine whether the user can assign a manager to the project.
     * Only Admins can assign managers.
     */
    public function assignManager(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage tasks within the project.
     * Admins and the project's manager can manage tasks.
     */
    public function manageTasks(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->managesProject($project);
    }

    /**
     * Determine whether the user can update teams assigned to the project.
     * Admins and the project's manager can update team assignments.
     */
    public function updateTeams(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->managesProject($project);
    }
}
