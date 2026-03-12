<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    ProjectStatus::factory()->create(['name' => 'In Progress']);
    TaskStatus::factory()->create(['name' => 'To Do']);
    TaskStatus::factory()->create(['name' => 'In Progress']);
    TaskStatus::factory()->create(['name' => 'Done']);
    $this->adminRole = Role::factory()->admin()->create();
    $this->memberRole = Role::factory()->member()->create();
    $this->managerRole = Role::factory()->manager()->create();
});

test('tasks page requires authentication', function () {
    $this->get('/tasks')
        ->assertRedirect('/login');
});

test('admin can view tasks page', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tasks/Tasks'));
});

test('non-admin cannot access tasks index', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $this->actingAs($member)
        ->get('/tasks')
        ->assertForbidden();
});

test('tasks page returns correct data structure', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Tasks')
            ->has('tasks.data')
            ->has('statuses')
            ->has('filters')
            ->has('tasks.data.0', fn ($page) => $page
                ->has('id')
                ->has('title')
                ->has('project')
                ->has('assignee')
                ->has('created_by')
                ->has('status')
                ->has('priority')
                ->has('due_date')
                ->has('can')
            )
        );
});

test('tasks can be searched by title', function () {
    // Admin can see all tasks
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    Task::factory()->forProject($project)->create(['title' => 'Design landing page']);
    Task::factory()->forProject($project)->create(['title' => 'Build API endpoints']);
    Task::factory()->forProject($project)->create(['title' => 'Write documentation']);

    $this->actingAs($admin)
        ->get('/tasks?search=Design')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Design landing page')
        );
});

test('tasks can be filtered by status', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $todoStatus = TaskStatus::where('name', 'To Do')->first();
    $doneStatus = TaskStatus::where('name', 'Done')->first();

    Task::factory()->forProject($project)->withStatus($todoStatus)->create(['title' => 'Todo task']);
    Task::factory()->forProject($project)->withStatus($doneStatus)->create(['title' => 'Done task']);

    $this->actingAs($admin)
        ->get('/tasks?status=todo')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Todo task')
        );
});

test('tasks page returns all statuses', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('statuses', 3)
        );
});

test('tasks page preserves filters in response', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $this->actingAs($admin)
        ->get('/tasks?search=test&status=in_progress')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'test')
            ->where('filters.status', 'in_progress')
        );
});

test('tasks are paginated', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    Task::factory(20)->forProject($project)->create();

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 10)
            ->where('tasks.total', 20)
            ->where('tasks.per_page', 10)
        );
});

test('tasks show You when current user is assignee', function () {
    $admin = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create();
    $task->assignedUsers()->attach($admin->id, [
        'assigned_by' => $admin->id,
        'assigned_date' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.data.0.assignee', 'You')
        );
});

test('tasks show You + X others when multiple assignees include current user', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create();
    // Assign 3 users including the current admin
    $task->assignedUsers()->attach([
        $admin->id => ['assigned_by' => $admin->id, 'assigned_date' => now()],
        $otherUser1->id => ['assigned_by' => $admin->id, 'assigned_date' => now()],
        $otherUser2->id => ['assigned_by' => $admin->id, 'assigned_date' => now()],
    ]);

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.data.0.assignee', 'You + 2 others')
        );
});

test('tasks show other user name when viewing as admin', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $assignee = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create();
    $task->assignedUsers()->attach($assignee->id, [
        'assigned_by' => $admin->id,
        'assigned_date' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.data.0.assignee', 'Jane Smith')
        );
});

// ===== USER-SCOPED TASK ROUTES =====

test('user tasks route requires authentication', function () {
    $user = User::factory()->create();

    $this->get("/users/{$user->id}/tasks")
        ->assertRedirect('/login');
});

