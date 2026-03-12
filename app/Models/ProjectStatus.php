<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectStatus extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectStatusFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $table = 'project_statuses';

    // A project status can be used by many projects (one-to-many)
    public function projects()
    {
        return $this->hasMany(Project::class, 'status_id');
    }
}
