<?php

use App\Models\Role;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    // Create roles
    $this->adminRole = Role::factory()->admin()->create();
    $this->managerRole = Role::factory()->manager()->create();
    $this->memberRole = Role::factory()->create(['name' => 'Member']);

    // Create team
    $this->team = Team::factory()->create();
});

// =========================================================================
// ADMIN TESTS
// =========================================================================

test('admin can view any team', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('view', $this->team))->toBeTrue();
});

test('admin can create teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('create', Team::class))->toBeTrue();
});

test('admin can update teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('update', $this->team))->toBeTrue();
});

test('admin can add members to any team', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('addMember', $this->team))->toBeTrue();
});

test('admin can remove members from teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('removeMember', $this->team))->toBeTrue();
});

test('admin cannot delete teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('delete', $this->team))->toBeFalse();
});

// =========================================================================
// MANAGER TESTS
// =========================================================================

test('manager can view teams they belong to', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('view', $this->team))->toBeTrue();
});

test('manager cannot view teams they do not belong to', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    // Not attached to the team

    expect($manager->can('view', $this->team))->toBeFalse();
});

test('manager can add members to their teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('addMember', $this->team))->toBeTrue();
});

test('manager cannot add members to other teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    // Not in this team

    expect($manager->can('addMember', $this->team))->toBeFalse();
});

test('manager cannot remove members from teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('removeMember', $this->team))->toBeFalse();
});

test('manager cannot create teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    expect($manager->can('create', Team::class))->toBeFalse();
});

test('manager cannot update teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('update', $this->team))->toBeFalse();
});

test('manager cannot delete teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('delete', $this->team))->toBeFalse();
});

// =========================================================================
// MEMBER TESTS
// =========================================================================

test('member can view teams they belong to', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('view', $this->team))->toBeTrue();
});

test('member cannot view teams they do not belong to', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    // Not attached to the team

    expect($member->can('view', $this->team))->toBeFalse();
});

test('member cannot add members to teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('addMember', $this->team))->toBeFalse();
});

test('member cannot create teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    expect($member->can('create', Team::class))->toBeFalse();
});

test('member cannot update teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('update', $this->team))->toBeFalse();
});

test('member cannot delete teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('delete', $this->team))->toBeFalse();
});