test('admin can view any user tasks', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $assignee = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
    $assignee->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $assignee->teams()->attach($team);

    $project = Project::factory()->create();
    $project->teams()->attach($team);

    $assignedTask = Task::factory()->forProject($project)->create(['title' => 'Assigned Task']);
    $assignedTask->assignedUsers()->attach($assignee->id, [
        'assigned_by' => $admin->id,
        'assigned_date' => now(),
    ]);

    $this->actingAs($admin)
        ->get("/users/{$assignee->id}/tasks")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Tasks')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Assigned Task')
            ->has('user', fn ($page) => $page
                ->where('id', $assignee->id)
                ->where('name', 'Jane Smith')
            )
        );
});

test('manager can view tasks of users in their team', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $member = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
    $member->roles()->attach($this->memberRole);

    // Both in same team
    $team = Team::factory()->create();
    $manager->teams()->attach($team);
    $member->teams()->attach($team);

    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->teams()->attach($team);

    $assignedTask = Task::factory()->forProject($project)->create(['title' => 'Member Task']);
    $assignedTask->assignedUsers()->attach($member->id, [
        'assigned_by' => $manager->id,
        'assigned_date' => now(),
    ]);

    $this->actingAs($manager)
        ->get("/users/{$member->id}/tasks")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Tasks')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Member Task')
        );
});

test('user can view their own tasks', function () {
    $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $user->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $user->teams()->attach($team);

    $project = Project::factory()->create();
    $project->teams()->attach($team);

    $task = Task::factory()->forProject($project)->create(['title' => 'My Task']);
    $task->assignedUsers()->attach($user->id, [
        'assigned_by' => $user->id,
        'assigned_date' => now(),
    ]);

    $this->actingAs($user)
        ->get("/users/{$user->id}/tasks")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Tasks')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'My Task')
        );
});

test('member can view tasks of user in their team', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $teammate = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $teammate->roles()->attach($this->memberRole);

    // Both in same team
    $team = Team::factory()->create();
    $member->teams()->attach($team);
    $teammate->teams()->attach($team);

    $project = Project::factory()->create();
    $project->teams()->attach($team);

    $task = Task::factory()->forProject($project)->create(['title' => 'Teammate Task']);
    $task->assignedUsers()->attach($teammate->id, [
        'assigned_by' => $member->id,
        'assigned_date' => now(),
    ]);

    // Member can view teammate's tasks (for commenting)
    $this->actingAs($member)
        ->get("/users/{$teammate->id}/tasks")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Tasks')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Teammate Task')
        );
});

test('member cannot view tasks of user outside their team', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $otherUser = User::factory()->create();
    $otherUser->roles()->attach($this->memberRole);

    // Users in different teams
    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();
    $member->teams()->attach($team1);
    $otherUser->teams()->attach($team2);

    $this->actingAs($member)
        ->get("/users/{$otherUser->id}/tasks")
        ->assertForbidden();
});

test('user tasks route supports search filtering', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $assignee = User::factory()->create();
    $assignee->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $assignee->teams()->attach($team);

    $project = Project::factory()->create();
    $project->teams()->attach($team);

    $task1 = Task::factory()->forProject($project)->create(['title' => 'Fix bug']);
    $task2 = Task::factory()->forProject($project)->create(['title' => 'Add feature']);

    $task1->assignedUsers()->attach($assignee->id, ['assigned_by' => $admin->id, 'assigned_date' => now()]);
    $task2->assignedUsers()->attach($assignee->id, ['assigned_by' => $admin->id, 'assigned_date' => now()]);

    $this->actingAs($admin)
        ->get("/users/{$assignee->id}/tasks?search=bug")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Fix bug')
        );
});

// ===== TASK STORE TESTS =====

test('store requires authentication', function () {
    $this->post('/tasks', [])
        ->assertRedirect('/login');
});

test('admin can create task in any project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $todoStatus = TaskStatus::where('name', 'To Do')->first();

    $this->actingAs($admin)
        ->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'New Admin Task',
            'description' => 'Task description',
            'priority' => 'high',
            'due_date' => now()->addWeek()->format('Y-m-d'),
        ])
        ->assertRedirect("/projects/{$project->id}");

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'title' => 'New Admin Task',
        'description' => 'Task description',
        'priority' => 'high',
        'status_id' => $todoStatus->id,
    ]);
});

