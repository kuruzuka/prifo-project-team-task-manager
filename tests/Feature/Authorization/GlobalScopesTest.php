<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    // Create roles
    $this->adminRole = Role::factory()->admin()->create();
    $this->managerRole = Role::factory()->manager()->create();
    $this->memberRole = Role::factory()->create(['name' => 'Member']);

    // Create required statuses
    $projectStatus = ProjectStatus::factory()->create();
    $taskStatus = TaskStatus::factory()->create();

    // Create two teams with separate projects
    $this->team1 = Team::factory()->create(['name' => 'Team One']);
    $this->team2 = Team::factory()->create(['name' => 'Team Two']);

    // Create projects for each team using factory (bypassing global scope)
    $this->project1 = Project::withoutGlobalScopes()->create([
        'name' => 'Project One',
        'description' => 'Team 1 project',
        'status_id' => $projectStatus->id,
        'start_date' => now(),
        'end_date' => now()->addMonths(3),
    ]);
    $this->project1->teams()->attach($this->team1);

    $this->project2 = Project::withoutGlobalScopes()->create([
        'name' => 'Project Two',
        'description' => 'Team 2 project',
        'status_id' => $projectStatus->id,
        'start_date' => now(),
        'end_date' => now()->addMonths(3),
    ]);
    $this->project2->teams()->attach($this->team2);

    // Create tasks for each project
    $this->task1 = Task::withoutGlobalScopes()->create([
        'project_id' => $this->project1->id,
        'title' => 'Task One',
        'description' => 'Task in project 1',
        'status_id' => $taskStatus->id,
    ]);

    $this->task2 = Task::withoutGlobalScopes()->create([
        'project_id' => $this->project2->id,
        'title' => 'Task Two',
        'description' => 'Task in project 2',
        'status_id' => $taskStatus->id,
    ]);
});

// =========================================================================
// TEAM SCOPE TESTS
// =========================================================================

test('admin can see all teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);
    $this->actingAs($admin);

    $teams = Team::all();

    expect($teams)->toHaveCount(2)
        ->and($teams->pluck('name')->toArray())->toContain('Team One', 'Team Two');
});

test('member can only see teams they belong to', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team1);
    $this->actingAs($member);

    $teams = Team::all();

    expect($teams)->toHaveCount(1)
        ->and($teams->first()->name)->toBe('Team One');
});

test('unauthenticated user sees no teams', function () {
    $teams = Team::all();

    expect($teams)->toHaveCount(0);
});

// =========================================================================
// PROJECT SCOPE TESTS
// =========================================================================

test('admin can see all projects', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);
    $this->actingAs($admin);

    $projects = Project::all();

    expect($projects)->toHaveCount(2)
        ->and($projects->pluck('name')->toArray())->toContain('Project One', 'Project Two');
});

test('manager can only see projects in their teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team1);
    $this->actingAs($manager);

    $projects = Project::all();

    expect($projects)->toHaveCount(1)
        ->and($projects->first()->name)->toBe('Project One');
});

test('member can only see projects in their teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team1);
    $this->actingAs($member);

    $projects = Project::all();

    expect($projects)->toHaveCount(1)
        ->and($projects->first()->name)->toBe('Project One');
});

test('user with multiple teams sees all their team projects', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach([$this->team1->id, $this->team2->id]);
    $this->actingAs($member);

    $projects = Project::all();

    expect($projects)->toHaveCount(2);
});

// =========================================================================
// TASK SCOPE TESTS
// =========================================================================

test('admin can see all tasks', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);
    $this->actingAs($admin);

    $tasks = Task::all();

    expect($tasks)->toHaveCount(2);
});

test('manager can see all tasks in their team projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team1);
    $this->actingAs($manager);

    $tasks = Task::all();

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->title)->toBe('Task One');
});

test('member can see all tasks in their team projects', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team1);
    $this->actingAs($member);

    // Members can see all tasks in projects their team has access to (for collaboration)
    $tasks = Task::all();

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->title)->toBe('Task One');
});

test('member cannot see tasks in projects outside their team', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team1);
    $this->actingAs($member);

    // Member in team1 cannot see task2 (belongs to project2 in team2)
    $tasks = Task::all();

    expect($tasks)->toHaveCount(1)
        ->and($tasks->contains($this->task2))->toBeFalse();
});

// =========================================================================
// IDOR PREVENTION TESTS
// =========================================================================

test('member cannot access project outside their teams via direct ID', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team1);
    $this->actingAs($member);

    // Try to access project2 directly (belongs to team2)
    $project = Project::find($this->project2->id);

    expect($project)->toBeNull();
});

test('member cannot access task outside their assignments via direct ID', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team1);
    $this->actingAs($member);

    // Try to access task2 directly (belongs to project in team2)
    $task = Task::find($this->task2->id);

    expect($task)->toBeNull();
});

// =========================================================================
// SCOPE BYPASS FOR ADMIN OPERATIONS
// =========================================================================

test('withoutGlobalScopes returns all records for admin operations', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team1);
    $this->actingAs($member);

    // Normal query respects scope
    $scopedProjects = Project::all();
    expect($scopedProjects)->toHaveCount(1);

    // withoutGlobalScopes bypasses for admin operations
    $allProjects = Project::withoutGlobalScopes()->get();
    expect($allProjects)->toHaveCount(2);
});
