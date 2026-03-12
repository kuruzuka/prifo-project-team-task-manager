<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display the projects listing page.
     *
     * Query explanation:
     * 1. withCount('tasks') - Adds tasks_count using a correlated subquery
     * 2. withAvg('tasks', 'progress') - Calculates average progress across all tasks
     * 3. team_members_count subquery - Counts distinct users across all teams assigned to the project:
     *    - Joins team_projects to find teams linked to each project
     *    - Joins user_teams to find users in those teams
     *    - Uses COUNT(DISTINCT) to avoid counting users in multiple teams twice
     * 4. with('status') - Eager loads the project status relationship
     * 5. with('manager') - Eager loads the project manager relationship
     *
     * This approach avoids N+1 queries by computing all aggregates in the initial query.
     *
     * Authorization: viewAny policy check + TeamProjectScope filters data by role:
     * - Admins see all projects
     * - Managers/Members see only projects in their teams
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = Project::query()
            ->select([
                'projects.id',
                'projects.name',
                'projects.description',
                'projects.status_id',
                'projects.manager_id',
                'projects.start_date',
                'projects.end_date',
            ])
            ->withCount('tasks')
            ->withAvg('tasks', 'progress')
            ->addSelect([
                'team_members_count' => DB::table('team_projects')
                    ->join('user_teams', 'team_projects.team_id', '=', 'user_teams.team_id')
                    ->whereColumn('team_projects.project_id', 'projects.id')
                    ->selectRaw('COUNT(DISTINCT user_teams.user_id)'),
            ])
            ->with([
                'status:id,name',
                'manager:id,first_name,last_name',
            ])
            ->orderBy('projects.created_at', 'desc')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status?->name,
                'progress' => (int) round($project->tasks_avg_progress ?? 0),
                'tasks_count' => $project->tasks_count,
                'team_members_count' => $project->team_members_count ?? 0,
                'manager' => $project->manager ? [
                    'id' => $project->manager->id,
                    'name' => $project->manager->first_name . ' ' . $project->manager->last_name,
                    'initials' => strtoupper(substr($project->manager->first_name, 0, 1) . substr($project->manager->last_name, 0, 1)),
                ] : null,
                'deadline' => $project->end_date?->format('F j, Y'),
                'start_date' => $project->start_date?->format('F j, Y'),
            ]);

        return Inertia::render('projects/Projects', [
            'projects' => $projects,
        ]);
    }

    /**
     * Display projects for a specific user.
     *
     * Authorization:
     * - Admins can view any user's projects
     * - Managers can view projects of users in their teams
     * - Users can view their own projects
     *
     * Returns projects where the user is either:
     * - The project manager
     * - A member of any team assigned to the project
     */
    public function forUser(User $user, Request $request): Response
    {
        $viewer = $request->user();

        // Authorization: Check if viewer can see this user's projects
        $canViewUserProjects = $viewer->isAdmin()
            || $viewer->id === $user->id
            || ($viewer->isManager() && $this->sharesTeam($viewer, $user));

        if (! $canViewUserProjects) {
            abort(403, 'You do not have permission to view this user\'s projects.');
        }

        $projects = Project::query()
            ->select([
                'projects.id',
                'projects.name',
                'projects.description',
                'projects.status_id',
                'projects.manager_id',
                'projects.start_date',
                'projects.end_date',
            ])
            ->where(function (Builder $query) use ($user) {
                // User is the project manager
                $query->where('projects.manager_id', $user->id)
                    // OR user is a member of a team assigned to the project
                    ->orWhereExists(function ($subquery) use ($user) {
                        $subquery->from('team_projects')
                            ->join('user_teams', 'team_projects.team_id', '=', 'user_teams.team_id')
                            ->whereColumn('team_projects.project_id', 'projects.id')
                            ->where('user_teams.user_id', $user->id);
                    });
            })
            ->withCount('tasks')
            ->withAvg('tasks', 'progress')
            ->addSelect([
                'team_members_count' => DB::table('team_projects')
                    ->join('user_teams', 'team_projects.team_id', '=', 'user_teams.team_id')
                    ->whereColumn('team_projects.project_id', 'projects.id')
                    ->selectRaw('COUNT(DISTINCT user_teams.user_id)'),
            ])
            ->with([
                'status:id,name',
                'manager:id,first_name,last_name',
            ])
            ->orderBy('projects.created_at', 'desc')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status?->name,
                'progress' => (int) round($project->tasks_avg_progress ?? 0),
                'tasks_count' => $project->tasks_count,
                'team_members_count' => $project->team_members_count ?? 0,
                'manager' => $project->manager ? [
                    'id' => $project->manager->id,
                    'name' => $project->manager->first_name . ' ' . $project->manager->last_name,
                    'initials' => strtoupper(substr($project->manager->first_name, 0, 1) . substr($project->manager->last_name, 0, 1)),
                ] : null,
                'deadline' => $project->end_date?->format('F j, Y'),
                'start_date' => $project->start_date?->format('F j, Y'),
            ]);

        return Inertia::render('projects/Projects', [
            'projects' => $projects,
            'user' => [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
            ],
        ]);
    }

    /**
     * Store a newly created project.
     *
     * Authorization is handled by ProjectStoreRequest.
     * Defaults:
     * - manager_id: authenticated user
     * - status_id: Planning (id=1)
     * - start_date: today
     */
    public function store(\App\Http\Requests\ProjectStoreRequest $request): \Illuminate\Http\RedirectResponse
    {
        $project = Project::create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'status_id' => 1, // Planning
            'manager_id' => $request->user()->id,
            'start_date' => now()->toDateString(),
            'end_date' => $request->validated('due_date'),
        ]);

        return redirect()->route('projects')->with('success', "Project \"{$project->name}\" created successfully.");
    }

    /**
     * Display the project details page with tasks.
     *
     * Authorization: Uses ProjectPolicy@view which checks team access.
     *
     * Query optimizations:
     * 1. Eager load manager with select constraints
     * 2. Eager load tasks with status relationship
     * 3. Eager load teams with members
     * 4. Eager load activity logs
     * 5. Select only needed columns
     */
    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $user = request()->user();
        $backUrl = $this->resolveProjectListUrl();

        // Load relationships with field constraints
        $project->load([
            'manager:id,first_name,last_name',
            'status:id,name',
            'teams' => fn ($query) => $query->withoutGlobalScopes()->select('teams.id', 'teams.name'),
            'teams.members' => fn ($query) => $query->select('users.id', 'users.first_name', 'users.last_name'),
            'activityLogs' => fn ($query) => $query
                ->with('actor:id,first_name,last_name')
                ->orderBy('created_at', 'desc')
                ->limit(50),
        ]);

        // Fetch task activity logs for all tasks in this project
        $taskIds = $project->tasks()->pluck('tasks.id');
        $taskActivityLogs = \App\Models\TaskActivityLog::query()
            ->whereIn('task_id', $taskIds)
            ->with([
                'actor:id,first_name,last_name',
                'task:id,title',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Load tasks separately for better control with pagination
        $tasks = $project->tasks()
            ->select([
                'tasks.id',
                'tasks.project_id', // Required for permission checks in hasTaskAccess()
                'tasks.title',
                'tasks.description',
                'tasks.priority',
                'tasks.due_date',
                'tasks.status_id',
                'tasks.created_by',
            ])
            ->withCount('assignedUsers')
            ->with([
                'status:id,name',
                'creator:id,first_name,last_name',
                'assignedUsers' => fn ($query) => $query
                    ->select('users.id', 'users.first_name', 'users.last_name'),
            ])
            ->orderBy('tasks.due_date', 'asc')
            ->orderBy('tasks.created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status?->name,
                'status_id' => $task->status_id,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->format('F j, Y'),
                'due_date_raw' => $task->due_date?->format('Y-m-d'),
                'assignee' => $this->formatTaskAssignee($task),
                'created_by' => $task->creator
                    ? $task->creator->first_name . ' ' . $task->creator->last_name
                    : null,
                'can' => [
                    'view' => $user->can('view', $task),
                    'delete' => $user->can('delete', $task),
                    'edit' => $user->can('edit', $task),
                ],
            ]);

        // Check if user can create tasks in this project
        $canCreateTask = Gate::allows('create', [Task::class, $project]);

        // Get all teams for the team selector (admin sees all, managers see their teams)
        $availableTeams = $this->getAvailableTeams($user);

        // Get task statuses for the edit task dialog
        $taskStatuses = TaskStatus::query()
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status?->name,
                'status_id' => $project->status_id,
                'manager' => $project->manager ? [
                    'id' => $project->manager->id,
                    'name' => $project->manager->first_name . ' ' . $project->manager->last_name,
                ] : null,
                'start_date' => $project->start_date?->format('F j, Y'),
                'start_date_raw' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('F j, Y'),
                'end_date_raw' => $project->end_date?->format('Y-m-d'),
                'teams' => $project->teams->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members' => $team->members->map(fn ($member) => [
                        'id' => $member->id,
                        'name' => $member->first_name . ' ' . $member->last_name,
                        'initials' => strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)),
                    ]),
                ]),
            ],
            'tasks' => $tasks,
            'taskStatuses' => $taskStatuses,
            'activities' => $this->buildCombinedActivityFeed($project->activityLogs, $taskActivityLogs),
            'availableTeams' => $availableTeams,
            'backUrl' => $backUrl,
            'canCreateTask' => $canCreateTask,
            'canUpdateTeams' => Gate::allows('updateTeams', $project),
            'canUpdate' => Gate::allows('update', $project),
            'statuses' => ProjectStatus::query()
                ->select('id', 'name')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * Update the project details.
     *
     * Only Admins and the project's manager can update project details.
     * Logs changes in project activity.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status_id' => ['required', 'integer', 'exists:project_statuses,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ], [
            'name.required' => 'Project name is required.',
            'name.max' => 'Project name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'status_id.required' => 'Status is required.',
            'status_id.exists' => 'Invalid status selected.',
            'start_date.required' => 'Start date is required.',
            'start_date.after_or_equal' => 'Start date must be today or later.',
            'end_date.after' => 'Due date must be after the start date.',
        ]);

        // Track changes for activity logging
        $changes = [];
        $fieldsToTrack = ['name', 'description', 'start_date', 'end_date'];

        foreach ($fieldsToTrack as $field) {
            $oldValue = $project->$field;
            $newValue = $validated[$field] ?? null;

            // Normalize date values for comparison
            if (in_array($field, ['start_date', 'end_date'])) {
                $oldValue = $oldValue?->format('Y-m-d');
            }

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Check for status change
        if ($project->status_id !== $validated['status_id']) {
            $oldStatus = $project->status?->name ?? 'None';
            $newStatus = ProjectStatus::find($validated['status_id'])?->name ?? 'None';
            $changes['status'] = [
                'old' => $oldStatus,
                'new' => $newStatus,
            ];
        }

        $project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status_id' => $validated['status_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
        ]);

        // Log specific field changes
        if (! empty($changes)) {
            $project->logActivity('project_updated', [
                'changes' => $changes,
            ]);
        }

        return back()->with('success', 'Project updated successfully.');
    }

    /**
     * Update the teams assigned to the project.
     *
     * Logs the change in project activity with old and new team names
     * for historical reference even if teams are renamed or deleted.
     */
    public function updateTeams(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('updateTeams', $project);

        $validated = $request->validate([
            'team_ids' => ['required', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        // Get old teams for logging
        $oldTeams = $project->teams()->pluck('name', 'teams.id')->toArray();
        $oldTeamIds = array_keys($oldTeams);
        $newTeamIds = $validated['team_ids'];

        // Get new team names for logging
        $newTeams = Team::withoutGlobalScopes()
            ->whereIn('id', $newTeamIds)
            ->pluck('name', 'id')
            ->toArray();

        // Sync teams
        $project->teams()->sync($newTeamIds);

        // Determine changes for activity log
        $addedIds = array_diff($newTeamIds, $oldTeamIds);
        $removedIds = array_diff($oldTeamIds, $newTeamIds);

        // Log activity with team names (preserved for history)
        if (! empty($addedIds) || ! empty($removedIds)) {
            $metadata = [];

            if (! empty($addedIds)) {
                $metadata['teams_added'] = array_values(array_intersect_key($newTeams, array_flip($addedIds)));
            }

            if (! empty($removedIds)) {
                $metadata['teams_removed'] = array_values(array_intersect_key($oldTeams, array_flip($removedIds)));
            }

            $metadata['old_teams'] = array_values($oldTeams);
            $metadata['new_teams'] = array_values($newTeams);

            $project->logActivity('teams_updated', $metadata);
        }

        return back()->with('success', 'Project teams updated successfully.');
    }

    /**
     * Get available teams for the team selector.
     * Admins see all teams, managers see their teams.
     */
    private function getAvailableTeams(User $user): array
    {
        if ($user->isAdmin()) {
            return Team::withoutGlobalScopes()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn ($team) => ['id' => $team->id, 'name' => $team->name])
                ->toArray();
        }

        return $user->teams()
            ->select('teams.id', 'teams.name')
            ->orderBy('teams.name')
            ->get()
            ->map(fn ($team) => ['id' => $team->id, 'name' => $team->name])
            ->toArray();
    }

    /**
     * Resolve the project list URL from the referer.
     * Supports user-scoped project lists (e.g., /users/4/projects).
     *
     * Uses session to persist the origin across navigation (e.g., when returning from a task).
     */
    private function resolveProjectListUrl(): string
    {
        $referer = request()->headers->get('referer');
        $sessionKey = 'project_list_origin';

        // If referer contains user-scoped projects, store and return it
        if ($referer && preg_match('#/users/\d+/projects#', $referer)) {
            $path = parse_url($referer, PHP_URL_PATH);
            session([$sessionKey => $path]);

            return $path;
        }

        // If referer is from main projects list, clear any stored user-scoped origin
        if ($referer && preg_match('#/projects$#', parse_url($referer, PHP_URL_PATH) ?? '')) {
            session()->forget($sessionKey);

            return route('projects', absolute: false);
        }

        // Check if we have a stored user-scoped origin (coming back from task, etc.)
        $storedOrigin = session($sessionKey);
        if ($storedOrigin) {
            return $storedOrigin;
        }

        return route('projects', absolute: false);
    }

    /**
     * Format task assignee display.
     */
    private function formatTaskAssignee($task): ?string
    {
        $assignees = $task->assignedUsers;
        $totalCount = $task->assigned_users_count;

        if ($assignees->isEmpty()) {
            return null;
        }

        $firstAssignee = $assignees->first();
        $name = $firstAssignee->first_name . ' ' . $firstAssignee->last_name;
        $othersCount = $totalCount - 1;

        return $othersCount > 0
            ? "{$name} + {$othersCount} " . ($othersCount === 1 ? 'other' : 'others')
            : $name;
    }

    /**
     * Check if two users share at least one team.
     */
    private function sharesTeam(User $viewer, User $target): bool
    {
        $viewerTeamIds = $viewer->teams()->pluck('teams.id');

        return $target->teams()->whereIn('teams.id', $viewerTeamIds)->exists();
    }

    /**
     * Build combined activity feed from project and task activities.
     *
     * Merges project-level activities with task-level activities,
     * sorted by timestamp (latest first), limited to 50 entries.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\ActivityLog>  $projectActivities
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskActivityLog>  $taskActivities
     * @return array<int, array<string, mixed>>
     */
    private function buildCombinedActivityFeed($projectActivities, $taskActivities): array
    {
        // Map project activities
        $projectItems = $projectActivities->map(fn ($log) => [
            'id' => 'project-' . $log->id,
            'source' => 'project',
            'type' => $log->activity_type,
            'metadata' => $log->metadata,
            'task_name' => null,
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->first_name . ' ' . $log->actor->last_name,
            ] : null,
            'created_at' => $log->created_at->diffForHumans(),
            'timestamp' => $log->created_at->timestamp,
        ]);

        // Map task activities
        $taskItems = $taskActivities->map(fn ($log) => [
            'id' => 'task-' . $log->id,
            'source' => 'task',
            'type' => $log->activity_type,
            'metadata' => $log->metadata,
            'task_name' => $log->task?->title,
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->first_name . ' ' . $log->actor->last_name,
            ] : null,
            'created_at' => $log->created_at->diffForHumans(),
            'timestamp' => $log->created_at->timestamp,
        ]);

        // Merge and sort by timestamp (latest first)
        return $projectItems
            ->merge($taskItems)
            ->sortByDesc('timestamp')
            ->take(50)
            ->values()
            ->toArray();
    }
}
