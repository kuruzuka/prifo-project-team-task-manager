<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope to filter tasks based on user access.
 *
 * Admins see all tasks.
 * Managers see all tasks in projects they manage or their team projects.
 * Members see tasks in projects their team has access to (for collaboration).
 * All users can see tasks they created or are assigned to.
 *
 * Security: Prevents unauthorized access to task data.
 */
class TaskAccessScope implements Scope
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

        // Admins see all tasks
        if ($user->isAdmin()) {
            return;
        }

        // Get user's team IDs for project access check
        $teamIds = $user->teams()->pluck('teams.id');

        // User can see tasks if:
        // 1. They created the task (any task, with or without project)
        // 2. They are assigned to the task (any task, with or without project)
        // 3. The task belongs to a project their team has access to
        $builder->where(function ($query) use ($user, $teamIds) {
            // 1. User created the task
            $query->where('tasks.created_by', $user->id);

            // 2. User is assigned to the task
            $query->orWhereExists(function ($assignedQuery) use ($user) {
                $assignedQuery->select('task_assignments.id')
                    ->from('task_assignments')
                    ->whereColumn('task_assignments.task_id', 'tasks.id')
                    ->where('task_assignments.user_id', $user->id);
            });

            // 3. Task is in a project user's team has access to
            $query->orWhereExists(function ($projectQuery) use ($teamIds) {
                $projectQuery->select('team_projects.id')
                    ->from('team_projects')
                    ->join('projects', 'projects.id', '=', 'team_projects.project_id')
                    ->whereColumn('projects.id', 'tasks.project_id')
                    ->whereIn('team_projects.team_id', $teamIds);
            });
        });
    }
}
