<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'comment_text',
    ];

    // A comment belongs to one task (many-to-one)
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    // A comment is written by one user (many-to-one)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
