<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'job_title_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // A user can have many roles (many-to-many)
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withTimestamps();
    }

    // A user can belong to many teams (many-to-many)
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'user_teams')
            ->withTimestamps();
    }

    // A user can be assigned to many tasks (many-to-many)
    public function assignedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_assignments')
            ->withPivot('assigned_by', 'assigned_date')
            ->withTimestamps();
    }

    // A user can assign tasks to others (one-to-many on pivot)
    public function tasksAssignedByMe()
    {
        return $this->hasMany(TaskAssignment::class, 'assigned_by');
    }

    // A user can write many comments (one-to-many)
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    // A user can perform many task activities (one-to-many)
    public function taskActivities()
    {
        return $this->hasMany(TaskActivityLog::class, 'actor_id');
    }

    // A user can manage many projects (one-to-many)
    public function managedProjects()
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    // A user has one job title (many-to-one)
    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    // Authorization helpers

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles->whereIn('name', $roles)->isNotEmpty();
    }

    /**
     * Check if user is an Admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    /**
     * Check if user is a Manager.
     */
    public function isManager(): bool
    {
        return $this->hasRole('Manager');
    }

    /**
     * Check if user is a Member.
     */
    public function isMember(): bool
    {
        return $this->hasRole('Member');
    }

    /**
     * Check if user belongs to a specific team.
     * Uses database query, bypassing global scopes.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->withoutGlobalScopes()->where('teams.id', $team->id)->exists();
    }

    /**
     * Check if user manages a specific project.
     */
    public function managesProject(Project $project): bool
    {
        return $project->manager_id === $this->id;
    }

    /**
     * Check if user is assigned to a specific task.
     * Uses database query, bypassing global scopes.
     */
    public function isAssignedToTask(Task $task): bool
    {
        return $this->assignedTasks()->withoutGlobalScopes()->where('tasks.id', $task->id)->exists();
    }

    /**
     * Check if user has access to a project via team membership.
     * Uses database queries, bypassing global scopes for authorization checks.
     */
    public function hasProjectAccess(Project $project): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Check if user's teams intersect with project's teams
        $userTeamIds = $this->teams()->withoutGlobalScopes()->pluck('teams.id');
        $projectTeamIds = $project->teams()->withoutGlobalScopes()->pluck('teams.id');

        return $userTeamIds->intersect($projectTeamIds)->isNotEmpty();
    }

    /**
     * Check if user has access to a task.
     * Users can access tasks if:
     * - They are admin
     * - They created the task
     * - They are assigned to the task
     * - They have team access to the task's project (for collaboration)
     */
    public function hasTaskAccess(Task $task): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Task creator always has access
        if ($task->created_by === $this->id) {
            return true;
        }

        // Any user assigned to the task has access
        if ($this->isAssignedToTask($task)) {
            return true;
        }

        // Load project relationship if not loaded (bypass scope)
        if (! $task->relationLoaded('project')) {
            $task->setRelation('project', Project::withoutGlobalScopes()->find($task->project_id));
        }

        $project = $task->project;

        // No project means no access (unless assigned or creator, checked above)
        if (! $project) {
            return false;
        }

        // Managers can access all tasks in projects they manage or have team access to
        if ($this->isManager()) {
            return $this->managesProject($project) || $this->hasProjectAccess($project);
        }

        // Members can view tasks in projects their team has access to (for collaboration)
        if ($this->isMember()) {
            return $this->hasProjectAccess($project);
        }

        return false;
    }

    /**
     * Check if user shares at least one team with another user.
     */
    public function sharesTeamWith(User $other): bool
    {
        if ($this->id === $other->id) {
            return true;
        }

        $myTeamIds = $this->teams()->withoutGlobalScopes()->pluck('teams.id');
        $theirTeamIds = $other->teams()->withoutGlobalScopes()->pluck('teams.id');

        return $myTeamIds->intersect($theirTeamIds)->isNotEmpty();
    }

    /**
     * Get all team IDs the user belongs to.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getTeamIds(): \Illuminate\Support\Collection
    {
        return $this->teams->pluck('id');
    }

    /**
     * Get all project IDs the user can access via team membership.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getAccessibleProjectIds(): \Illuminate\Support\Collection
    {
        return $this->teams()
            ->with('projects')
            ->get()
            ->flatMap(fn (Team $team) => $team->projects->pluck('id'))
            ->unique();
    }

    /**
     * Get user's permission summary for frontend sharing.
     *
     * @return array<string, mixed>
     */
    public function getPermissions(): array
    {
        return [
            'isAdmin' => $this->isAdmin(),
            'isManager' => $this->isManager(),
            'isMember' => $this->isMember(),
            'roles' => $this->roles->pluck('name')->toArray(),
            'teamIds' => $this->getTeamIds()->toArray(),
            'managedProjectIds' => $this->managedProjects->pluck('id')->toArray(),
        ];
    }
}
