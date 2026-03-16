<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardRouter;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardRouter::class, 'index'])->name('dashboard');

    // Admin-only: global index routes
    Route::middleware('role:Admin')->group(function () {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects');
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks');
        Route::get('teams', [TeamController::class, 'index'])->name('teams');
    });

    // Projects: individual access controlled by ProjectPolicy
    Route::post('projects', [ProjectController::class, 'store'])->middleware('throttle:creation')->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('throttle:creation')->name('projects.update');
    Route::patch('projects/{project}/teams', [ProjectController::class, 'updateTeams'])->name('projects.updateTeams');

    // Tasks: individual access controlled by TaskPolicy
    Route::post('tasks', [TaskController::class, 'store'])->middleware('throttle:creation')->name('tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('throttle:creation')->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Task activity updates (status, priority, progress, assignees)
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.updateStatus');
    Route::patch('tasks/{task}/priority', [TaskController::class, 'updatePriority'])->name('tasks.updatePriority');
    Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress'])->name('tasks.updateProgress');
    Route::post('tasks/{task}/assignees', [TaskController::class, 'addAssignee'])->name('tasks.addAssignee');
    Route::delete('tasks/{task}/assignees/{user}', [TaskController::class, 'removeAssignee'])->name('tasks.removeAssignee');

    // Task comments
    Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->middleware('throttle:creation')->name('tasks.comments.store');
    Route::patch('comments/{comment}', [CommentController::class, 'update'])->middleware('throttle:creation')->name('comments.update');

    // Teams: individual access controlled by TeamPolicy
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->middleware('throttle:creation')->name('teams.addMember');
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.removeMember');

    // User-scoped routes: users can view their own resources
    Route::get('users/{user}/projects', [ProjectController::class, 'forUser'])->name('users.projects');
    Route::get('users/{user}/tasks', [TaskController::class, 'forUser'])->name('users.tasks');
    Route::get('users/{user}/teams', [TeamController::class, 'forUser'])->name('users.teams');

    // Documentation
    Route::middleware('role:Developer')->group(function () {
        Route::inertia('docs', 'docs/Index')->name('docs');
    });

    // Admin-only: management routes
    Route::middleware('role:Admin')->prefix('admin')->name('admin.')->group(function () {
        // Audit logs
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        // Team management
        // Route::resource('teams', AdminTeamController::class);

        // Project management (create, assign managers)
        // Route::resource('projects', AdminProjectController::class);

        // User role management
        // Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        // Route::put('users/{user}/roles', [AdminUserController::class, 'updateRoles'])->name('users.roles');
    });

    // Team-scoped routes: require team membership
    Route::middleware('team.access')->prefix('teams/{team}')->name('teams.')->group(function () {
        // Team projects
        // Route::get('projects', [TeamProjectController::class, 'index'])->name('projects.index');

        // Team members (Managers can add members)
        // Route::post('members', [TeamMemberController::class, 'store'])
        //     ->middleware('role:Admin,Manager')
        //     ->name('members.store');
    });
});

require __DIR__.'/settings.php';
