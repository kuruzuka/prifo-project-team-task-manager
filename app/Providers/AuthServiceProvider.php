<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Policies\ActivityLogPolicy;
use App\Policies\CommentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TeamPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Project::class => ProjectPolicy::class,
        Task::class => TaskPolicy::class,
        Team::class => TeamPolicy::class,
        Comment::class => CommentPolicy::class,
        ActivityLog::class => ActivityLogPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerGates();
    }

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Register application gates.
     *
     * Gates are used for actions not tied to a specific model instance.
     */
    protected function registerGates(): void
    {
        // Role-based gates for global actions
        Gate::define('admin', fn ($user) => $user->isAdmin());

        Gate::define('manage-teams', fn ($user) => $user->isAdmin());

        Gate::define('create-projects', fn ($user) => $user->isAdmin());

        Gate::define('assign-roles', fn ($user) => $user->isAdmin());

        Gate::define('view-activity-logs', fn ($user) => $user->isAdmin());

        // Manager-level gates
        Gate::define('manage-project-tasks', function ($user, Project $project) {
            return $user->isAdmin() || $user->managesProject($project);
        });

        Gate::define('assign-project-members', function ($user, Project $project) {
            return $user->isAdmin() || $user->managesProject($project);
        });

        // Team-level gates
        Gate::define('add-team-member', function ($user, Team $team) {
            if ($user->isAdmin()) {
                return true;
            }

            return $user->isManager() && $user->belongsToTeam($team);
        });
    }
}
