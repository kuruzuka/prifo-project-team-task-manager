<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
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
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Users can view comments on tasks they have access to.
     */
    public function view(User $user, Comment $comment): bool
    {
        $task = $this->getCommentTask($comment);

        return $task && $user->hasTaskAccess($task);
    }

    /**
     * Determine whether the user can create models.
     * Must have access to the task to comment on it.
     */
    public function create(User $user, ?Task $task = null): bool
    {
        if (! $task) {
            return true; // Will be validated in context
        }

        return $user->hasTaskAccess($task);
    }

    /**
     * Determine whether the user can update the model.
     * Users can only update their own comments.
     */
    public function update(User $user, Comment $comment): bool
    {
        $task = $this->getCommentTask($comment);

        // Managers can update comments in their projects
        if ($user->isManager() && $task && $task->project && $user->managesProject($task->project)) {
            return true;
        }

        // Users can only edit their own comments
        return $comment->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     * No one can delete comments.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return false; // No deletes allowed
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Comment $comment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Never allowed - audit protection.
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return false; // Never allow hard deletes
    }

    /**
     * Get the task for a comment, bypassing global scopes.
     */
    private function getCommentTask(Comment $comment): ?Task
    {
        if ($comment->relationLoaded('task') && $comment->task) {
            return $comment->task;
        }

        // Bypass TaskAccessScope to get the actual task
        return Task::withoutGlobalScopes()->find($comment->task_id);
    }
}