test('manager can create task in managed project', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $manager->refresh()->loadMissing('managedProjects');

    $this->actingAs($manager)
        ->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'Manager Task',
            'priority' => 'medium',
        ])
        ->assertRedirect("/projects/{$project->id}");

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'title' => 'Manager Task',
        'priority' => 'medium',
    ]);
});

test('manager cannot create task in project they do not manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $otherManager = User::factory()->create();
    $project = Project::factory()->create(['manager_id' => $otherManager->id]);

    $this->actingAs($manager)
        ->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'Unauthorized Task',
            'priority' => 'low',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('tasks', [
        'title' => 'Unauthorized Task',
    ]);
});

test('member cannot create tasks', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $project = Project::factory()->create();

    $this->actingAs($member)
        ->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'Member Task',
            'priority' => 'low',
        ])
        ->assertForbidden();
});

test('task store validates required fields', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $this->actingAs($admin)
        ->post('/tasks', [])
        ->assertSessionHasErrors(['project_id', 'title', 'priority']);
});

test('task store validates priority values', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'Test Task',
            'priority' => 'invalid_priority',
        ])
        ->assertSessionHasErrors(['priority']);
});

test('task store validates unique title within project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    Task::factory()->forProject($project)->create(['title' => 'Existing Task']);

    $this->actingAs($admin)
        ->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'Existing Task',
            'priority' => 'low',
        ])
        ->assertSessionHasErrors(['title']);
});

test('task store allows same title in different projects', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project1 = Project::factory()->create();
    $project2 = Project::factory()->create();
    Task::factory()->forProject($project1)->create(['title' => 'Shared Task Name']);

    $this->actingAs($admin)
        ->post('/tasks', [
            'project_id' => $project2->id,
            'title' => 'Shared Task Name',
            'priority' => 'low',
        ])
        ->assertRedirect("/projects/{$project2->id}");

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project2->id,
        'title' => 'Shared Task Name',
    ]);
});

test('task store assigns default To Do status', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $todoStatus = TaskStatus::where('name', 'To Do')->first();

    $this->actingAs($admin)
        ->post('/tasks', [
            'project_id' => $project->id,
            'title' => 'Status Test Task',
            'priority' => 'medium',
        ])
        ->assertRedirect("/projects/{$project->id}");

    $task = Task::where('title', 'Status Test Task')->first();
    expect($task->status_id)->toBe($todoStatus->id);
});

test('tasks page returns projects for task creation', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project1 = Project::factory()->create(['name' => 'Project Alpha']);
    $project2 = Project::factory()->create(['name' => 'Project Beta']);

    $this->actingAs($admin)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 2)
            ->where('projects.0.name', 'Project Alpha')
            ->where('projects.1.name', 'Project Beta')
        );
});

test('manager cannot access tasks index', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $this->actingAs($manager)
        ->get('/tasks')
        ->assertForbidden();
});

test('manager sees managed projects in user tasks route', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $otherManager = User::factory()->create();

    $managedProject = Project::factory()->create(['name' => 'My Project', 'manager_id' => $manager->id]);
    Project::factory()->create(['name' => 'Other Project', 'manager_id' => $otherManager->id]);

    $this->actingAs($manager)
        ->get("/users/{$manager->id}/tasks")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'My Project')
        );
});

// =========================================================================
// SHOW TESTS
// =========================================================================

test('admin can view task details', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create(['name' => 'Test Project']);
    $task = Task::factory()->forProject($project)->create([
        'title' => 'Test Task',
        'description' => 'Test description',
        'priority' => 'high',
    ]);

    $this->actingAs($admin)
        ->get("/tasks/{$task->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Show')
            ->where('task.title', 'Test Task')
            ->where('task.description', 'Test description')
            ->where('task.project', 'Test Project')
            ->where('task.priority', 'high')
            ->has('can.update')
            ->has('can.delete')
        );
});

