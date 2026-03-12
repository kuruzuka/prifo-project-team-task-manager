<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskActivityLog extends Model
{
    //
    protected $fillable = [
        'task_id',
        'activity_type',
        'metadata',
        'actor_id',
        'created_at',
    ];

    protected $table = 'task_activity_log';

    protected $casts = [
        'metadata' => 'array',
    ];

    // An activity log belongs to one task (many-to-one)
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    // An activity log is performed by one user (many-to-one)
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
