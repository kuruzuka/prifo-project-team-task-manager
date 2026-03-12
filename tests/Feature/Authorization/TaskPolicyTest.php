<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    // Create roles
    $this->adminRole = Role::factory()->admin()->create();
    $this->managerRole = Role::factory()->manager()->create();
    $this->memberRole = Role::factory()->create(['name' => 'Member']);

    // Create team, project, and task
    $this->team = Team::factory()->create();
    $this->project = Project::factory()->create();
    $this->project->teams()->attach($this->team);
    $this->task = Task::factory()->forProject($this->project)->create();
});

// =========================================================================
// ADMIN TESTS
// =========================================================================

test('admin can view any task', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('view', $this->task))->toBeTrue();
});

test('admin can update any task', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('update', $this->task))->toBeTrue();
});

test('admin can assign users to tasks', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('assign', $this->task))->toBeTrue();
});

test('admin can delete tasks', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('delete', $this->task))->toBeTrue();
});

// =========================================================================
// MANAGER TESTS
// =========================================================================

test('manager can view tasks in their projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('view', $this->task))->toBeTrue();
});

test('manager can update tasks in projects they manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);
    $this->project->update(['manager_id' => $manager->id]);

    expect($manager->can('update', $this->task))->toBeTrue();
});

test('manager cannot update tasks in projects they do not manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);
    // Not the manager of the project

    expect($manager->can('update', $this->task))->toBeFalse();
});

test('manager can assign users to tasks in their projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $this->project->update(['manager_id' => $manager->id]);

    expect($manager->can('assign', $this->task))->toBeTrue();
});

test('manager cannot assign users to tasks outside their projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    // Not the manager

    expect($manager->can('assign', $this->task))->toBeFalse();
});

test('manager can create tasks in projects they manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $this->project->update(['manager_id' => $manager->id]);
    $manager->refresh()->loadMissing('managedProjects');

    expect($manager->can('create', [Task::class, $this->project]))->toBeTrue();
});

test('manager can delete tasks in projects they manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $this->project->update(['manager_id' => $manager->id]);

    expect($manager->can('delete', $this->task))->toBeTrue();
});

test('manager cannot delete tasks outside their projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    // Not the manager of this project

    expect($manager->can('delete', $this->task))->toBeFalse();
});

// =========================================================================
// MEMBER TESTS
// =========================================================================

test('member can view tasks assigned to them', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);
    $this->task->assignedUsers()->attach($member->id, [
        'assigned_by' => $member->id,
        'assigned_date' => now(),
    ]);

    expect($member->can('view', $this->task))->toBeTrue();
});

test('member can view tasks in their team projects even when not assigned', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);
    // Not assigned to the task, but in the same team - can view for collaboration

    expect($member->can('view', $this->task))->toBeTrue();
});

test('member can update progress on assigned tasks', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);
    $this->task->assignedUsers()->attach($member->id, [
        'assigned_by' => $member->id,
        'assigned_date' => now(),
    ]);

    expect($member->can('updateProgress', $this->task))->toBeTrue();
});

test('member cannot update tasks not assigned to them', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);
    // Not assigned

    expect($member->can('update', $this->task))->toBeFalse();
});

test('member cannot create tasks', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    expect($member->can('create', Task::class))->toBeFalse();
});

test('member cannot assign tasks', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('assign', $this->task))->toBeFalse();
});

test('member cannot delete tasks', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);
    $this->task->assignedUsers()->attach($member->id, [
        'assigned_by' => $member->id,
        'assigned_date' => now(),
    ]);

    expect($member->can('delete', $this->task))->toBeFalse();
});
