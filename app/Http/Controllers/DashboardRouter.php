<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardRouter extends Controller
{
    /**
     * Display the dashboard with user-specific statistics.
     *
     * Projects and team counts are filtered by global scopes based on user role.
     * Task counts show only tasks assigned to the current user (My Tasks).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Projects count - only projects where user is involved (manager or team member)
        $totalProjects = Project::query()
            ->where(function ($query) use ($user) {
                $query->where('projects.manager_id', $user->id)
                    ->orWhereExists(function ($subquery) use ($user) {
                        $subquery->from('team_projects')
                            ->join('user_teams', 'team_projects.team_id', '=', 'user_teams.team_id')
                            ->whereColumn('team_projects.project_id', 'projects.id')
                            ->where('user_teams.user_id', $user->id);
                    });
            })
            ->count();

        // Task counts - only tasks assigned to current user (My Tasks)
        // Active excludes Done and Blocked statuses
        $activeTasks = Task::whereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('status', fn ($q) => $q->whereNotIn('name', ['Done', 'Blocked']))
            ->count();
        $tasksDone = Task::whereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('status', fn ($q) => $q->where('name', 'Done'))
            ->count();

        // Team members count - unique members across user's teams
        $teamMemberCount = $user->isAdmin()
            ? Team::withoutGlobalScopes()
                ->withCount('members')
                ->get()
                ->flatMap(fn ($team) => $team->members->pluck('id'))
                ->unique()
                ->count()
            : $user->teams()
                ->withoutGlobalScopes()
                ->with('members')
                ->get()
                ->flatMap(fn ($team) => $team->members->pluck('id'))
                ->unique()
                ->count();

        // Recent tasks assigned to current user (max 4)
        $recentTasks = Task::query()
            ->select(['id', 'title', 'priority', 'project_id', 'status_id', 'created_at'])
            ->whereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id))
            ->with(['project:id,name', 'status:id,name'])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'project' => $task->project?->name,
                'priority' => $task->priority,
                'status' => $task->status?->name,
            ]);

        // Project progress (max 4) - only projects with at least one task
        // Uses average of task progress fields (same as Projects page)
        $projectProgress = Project::query()
            ->select(['id', 'name'])
            ->whereHas('tasks')
            ->withAvg('tasks', 'progress')
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'progress' => (int) round($project->tasks_avg_progress ?? 0),
            ]);

        return Inertia::render('dashboard/Dashboard', [
            'stats' => [
                'totalProjects' => $totalProjects,
                'activeTasks' => $activeTasks,
                'tasksDone' => $tasksDone,
                'teamMembers' => $teamMemberCount,
            ],
            'recentTasks' => $recentTasks,
            'projectProgress' => $projectProgress,
        ]);
    }
}
