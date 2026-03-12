<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskAssignment extends Model
{
    //
    protected $fillable = [
        'task_id',
        'user_id',
        'assigned_by',
        'assigned_date',
    ];

    protected $table = 'task_assignments';

    // An assignment belongs to one task
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    // An assignment belongs to one user (the assignee)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // An assignment was created by one user (the assigner)
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
