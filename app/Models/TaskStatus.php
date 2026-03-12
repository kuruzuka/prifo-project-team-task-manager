<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskStatus extends Model
{
    /** @use HasFactory<\Database\Factories\TaskStatusFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $table = 'task_statuses';

    // A task status can be used by many tasks (one-to-many)
    public function tasks()
    {
        return $this->hasMany(Task::class, 'status_id');
    }
}
