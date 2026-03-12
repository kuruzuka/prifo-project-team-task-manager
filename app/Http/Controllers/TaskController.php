<?php

namespace App\Http\Controllers;

use App\Concerns\UsesConcurrencyControl;
use App\Http\Requests\TaskStoreRequest;
use App\Models\Project;
use App\Models\Scopes\TaskAccessScope;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TransactionLog;
use App\Models\User;
use App\Services\StoredProcedureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    use UsesConcurrencyControl;
    /**
     * Display the tasks listing page with search, filtering, and pagination.
     *
     * Authorization: viewAny policy check + TaskAccessScope filters data by role:
     * - Admins see all tasks
     * - Managers see tasks in their team projects
     * - Members see only tasks assigned to them
     *
     * Query optimizations:
     * 1. Select only required columns to reduce memory usage
     * 2. Eager load relationships with field constraints (project:id,name, status:id,name)
     * 3. Use conditional when() clauses for optional filters
     * 4. Limit assignedUsers to 1 for display purposes (uses subquery limit in Laravel 12)
     * 5. Index-friendly filtering on status_id foreign key
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $currentUserId = $request->user()->id;

        $tasks = Task::query()
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.priority',
                'tasks.due_date',
                'tasks.project_id',
                'tasks.status_id',
                'tasks.created_by',
            ])
            ->withCount('assignedUsers')
            ->with([
                'project:id,name',
                'status:id,name',
                'creator:id,first_name,last_name',
                'assignedUsers' => fn ($query) => $query
                    ->select('users.id', 'users.first_name', 'users.last_name'),
            ])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function (Builder $q) use ($search) {
                    $q->where('tasks.title', 'like', $search)
                        ->orWhere('tasks.priority', 'like', $search)
                        ->orWhereHas('project', fn ($p) => $p->where('name', 'like', $search))
                        ->orWhereHas('status', fn ($s) => $s->where('name', 'like', $search))
                        ->orWhereHas('assignedUsers', fn ($u) => $u
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search));
                });
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request) {
                $query->whereHas('status', fn ($q) => $q->where('name', $this->mapStatusFilter($request->input('status'))));
            })
            ->orderBy('tasks.due_date', 'asc')
            ->orderBy('tasks.created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Task $task) => $this->formatTaskForList($task, $currentUserId));

        $statuses = TaskStatus::query()
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        return Inertia::render('tasks/Tasks', [
            'tasks' => $tasks,
            'statuses' => $statuses,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
            'projects' => $this->getProjectsForTaskCreation($request->user()),
        ]);
    }

    /**
     * Map frontend filter values to database status names.
     */
    private function mapStatusFilter(string $filter): string
    {
        return match ($filter) {
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'in_review' => 'In Review',
            'done' => 'Done',
            'blocked' => 'Blocked',
            default => $filter,
        };
    }

    /**
     * Display tasks assigned to a specific user.
     *
     * Authorization:
     * - Admins can view any user's tasks
     * - Users in shared teams can view each other's tasks (for commenting)
     * - Users can view their own tasks
     *
     * TaskAccessScope further filters to only show tasks the viewer has access to.
     */
    public function forUser(User $user, Request $request): Response
    {
        // Authorization: Use UserPolicy to check if viewer can see this user
        // Admins bypass via policy, others need shared team membership
        Gate::authorize('view', $user);

        // Bypass TaskAccessScope since we've authorized via UserPolicy
        // This allows viewing teammate's tasks (for commenting)
        $tasks = Task::withoutGlobalScope(TaskAccessScope::class)
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.priority',
                'tasks.due_date',
                'tasks.project_id',
                'tasks.status_id',
                'tasks.created_by',
            ])
            ->whereHas('assignedUsers', fn ($query) => $query->where('users.id', $user->id))
            ->withCount('assignedUsers')
            ->with([
                'project:id,name',
                'status:id,name',
                'creator:id,first_name,last_name',
                'assignedUsers' => fn ($query) => $query
                    ->select('users.id', 'users.first_name', 'users.last_name'),
            ])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function (Builder $q) use ($search) {
                    $q->where('tasks.title', 'like', $search)
                        ->orWhere('tasks.priority', 'like', $search)
                        ->orWhereHas('project', fn ($p) => $p->where('name', 'like', $search))
                        ->orWhereHas('status', fn ($s) => $s->where('name', 'like', $search))
                        ->orWhereHas('assignedUsers', fn ($u) => $u
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search));
                });
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request) {
                $query->whereHas('status', fn ($q) => $q->where('name', $this->mapStatusFilter($request->input('status'))));
            })
            ->orderBy('tasks.due_date', 'asc')
            ->orderBy('tasks.created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Task $task) => $this->formatTaskForList($task, $user->id));

        $statuses = TaskStatus::query()
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        return Inertia::render('tasks/Tasks', [
            'tasks' => $tasks,
            'statuses' => $statuses,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
            ],
            'projects' => $this->getProjectsForTaskCreation($request->user()),
        ]);
    }

    /**
     * Store a newly created task.
     *
     * Authorization: TaskStoreRequest checks user can create tasks in the project.
     * Default status: "To Do" (first status in task_statuses table).
     * Logs task_created activity automatically.
     */
    public function store(TaskStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Get default status ("To Do")
        $defaultStatus = TaskStatus::query()
            ->where('name', 'To Do')
            ->first();

        $task = DB::transaction(function () use ($validated, $defaultStatus, $user) {
            $task = Task::create([
                'project_id' => $validated['project_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'],
                'status_id' => $defaultStatus->id,
                'due_date' => $validated['due_date'] ?? null,
                'created_by' => $user->id,
            ]);

            // Log task creation activity
            $task->logTaskActivity('task_created', [
                'title' => $task->title,
                'project_id' => $task->project_id,
            ], $user->id);

            return $task;
        });

        // Determine redirect URL - respect return_to parameter for context-aware navigation
        $redirectUrl = $this->resolveRedirectUrl($request, $task->project_id);

        return redirect($redirectUrl)
            ->with('success', 'Task created successfully.');
    }

    /**
     * Get projects available for task creation based on user role.
     *
     * - Admins: all active projects
     * - Managers: projects they manage
     *
     * Bypasses global scopes since authorization is based on management role,
     * not team membership.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function getProjectsForTaskCreation(User $user): array
    {
        if ($user->isAdmin()) {
            return Project::withoutGlobalScopes()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                ])
                ->toArray();
        }

        if ($user->isManager()) {
            return $user->managedProjects()
                ->withoutGlobalScopes()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                ])
                ->toArray();
        }

        return [];
    }

    /**
     * Format assignee display as "You + X others" or "Name + X others".
     */
    private function formatAssigneeDisplay(Task $task, int $contextUserId): ?string
    {
        $assignees = $task->assignedUsers;
        $totalCount = $task->assigned_users_count;

        if ($assignees->isEmpty()) {
            return null;
        }

        $isContextUserAssigned = $assignees->contains('id', $contextUserId);
        $othersCount = $totalCount - 1;

        if ($isContextUserAssigned) {
            // Show "You" or "You + X others"
            return $othersCount > 0
                ? "You + {$othersCount} " . ($othersCount === 1 ? 'other' : 'others')
                : 'You';
        }

        // Show first assignee name or "Name + X others"
        $firstAssignee = $assignees->first();
        $name = $firstAssignee->first_name . ' ' . $firstAssignee->last_name;

        return $othersCount > 0
            ? "{$name} + {$othersCount} " . ($othersCount === 1 ? 'other' : 'others')
            : $name;
    }

    /**
     * Format a task for list display including per-task permissions.
     *
     * @return array<string, mixed>
     */
    private function formatTaskForList(Task $task, int $contextUserId): array
    {
        $user = request()->user();

        return [
            'id' => $task->id,
            'title' => $task->title,
            'project' => $task->project?->name,
            'assignee' => $this->formatAssigneeDisplay($task, $contextUserId),
            'created_by' => $task->creator
                ? $task->creator->first_name . ' ' . $task->creator->last_name
                : null,
            'status' => $task->status?->name,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('F j, Y'),
            'can' => [
                'view' => Gate::allows('view', $task),
                'delete' => Gate::allows('delete', $task),
            ],
        ];
    }

    /**
     * Display the specified task with comments and activity history.
     */
    public function show(int $task): Response
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('view', $task);

        // Determine the back URL from referer (supports user-scoped task lists)
        $backUrl = $this->resolveTaskListUrl();

        $task->load([
            'project:id,name',
            'status:id,name',
            'assignedUsers:users.id,first_name,last_name',
            'comments' => fn ($query) => $query
                ->with('user:id,first_name,last_name')
                ->orderBy('created_at', 'desc'),
            'taskActivityLogs' => fn ($query) => $query
                ->with('actor:id,first_name,last_name')
                ->orderBy('created_at', 'desc')
                ->limit(50),
        ]);

        $statuses = TaskStatus::query()
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        // Get available users for assignment - limited to project team members
        $availableUsers = collect();
        $hasProjectTeams = false;

        if ($task->project_id) {
            // Check if the project has any teams assigned
            $projectTeamIds = DB::table('team_projects')
                ->where('project_id', $task->project_id)
                ->pluck('team_id');

            $hasProjectTeams = $projectTeamIds->isNotEmpty();

            if ($hasProjectTeams) {
                // Get users from teams assigned to this project, with their team names
                // Use withoutGlobalScopes on teams relationship to bypass TeamMembershipScope
                $availableUsers = User::query()
                    ->whereHas('teams', fn ($query) => $query->withoutGlobalScopes()->whereIn('teams.id', $projectTeamIds))
                    ->with(['teams' => fn ($query) => $query->withoutGlobalScopes()->whereIn('teams.id', $projectTeamIds)->select('teams.id', 'teams.name')])
                    ->select('id', 'first_name', 'last_name')
                    ->orderBy('first_name')
                    ->get()
                    ->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->first_name . ' ' . $u->last_name,
                        'team' => $u->teams->pluck('name')->join(', '),
                    ]);
            }
        }

        return Inertia::render('tasks/Show', [
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'project_id' => $task->project_id,
                'project' => $task->project?->name,
                'status_id' => $task->status_id,
                'status' => $task->status?->name,
                'priority' => $task->priority,
                'progress' => $task->progress ?? 0,
                'due_date' => $task->due_date?->format('F j, Y'),
                'due_date_raw' => $task->due_date?->format('Y-m-d'),
                'assignees' => $task->assignedUsers->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ]),
                'created_at' => $task->created_at->format('F j, Y'),
                'version' => $task->version,
            ],
            'comments' => $task->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'text' => $comment->comment_text,
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                ],
                'created_at' => $comment->created_at->diffForHumans(),
                'updated_at' => $comment->updated_at->diffForHumans(),
                'is_edited' => $comment->updated_at->gt($comment->created_at),
                'can_edit' => Gate::allows('update', $comment),
            ]),
            'activities' => $task->taskActivityLogs->map(fn ($log) => [
                'id' => $log->id,
                'type' => $log->activity_type,
                'metadata' => $log->metadata,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->first_name . ' ' . $log->actor->last_name,
                ] : null,
                'created_at' => $log->created_at->diffForHumans(),
            ]),
            'statuses' => $statuses,
            'availableUsers' => $availableUsers,
            'hasProjectTeams' => $hasProjectTeams,
            'can' => [
                'edit' => Gate::allows('edit', $task),
                'update' => Gate::allows('update', $task),
                'delete' => Gate::allows('delete', $task),
                'updateProgress' => Gate::allows('updateProgress', $task),
                'assign' => Gate::allows('assign', $task),
            ],
            'backUrl' => $backUrl,
        ]);
    }

    /**
     * Resolve the back URL from the referer.
     * Returns user to an authorized page based on their role.
     * Supports:
     * - User-scoped task lists (e.g., /users/4/tasks)
     * - Project detail pages (e.g., /projects/5)
     */
    private function resolveTaskListUrl(): string
    {
        $referer = request()->headers->get('referer');

        if ($referer) {
            $path = parse_url($referer, PHP_URL_PATH);

            // Check for user-scoped task lists
            if (preg_match('#/users/\d+/tasks#', $path)) {
                return $path;
            }

            // Check for project detail pages
            if (preg_match('#/projects/\d+$#', $path)) {
                return $path;
            }
        }

        // Role-aware fallback: only admins can access global /tasks
        $user = auth()->user();
        if ($user && $user->isAdmin()) {
            return route('tasks', absolute: false);
        }

        // Non-admins go to their My Tasks page
        return route('users.tasks', ['user' => auth()->id()], false);
    }

    /**
     * Resolve redirect URL after task creation/deletion.
     * Returns user to the most appropriate authorized page.
     */
    private function resolveRedirectUrl(Request $request, ?int $projectId = null): string
    {
        // Check for explicit return_to parameter
        if ($request->filled('return_to')) {
            $returnTo = $request->input('return_to');
            // Validate it's a safe internal path
            if (str_starts_with($returnTo, '/')) {
                return $returnTo;
            }
        }

        // Always prefer project page when project context is available
        // This is the most natural destination for task operations
        if ($projectId) {
            return route('projects.show', $projectId, false);
        }

        // For admin users, return to global tasks list
        // For non-admins, return to their own tasks (My Tasks)
        $user = $request->user();
        if ($user && $user->isAdmin()) {
            return route('tasks', absolute: false);
        }

        // Default to user's task list (My Tasks)
        return route('users.tasks', ['user' => $request->user()?->id], false);
    }

    /**
     * Show the form for editing the specified task.
     * Only Managers and Admins can fully edit tasks.
     */
    public function edit(int $task): Response
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('edit', $task);

        $backUrl = $this->resolveTaskListUrl();

        $task->load([
            'project:id,name',
            'status:id,name',
            'assignedUsers:users.id,first_name,last_name',
        ]);

        $statuses = TaskStatus::query()
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        return Inertia::render('tasks/Edit', [
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'project_id' => $task->project_id,
                'project' => $task->project?->name,
                'status_id' => $task->status_id,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'assignee_ids' => $task->assignedUsers->pluck('id')->toArray(),
            ],
            'statuses' => $statuses,
            'backUrl' => $backUrl,
        ]);
    }

    /**
     * Remove the specified task from storage (soft delete).
     * Respects context-aware navigation via referer or return_to param.
     */
    public function destroy(Request $request, int $task): RedirectResponse
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('delete', $task);

        $taskName = $task->title;
        $projectId = $task->project_id;
        $projectName = $task->project?->name ?? 'Unknown';

        $task->delete();

        // Determine redirect URL - return to project if that's the context
        $redirectUrl = $this->resolveRedirectUrl($request, $projectId);

        return redirect($redirectUrl)
            ->with('success', "Task '{$taskName}' in '{$projectName}' has been deleted.");
    }

    /**
     * Update the specified task (full edit).
     * Only creators, managers, and admins can fully edit tasks.
     * Uses transaction with optimistic locking for concurrency safety.
     */
    public function update(Request $request, int $task): RedirectResponse
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('edit', $task);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status_id' => ['nullable', 'integer', 'exists:task_statuses,id'],
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'due_date' => ['nullable', 'date'],
            'version' => ['nullable', 'integer'],
        ]);

        return $this->withVersionCheck(
            entity: $task,
            expectedVersion: $validated['version'] ?? null,
            callback: function () use ($task, $validated) {
                // Track changes for activity logging
                $changes = [];
                $fieldsToTrack = ['title', 'description', 'priority', 'due_date'];

                foreach ($fieldsToTrack as $field) {
                    $oldValue = $task->$field;
                    $newValue = $validated[$field] ?? null;

                    // Normalize values for comparison
                    if ($field === 'due_date') {
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
                $newStatusId = $validated['status_id'] ?? $task->status_id;
                if ($task->status_id !== $newStatusId) {
                    $oldStatus = $task->status?->name ?? 'None';
                    $newStatus = TaskStatus::find($newStatusId)?->name ?? 'None';
                    $changes['status'] = [
                        'old' => $oldStatus,
                        'new' => $newStatus,
                    ];
                }

                $task->update([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'status_id' => $newStatusId,
                    'priority' => $validated['priority'],
                    'due_date' => $validated['due_date'] ?? null,
                ]);

                // Log specific field changes
                if (! empty($changes)) {
                    $task->logTaskActivity('task_updated', [
                        'changes' => $changes,
                    ]);
                }

                return redirect()
                    ->route('tasks.show', $task->id)
                    ->with('success', 'Task updated successfully.');
            },
            operationName: 'Update Task'
        );
    }

    /**
     * Update the task's status.
     * Logs activity with old and new status.
     * Uses transaction with optimistic locking for concurrency safety.
     */
    public function updateStatus(Request $request, int $task): RedirectResponse
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'status_id' => ['required', 'integer', 'exists:task_statuses,id'],
            'version' => ['nullable', 'integer'],
        ]);

        return $this->withVersionCheck(
            entity: $task,
            expectedVersion: $validated['version'] ?? null,
            callback: function () use ($task, $validated) {
                $oldStatus = $task->status?->name ?? 'None';
                $task->update(['status_id' => $validated['status_id']]);
                $task->load('status:id,name');
                $newStatus = $task->status->name;

                $task->logTaskActivity('status_changed', [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                return back()->with('success', "Status changed from '{$oldStatus}' to '{$newStatus}'.");
            },
            operationName: 'Update Task Status'
        );
    }

    /**
     * Update the task's priority.
     * Logs activity with old and new priority.
     * Uses transaction with optimistic locking for concurrency safety.
     */
    public function updatePriority(Request $request, int $task): RedirectResponse
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'version' => ['nullable', 'integer'],
        ]);

        return $this->withVersionCheck(
            entity: $task,
            expectedVersion: $validated['version'] ?? null,
            callback: function () use ($task, $validated) {
                $oldPriority = $task->priority;
                $task->update(['priority' => $validated['priority']]);

                $task->logTaskActivity('priority_changed', [
                    'old_priority' => $oldPriority,
                    'new_priority' => $validated['priority'],
                ]);

                return back()->with('success', "Priority changed from '{$oldPriority}' to '{$validated['priority']}'.");
            },
            operationName: 'Update Task Priority'
        );
    }

    /**
     * Update the task's progress (0-100).
     * Assigned users can update progress; logs the change.
     * Uses transaction with optimistic locking for concurrency safety.
     */
    public function updateProgress(Request $request, int $task): RedirectResponse
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('updateProgress', $task);

        $validated = $request->validate([
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'version' => ['nullable', 'integer'],
        ]);

        return $this->withVersionCheck(
            entity: $task,
            expectedVersion: $validated['version'] ?? null,
            callback: function () use ($task, $validated) {
                $oldProgress = $task->progress ?? 0;
                $task->update(['progress' => $validated['progress']]);

                $task->logTaskActivity('progress_updated', [
                    'old_progress' => $oldProgress,
                    'new_progress' => (int) $validated['progress'],
                ]);

                return back()->with('success', "Progress updated to {$validated['progress']}%.");
            },
            operationName: 'Update Task Progress'
        );
    }

    /**
     * Add an assignee to the task.
     * Only admins and project managers can assign users.
     * Uses pessimistic locking to prevent race conditions in concurrent assignments.
     */
    public function addAssignee(Request $request, int $task): RedirectResponse
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('assign', $task);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        return $this->withLockedTransaction(
            operationName: 'Add Task Assignee',
            entity: $task,
            callback: function ($lockedTask) use ($validated, $user, $request) {
                // Check if already assigned (after acquiring lock to prevent race)
                if ($lockedTask->assignedUsers()->where('user_id', $validated['user_id'])->exists()) {
                    return back()->withErrors(['user_id' => 'User is already assigned to this task.']);
                }

                $lockedTask->assignedUsers()->attach($validated['user_id'], [
                    'assigned_by' => $request->user()->id,
                    'assigned_date' => now(),
                ]);

                $lockedTask->logTaskActivity('assignee_added', [
                    'user_id' => $user->id,
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                ]);

                return back()->with('success', "{$user->first_name} {$user->last_name} has been assigned.");
            },
            operationType: TransactionLog::TYPE_ASSIGN
        );
    }

    /**
     * Remove an assignee from the task.
     * Only admins and project managers can unassign users.
     * Uses pessimistic locking to prevent race conditions.
     */
    public function removeAssignee(int $task, int $user): RedirectResponse
    {
        $task = Task::withoutGlobalScopes()->findOrFail($task);
        Gate::authorize('assign', $task);

        $userModel = User::findOrFail($user);

        return $this->withLockedTransaction(
            operationName: 'Remove Task Assignee',
            entity: $task,
            callback: function ($lockedTask) use ($user, $userModel) {
                // Check if actually assigned (after acquiring lock)
                if (! $lockedTask->assignedUsers()->where('user_id', $user)->exists()) {
                    return back()->withErrors(['user_id' => 'User is not assigned to this task.']);
                }

                $lockedTask->assignedUsers()->detach($user);

                $lockedTask->logTaskActivity('assignee_removed', [
                    'user_id' => $userModel->id,
                    'user_name' => $userModel->first_name . ' ' . $userModel->last_name,
                ]);

                return back()->with('success', "{$userModel->first_name} {$userModel->last_name} has been unassigned.");
            },
            operationType: TransactionLog::TYPE_ASSIGN
        );
    }
}
