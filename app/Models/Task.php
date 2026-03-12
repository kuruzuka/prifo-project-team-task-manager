<?php

namespace App\Models;

use App\Concerns\HasActivityLogs;
use App\Concerns\HasOptimisticLocking;
use App\Concerns\PreventsHardDeletes;
use App\Models\Scopes\TaskAccessScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([TaskAccessScope::class])]
class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;
    use HasActivityLogs;
    use HasOptimisticLocking;
    use SoftDeletes;
    use PreventsHardDeletes;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'priority',
        'progress',
        'status_id',
        'due_date',
        'created_by',
        'version',
    ];

    /**
     * Attributes that should be hidden from serialization.
     * Version is managed internally for optimistic locking.
     */
    protected $hidden = [
        'version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'progress' => 'integer',
        ];
    }

    // A task belongs to one project (many-to-one)
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // A task has one creator (many-to-one)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // A task has one status (many-to-one)
    public function status()
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    // A task can be assigned to many users (many-to-many)
    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'task_assignments')
            ->withPivot('assigned_by', 'assigned_date')
            ->withTimestamps();
    }

    // A task can have many comments (one-to-many)
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    // A task can have many task activity logs (one-to-many)
    public function taskActivityLogs()
    {
        return $this->hasMany(TaskActivityLog::class);
    }

    /**
     * Log a specific task activity with metadata.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function logTaskActivity(string $activityType, ?array $metadata = null, ?int $actorId = null): TaskActivityLog
    {
        return $this->taskActivityLogs()->create([
            'activity_type' => $activityType,
            'metadata' => $metadata,
            'actor_id' => $actorId ?? auth()->id(),
        ]);
    }
}
