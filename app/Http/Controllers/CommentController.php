<?php

namespace App\Http\Controllers;

use App\Concerns\UsesConcurrencyControl;
use App\Http\Requests\CommentStoreRequest;
use App\Http\Requests\CommentUpdateRequest;
use App\Models\Comment;
use App\Models\Task;
use App\Models\TransactionLog;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    use UsesConcurrencyControl;

    /**
     * Store a new comment on a task.
     * Also logs the activity in task_activity_log.
     * Uses transaction to ensure comment and activity log are created atomically.
     */
    public function store(CommentStoreRequest $request, Task $task): RedirectResponse
    {
        return $this->withTransaction(
            operationName: 'Add Comment',
            entity: $task,
            callback: function () use ($request, $task) {
                $comment = $task->comments()->create([
                    'user_id' => $request->user()->id,
                    'comment_text' => $request->validated('comment_text'),
                ]);

                // Log the comment activity
                $task->logTaskActivity('comment_added', [
                    'comment_id' => $comment->id,
                    'comment_preview' => substr($comment->comment_text, 0, 100),
                ]);

                return back()->with('success', 'Comment added successfully.');
            },
            operationType: TransactionLog::TYPE_CREATE
        );
    }

    /**
     * Update an existing comment.
     * Only the comment author or admin/manager can update.
     * Uses transaction to ensure comment update and activity log are atomic.
     */
    public function update(CommentUpdateRequest $request, Comment $comment): RedirectResponse
    {
        return $this->withTransaction(
            operationName: 'Edit Comment',
            entity: $comment,
            callback: function () use ($request, $comment) {
                $oldText = $comment->comment_text;

                $comment->update([
                    'comment_text' => $request->validated('comment_text'),
                ]);

                // Log the edit activity
                $comment->task->logTaskActivity('comment_edited', [
                    'comment_id' => $comment->id,
                    'old_preview' => substr($oldText, 0, 100),
                    'new_preview' => substr($comment->comment_text, 0, 100),
                ]);

                return back()->with('success', 'Comment updated successfully.');
            },
            operationType: TransactionLog::TYPE_UPDATE
        );
    }
}
