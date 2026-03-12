<?php

use App\Models\Role;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    // Create roles
    $this->adminRole = Role::factory()->admin()->create();
    $this->managerRole = Role::factory()->manager()->create();
    $this->memberRole = Role::factory()->create(['name' => 'Member']);

    // Create teams
    $this->team = Team::factory()->create();
    $this->otherTeam = Team::factory()->create();
});

// =========================================================================
// ADMIN TESTS
// =========================================================================

test('admin can view any user', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->otherTeam);

    expect($admin->can('view', $targetUser))->toBeTrue();
});

test('admin can view users outside their teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);
    $admin->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->otherTeam);

    expect($admin->can('view', $targetUser))->toBeTrue();
});

test('admin can create users', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    expect($admin->can('create', User::class))->toBeTrue();
});

test('admin can update any user', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $targetUser = User::factory()->create();

    expect($admin->can('update', $targetUser))->toBeTrue();
});

test('admin can delete users', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $targetUser = User::factory()->create();

    expect($admin->can('delete', $targetUser))->toBeTrue();
});

// =========================================================================
// MANAGER TESTS
// =========================================================================

test('manager can view users in their teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->team);

    expect($manager->can('view', $targetUser))->toBeTrue();
});

test('manager cannot view users outside their teams', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->otherTeam);

    expect($manager->can('view', $targetUser))->toBeFalse();
});

test('manager can view themselves', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    expect($manager->can('view', $manager))->toBeTrue();
});

test('manager cannot create users', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    expect($manager->can('create', User::class))->toBeFalse();
});

test('manager can update their own profile', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    expect($manager->can('update', $manager))->toBeTrue();
});

test('manager cannot update other users', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);
    $manager->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->team);

    expect($manager->can('update', $targetUser))->toBeFalse();
});

test('manager cannot delete users', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $targetUser = User::factory()->create();

    expect($manager->can('delete', $targetUser))->toBeFalse();
});

// =========================================================================
// MEMBER TESTS
// =========================================================================

test('member can view users in their teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->team);

    expect($member->can('view', $targetUser))->toBeTrue();
});

test('member cannot view users outside their teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->otherTeam);

    expect($member->can('view', $targetUser))->toBeFalse();
});

test('member can view themselves', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    expect($member->can('view', $member))->toBeTrue();
});

test('member can view users in shared teams even if they belong to multiple teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach([$this->team->id, $this->otherTeam->id]);

    expect($member->can('view', $targetUser))->toBeTrue();
});

test('member cannot create users', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    expect($member->can('create', User::class))->toBeFalse();
});

test('member can update their own profile', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    expect($member->can('update', $member))->toBeTrue();
});

test('member cannot update other users', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach($this->team);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->team);

    expect($member->can('update', $targetUser))->toBeFalse();
});

test('member cannot delete users', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $targetUser = User::factory()->create();

    expect($member->can('delete', $targetUser))->toBeFalse();
});

// =========================================================================
// EDGE CASES
// =========================================================================

test('user with no teams cannot view other users', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    // No team attachment

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach($this->team);

    expect($member->can('view', $targetUser))->toBeFalse();
});

test('user can view another user if they share at least one team', function () {
    $thirdTeam = Team::factory()->create();

    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);
    $member->teams()->attach([$this->team->id, $thirdTeam->id]);

    $targetUser = User::factory()->create();
    $targetUser->teams()->attach([$this->otherTeam->id, $thirdTeam->id]);

    // They share $thirdTeam
    expect($member->can('view', $targetUser))->toBeTrue();
});
