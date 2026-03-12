<?php

use App\Models\Role;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->adminRole = Role::factory()->admin()->create();
    $this->memberRole = Role::factory()->member()->create();
    $this->managerRole = Role::factory()->manager()->create();
});

test('teams page requires authentication', function () {
    $this->get('/teams')
        ->assertRedirect('/login');
});

test('admin can view teams page', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('teams/Teams'));
});

test('non-admin cannot access teams index', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $this->actingAs($member)
        ->get('/teams')
        ->assertForbidden();
});

test('teams page returns correct data structure', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $team = Team::factory()->create();
    $team->members()->attach($admin->id);

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/Teams')
            ->has('teams')
            ->has('teams.0', fn ($page) => $page
                ->has('id')
                ->has('name')
                ->has('description')
                ->has('members')
                ->has('members_count')
                ->has('overflow_count')
            )
        );
});

test('teams page returns member data with initials', function () {
    $admin = User::factory()->create([
        'first_name' => 'Admin',
        'last_name' => 'User',
    ]);
    $admin->roles()->attach($this->adminRole);

    $member = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $team = Team::factory()->create();
    $team->members()->attach($member->id);

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams.0.members.0', fn ($page) => $page
                ->has('id')
                ->where('name', 'John Doe')
                ->where('initials', 'JD')
                ->has('avatar')
            )
        );
});

test('admin can see all teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    // Create teams the admin is NOT a member of
    Team::factory()->create(['name' => 'Alpha Team']);
    Team::factory()->create(['name' => 'Beta Team']);

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams', 2)
        );
});

test('member can view their teams via user-scoped route', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    // Team the member belongs to
    $memberTeam = Team::factory()->create(['name' => 'My Team']);
    $memberTeam->members()->attach($member->id);

    // Team the member does NOT belong to
    Team::factory()->create(['name' => 'Other Team']);

    $this->actingAs($member)
        ->get("/users/{$member->id}/teams")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams', 1)
            ->where('teams.0.name', 'My Team')
        );
});

test('teams page limits members to 6 and calculates overflow', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $team = Team::factory()->create();

    // Add 8 members to the team
    $members = User::factory(8)->create();
    $team->members()->attach($members->pluck('id'));

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams.0.members', 6)
            ->where('teams.0.members_count', 8)
            ->where('teams.0.overflow_count', 2)
        );
});

test('teams page shows zero overflow when 6 or fewer members', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $team = Team::factory()->create();

    // Add exactly 6 members
    $members = User::factory(6)->create();
    $team->members()->attach($members->pluck('id'));

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams.0.members', 6)
            ->where('teams.0.members_count', 6)
            ->where('teams.0.overflow_count', 0)
        );
});

test('teams page handles teams with no members', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    Team::factory()->create();

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams.0.members', 0)
            ->where('teams.0.members_count', 0)
            ->where('teams.0.overflow_count', 0)
        );
});

test('teams are ordered by name', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    Team::factory()->create(['name' => 'Zebra Team']);
    Team::factory()->create(['name' => 'Alpha Team']);
    Team::factory()->create(['name' => 'Beta Team']);

    $this->actingAs($admin)
        ->get('/teams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('teams.0.name', 'Alpha Team')
            ->where('teams.1.name', 'Beta Team')
            ->where('teams.2.name', 'Zebra Team')
        );
});

// ===== USER-SCOPED TEAM ROUTES =====

test('user teams route requires authentication', function () {
    $user = User::factory()->create();

    $this->get("/users/{$user->id}/teams")
        ->assertRedirect('/login');
});

test('admin can view any user teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $targetUser = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Wonder']);
    $targetUser->roles()->attach($this->memberRole);

    // Create a team the target user belongs to
    $memberTeam = Team::factory()->create(['name' => 'User Team']);
    $memberTeam->members()->attach($targetUser->id);

    // Create a team the target user does NOT belong to
    Team::factory()->create(['name' => 'Other Team']);

    $this->actingAs($admin)
        ->get("/users/{$targetUser->id}/teams")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/Teams')
            ->has('teams', 1)
            ->where('teams.0.name', 'User Team')
            ->has('user', fn ($page) => $page
                ->where('id', $targetUser->id)
                ->where('name', 'Alice Wonder')
            )
        );
});

test('manager can view teams of users who share a team', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $teamMember = User::factory()->create(['first_name' => 'Team', 'last_name' => 'Member']);
    $teamMember->roles()->attach($this->memberRole);

    // Both in same team
    $sharedTeam = Team::factory()->create(['name' => 'Shared Team']);
    $manager->teams()->attach($sharedTeam);
    $teamMember->teams()->attach($sharedTeam);

    $this->actingAs($manager)
        ->get("/users/{$teamMember->id}/teams")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/Teams')
            ->has('teams', 1)
            ->where('teams.0.name', 'Shared Team')
        );
});

test('user can view their own teams', function () {
    $user = User::factory()->create(['first_name' => 'Self', 'last_name' => 'User']);
    $user->roles()->attach($this->memberRole);

    $team = Team::factory()->create(['name' => 'My Team']);
    $team->members()->attach($user->id);

    $this->actingAs($user)
        ->get("/users/{$user->id}/teams")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/Teams')
            ->has('teams', 1)
            ->where('teams.0.name', 'My Team')
        );
});

test('member cannot view teams of user outside their teams', function () {
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
        ->get("/users/{$otherUser->id}/teams")
        ->assertForbidden();
});

test('user teams route returns empty when user has no teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $targetUser = User::factory()->create();
    $targetUser->roles()->attach($this->memberRole);

    // Create teams that the user is NOT a member of
    Team::factory(3)->create();

    $this->actingAs($admin)
        ->get("/users/{$targetUser->id}/teams")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams', 0)
        );
});