test('member can view assigned task details', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    $project = Project::factory()->create();
    $project->teams()->attach($team);

    $task = Task::factory()->forProject($project)->create(['title' => 'Assigned Task']);
    $task->assignedUsers()->attach($member->id, [
        'assigned_by' => $member->id,
        'assigned_date' => now(),
    ]);

    $this->actingAs($member)
        ->get("/tasks/{$task->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Show')
            ->where('task.title', 'Assigned Task')
        );
});

test('member cannot view unassigned task', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($member)
        ->get("/tasks/{$task->id}")
        ->assertForbidden();
});

// =========================================================================
// EDIT TESTS
// =========================================================================

test('admin can access task edit page', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create(['title' => 'Editable Task']);

    $this->actingAs($admin)
        ->get("/tasks/{$task->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Edit')
            ->where('task.title', 'Editable Task')
            ->has('statuses')
        );
});

test('manager can edit tasks in their project', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($manager)
        ->get("/tasks/{$task->id}/edit")
        ->assertOk();
});

test('member cannot access task edit page', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($member)
        ->get("/tasks/{$task->id}/edit")
        ->assertForbidden();
});

test('creator can access task edit page', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $task = Task::factory()->forProject($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->get("/tasks/{$task->id}/edit")
        ->assertOk();
});

test('admin can update task via PUT', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create(['title' => 'Original Title']);

    $this->actingAs($admin)
        ->put("/tasks/{$task->id}", [
            'title' => 'Updated Title',
            'priority' => 'high',
        ])
        ->assertRedirect("/tasks/{$task->id}");

    $task->refresh();
    expect($task->title)->toBe('Updated Title');
    expect($task->priority)->toBe('high');
});

test('creator can update their own task', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $task = Task::factory()->forProject($project)->create([
        'title' => 'My Task',
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->put("/tasks/{$task->id}", [
            'title' => 'My Updated Task',
            'priority' => 'critical',
        ])
        ->assertRedirect("/tasks/{$task->id}");

    $task->refresh();
    expect($task->title)->toBe('My Updated Task');
    expect($task->priority)->toBe('critical');
});

test('member cannot update task they did not create', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create();

    // Assign member to the task so they can view it
    $task->assignedUsers()->attach($member->id, [
        'assigned_by' => $task->creator?->id ?? 1,
        'assigned_date' => now(),
    ]);

    $this->actingAs($member)
        ->put("/tasks/{$task->id}", [
            'title' => 'Attempted Update',
            'priority' => 'low',
        ])
        ->assertForbidden();
});

// =========================================================================
// DELETE TESTS
// =========================================================================

test('admin can delete a task', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $task = Task::factory()->forProject($project)->create(['title' => 'To Delete']);

    $this->actingAs($admin)
        ->delete("/tasks/{$task->id}")
        ->assertRedirect("/projects/{$project->id}");

    expect(Task::withoutGlobalScopes()->withTrashed()->find($task->id)->trashed())->toBeTrue();
});

test('manager can delete tasks in their project', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($manager)
        ->delete("/tasks/{$task->id}")
        ->assertRedirect("/projects/{$project->id}");

    expect(Task::withoutGlobalScopes()->withTrashed()->find($task->id)->trashed())->toBeTrue();
});

test('manager cannot delete tasks outside their project', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $otherManager = User::factory()->create();
    $project = Project::factory()->create(['manager_id' => $otherManager->id]);
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($manager)
        ->delete("/tasks/{$task->id}")
        ->assertForbidden();

    expect(Task::withoutGlobalScopes()->find($task->id))->not->toBeNull();
});

test('member cannot delete tasks', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    $project = Project::factory()->create();
    $project->teams()->attach($team);

    $task = Task::factory()->forProject($project)->create();
    $task->assignedUsers()->attach($member->id, [
        'assigned_by' => $member->id,
        'assigned_date' => now(),
    ]);

    $this->actingAs($member)
        ->delete("/tasks/{$task->id}")
        ->assertForbidden();
});
