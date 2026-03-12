<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    // Create roles
    $this->adminRole = Role::factory()->admin()->create();
    $this->managerRole = Role::factory()->manager()->create();
    $this->memberRole = Role::factory()->create(['name' => 'Member']);

    // Create team and project
    $this->team = Team::factory()->create();
    $this->project = Project::factory()->create();
    $this->project->teams()->attach($this->team);
});

// =========================================================================
// ADMIN TESTS
// =========================================================================

test('admin can view any project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('view', $this->project))->toBeTrue();
});

test('admin can update any project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('update', $this->project))->toBeTrue();
});

test('admin cannot delete projects', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('delete', $this->project))->toBeFalse();
});

test('admin cannot force delete projects', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('forceDelete', $this->project))->toBeFalse();
});

// =========================================================================
// MANAGER TESTS
// =========================================================================

test('manager can view projects in their teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('view', $this->project))->toBeTrue();
});

test('manager cannot view projects outside their teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    // Not attached to the team

    expect($manager->can('view', $this->project))->toBeFalse();
});

test('manager can update projects they manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);
    $this->project->update(['manager_id' => $manager->id]);

    expect($manager->can('update', $this->project))->toBeTrue();
});

test('manager cannot update projects they do not manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);
    // Not the manager of this project

    expect($manager->can('update', $this->project))->toBeFalse();
});

test('manager cannot create projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    expect($manager->can('create', Project::class))->toBeFalse();
});

test('manager cannot delete projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);
    $this->project->update(['manager_id' => $manager->id]);

    expect($manager->can('delete', $this->project))->toBeFalse();
});

// =========================================================================
// MEMBER TESTS
// =========================================================================

test('member can view projects in their teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('view', $this->project))->toBeTrue();
});

test('member cannot view projects outside their teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    // Not attached to the team

    expect($member->can('view', $this->project))->toBeFalse();
});

test('member cannot update projects', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('update', $this->project))->toBeFalse();
});

test('member cannot create projects', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    expect($member->can('create', Project::class))->toBeFalse();
});

test('member cannot delete projects', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('delete', $this->project))->toBeFalse();
});

// =========================================================================
// SPECIAL POLICY METHODS
// =========================================================================

test('admin can assign manager to project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('assignManager', $this->project))->toBeTrue();
});

test('manager cannot assign manager to project', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('assignManager', $this->project))->toBeFalse();
});

test('admin can manage tasks in any project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('manageTasks', $this->project))->toBeTrue();
});

test('manager can manage tasks in their projects', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $this->project->update(['manager_id' => $manager->id]);

    expect($manager->can('manageTasks', $this->project))->toBeTrue();
});

test('manager cannot manage tasks in projects they do not manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    // Not the manager

    expect($manager->can('manageTasks', $this->project))->toBeFalse();
});
