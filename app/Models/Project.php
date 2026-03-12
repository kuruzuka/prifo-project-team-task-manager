<?php

namespace App\Models;

use App\Concerns\HasActivityLogs;
use App\Concerns\HasOptimisticLocking;
use App\Concerns\PreventsHardDeletes;
use App\Models\Scopes\TeamProjectScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([TeamProjectScope::class])]
class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;
    use SoftDeletes;
    use HasActivityLogs;
    use HasOptimisticLocking;
    use PreventsHardDeletes;

    protected $fillable = [
        'name',
        'description',
        'status_id',
        'manager_id',
        'start_date',
        'end_date',
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
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    // A project has one manager (many-to-one)
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // A project has one status (many-to-one)
    public function status()
    {
        return $this->belongsTo(ProjectStatus::class, 'status_id');
    }

    // A project can have many tasks (one-to-many)
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // A project can be assigned to many teams (many-to-many)
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_projects')
            ->withTimestamps();
    }
}
