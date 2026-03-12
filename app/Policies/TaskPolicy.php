<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
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
     * Managers: can view all tasks in their projects.
     * Members: can only view tasks assigned to them.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->hasTaskAccess($task);
    }

    /**
     * Determine whether the user can create models.
     * Must specify a project context.
     * Only Admins and Managers of the project can create tasks.
     */
    public function create(User $user, ?Project $project = null): bool
    {
        if (! $project) {
            // Without project context, only managers with at least one managed project
            return $user->isManager() && $user->managedProjects->isNotEmpty();
        }

        // Managers can create tasks in projects they manage
        return $user->isManager() && $user->managesProject($project);
    }

    /**
     * Determine whether the user can fully edit the model (title, description, etc.).
     * Admins: bypass (handled in before())
     * Managers: can edit tasks in projects they manage
     * Creators: can edit tasks they created
     */
    public function edit(User $user, Task $task): bool
    {
        // Creator can edit their own tasks
        if ($task->created_by === $user->id) {
            return true;
        }

        $project = $this->getTaskProject($task);

        // Managers can edit tasks in projects they manage
        return $user->isManager() && $project && $user->managesProject($project);
    }

    /**
     * Determine whether the user can update the model.
     * Admins: can update any task.
     * Creators: can update tasks they created.
     * Managers: can update tasks in their projects.
     * Members/Assigned users: can only update progress/status on assigned tasks.
     */
    public function update(User $user, Task $task): bool
    {
        // Task creator can always update their task
        if ($task->created_by === $user->id) {
            return true;
        }

        // Any assigned user can update task progress (limited fields handled in controller)
        if ($user->isAssignedToTask($task)) {
            return true;
        }

        $project = $this->getTaskProject($task);

        // Managers can update tasks in projects they manage
        if ($user->isManager() && $project && $user->managesProject($project)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the member can update task progress.
     * Task creators and assigned members can update progress/status.
     */
    public function updateProgress(User $user, Task $task): bool
    {
        // Task creator can always update progress
        if ($task->created_by === $user->id) {
            return true;
        }

        // Any assigned user can update progress
        if ($user->isAssignedToTask($task)) {
            return true;
        }

        $project = $this->getTaskProject($task);

        // Admins and Managers can always update tasks in their projects
        if ($user->isAdmin() || ($user->isManager() && $project && $user->managesProject($project))) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Admins and project Managers can soft delete tasks.
     */
    public function delete(User $user, Task $task): bool
    {
        // Admins can delete any task (handled in before())
        if ($user->isAdmin()) {
            return true;
        }

        $project = $this->getTaskProject($task);

        // Managers can delete tasks in projects they manage
        return $user->isManager() && $project && $user->managesProject($project);
    }

    /**
     * Determine whether the user can restore the model.
     * Only Admins can restore.
     */
    public function restore(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Never allowed - audit protection.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return false; // Never allow hard deletes
    }

    /**
     * Determine whether the user can assign users to the task.
     * Admins, project Managers, and task creators can assign.
     */
    public function assign(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Task creator can assign users to their task
        if ($task->created_by === $user->id) {
            return true;
        }

        $project = $this->getTaskProject($task);

        return $user->isManager() && $project && $user->managesProject($project);
    }

    /**
     * Get the task's project, bypassing global scopes.
     */
    private function getTaskProject(Task $task): ?Project
    {
        if ($task->relationLoaded('project') && $task->project !== null) {
            return $task->project;
        }

        $project = Project::withoutGlobalScopes()->find($task->project_id);
        $task->setRelation('project', $project);

        return $project;
    }
}
