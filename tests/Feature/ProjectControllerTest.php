<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    // Create statuses
    ProjectStatus::factory()->create(['name' => 'In Progress']);
    TaskStatus::factory()->create(['name' => 'To Do']);
    $this->adminRole = Role::factory()->admin()->create();
    $this->memberRole = Role::factory()->member()->create();
    $this->managerRole = Role::factory()->manager()->create();
});

test('projects page requires authentication', function () {
    $this->get('/projects')
        ->assertRedirect('/login');
});

test('admin can view projects page', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('projects/Projects'));
});

test('non-admin cannot access projects index', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $this->actingAs($member)
        ->get('/projects')
        ->assertForbidden();
});

test('projects page returns correct data structure', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Projects')
            ->has('projects')
            ->has('projects.0', fn ($page) => $page
                ->has('id')
                ->has('name')
                ->has('description')
                ->has('status')
                ->has('progress')
                ->has('tasks_count')
                ->has('team_members_count')
                ->has('manager')
                ->has('deadline')
                ->has('start_date')
            )
        );
});

test('projects page calculates tasks_count correctly', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    Task::factory(5)->forProject($project)->create();

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.tasks_count', 5)
        );
});

test('projects page calculates progress as average of task progress', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    // Create tasks with known progress values: 20, 40, 60 = avg 40
    Task::factory()->forProject($project)->create(['progress' => 20]);
    Task::factory()->forProject($project)->create(['progress' => 40]);
    Task::factory()->forProject($project)->create(['progress' => 60]);

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.progress', 40)
        );
});

test('projects page counts team members correctly', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $team = Team::factory()->create();

    // Add 3 members to the team
    $members = User::factory(3)->create();
    $team->members()->attach($members->pluck('id'));

    // Assign team to project
    $project->teams()->attach($team->id);

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.team_members_count', 3)
        );
});

test('projects page handles projects without tasks', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    Project::factory()->create();

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.tasks_count', 0)
            ->where('projects.0.progress', 0)
        );
});

test('projects page handles projects without teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    Project::factory()->create();

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.team_members_count', 0)
        );
});

// ===== PROJECT MANAGER TESTS =====

test('projects page returns manager data', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $manager = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    Project::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.0.manager', fn ($page) => $page
                ->where('id', $manager->id)
                ->where('name', 'John Doe')
                ->where('initials', 'JD')
            )
        );
});

test('projects page handles projects without manager', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    Project::factory()->create(['manager_id' => null]);

    $this->actingAs($admin)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.manager', null)
        );
});

// ===== MEMBER CAN ONLY SEE TEAM PROJECTS =====

test('member can view their team projects via user-scoped route', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    // Project in member's team
    $teamProject = Project::factory()->create(['name' => 'Team Project']);
    $teamProject->teams()->attach($team);

    // Project NOT in member's team
    Project::factory()->create(['name' => 'Other Project']);

    $this->actingAs($member)
        ->get("/users/{$member->id}/projects")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'Team Project')
        );
});

// ===== USER-SCOPED PROJECT ROUTES =====

test('user projects route requires authentication', function () {
    $user = User::factory()->create();

    $this->get("/users/{$user->id}/projects")
        ->assertRedirect('/login');
});

test('admin can view any user projects', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $manager = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Manager']);
    $manager->roles()->attach($this->managerRole);

    // Create a project managed by the target user
    Project::factory()->create([
        'name' => 'Managed Project',
        'manager_id' => $manager->id,
    ]);

    $this->actingAs($admin)
        ->get("/users/{$manager->id}/projects")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Projects')
            ->has('projects', 1)
            ->where('projects.0.name', 'Managed Project')
            ->has('user', fn ($page) => $page
                ->where('id', $manager->id)
                ->where('name', 'Jane Manager')
            )
        );
});

test('manager can view projects of users in their team', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $teamMember = User::factory()->create(['first_name' => 'Team', 'last_name' => 'Member']);
    $teamMember->roles()->attach($this->memberRole);

    // Both in same team
    $team = Team::factory()->create();
    $manager->teams()->attach($team);
    $teamMember->teams()->attach($team);

    // Create a project assigned to the team
    $project = Project::factory()->create([
        'name' => 'Team Project',
        'manager_id' => $manager->id,
    ]);
    $project->teams()->attach($team);

    $this->actingAs($manager)
        ->get("/users/{$teamMember->id}/projects")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Projects')
            ->has('projects', 1)
            ->where('projects.0.name', 'Team Project')
        );
});

test('user can view their own projects', function () {
    $user = User::factory()->create(['first_name' => 'Self', 'last_name' => 'User']);
    $user->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $user->teams()->attach($team);

    $project = Project::factory()->create(['name' => 'My Team Project']);
    $project->teams()->attach($team);

    $this->actingAs($user)
        ->get("/users/{$user->id}/projects")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Projects')
            ->has('projects', 1)
            ->where('projects.0.name', 'My Team Project')
        );
});

test('member cannot view projects of user outside their team', function () {
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
        ->get("/users/{$otherUser->id}/projects")
        ->assertForbidden();
});

test('user projects route returns projects where user is team member', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $teamMember = User::factory()->create();
    $teamMember->roles()->attach($this->memberRole);

    $otherUser = User::factory()->create();
    $otherUser->roles()->attach($this->memberRole);

    // Create a team and add the user
    $team = Team::factory()->create();
    $teamMember->teams()->attach($team);

    // Create a project assigned to that team
    $project = Project::factory()->create([
        'name' => 'Team Project',
        'manager_id' => $otherUser->id,
    ]);
    $project->teams()->attach($team);

    // Create a project NOT assigned to any team the user is in
    Project::factory()->create([
        'name' => 'Unrelated Project',
        'manager_id' => $otherUser->id,
    ]);

    $this->actingAs($admin)
        ->get("/users/{$teamMember->id}/projects")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'Team Project')
        );
});

// ===== PROJECT SHOW TESTS =====

test('project show page requires authentication', function () {
    $project = Project::factory()->create();

    $this->get("/projects/{$project->id}")
        ->assertRedirect('/login');
});

test('admin can view any project details', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $manager = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $project = Project::factory()->create([
        'name' => 'Test Project',
        'description' => 'Project description',
        'manager_id' => $manager->id,
    ]);

    $this->actingAs($admin)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Show')
            ->has('project', fn ($page) => $page
                ->where('id', $project->id)
                ->where('name', 'Test Project')
                ->where('description', 'Project description')
                ->has('manager', fn ($page) => $page
                    ->where('id', $manager->id)
                    ->where('name', 'John Doe')
                )
                ->etc()
            )
            ->has('tasks')
        );
});

test('project show page returns tasks belonging to project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $todoStatus = TaskStatus::where('name', 'To Do')->first();

    Task::factory()->forProject($project)->withStatus($todoStatus)->create([
        'title' => 'Task One',
        'priority' => 'high',
    ]);
    Task::factory()->forProject($project)->withStatus($todoStatus)->create([
        'title' => 'Task Two',
        'priority' => 'low',
    ]);

    // Task for another project should not appear
    $otherProject = Project::factory()->create();
    Task::factory()->forProject($otherProject)->create(['title' => 'Other Task']);

    $this->actingAs($admin)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 2)
            ->has('tasks.data.0', fn ($page) => $page
                ->has('id')
                ->has('title')
                ->has('description')
                ->has('status')
                ->has('status_id')
                ->has('priority')
                ->has('due_date')
                ->has('due_date_raw')
                ->has('assignee')
                ->has('created_by')
                ->has('can', fn ($page) => $page
                    ->has('view')
                    ->has('delete')
                    ->has('edit')
                )
            )
        );
});

test('member can view project in their team', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    $project = Project::factory()->create(['name' => 'Team Project']);
    $project->teams()->attach($team);

    $this->actingAs($member)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Show')
            ->where('project.name', 'Team Project')
        );
});

test('member cannot view project outside their team', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    // Project not in member's team
    $project = Project::factory()->create(['name' => 'Other Project']);

    // TeamProjectScope hides projects outside user's teams (returns 404, not 403)
    $this->actingAs($member)
        ->get("/projects/{$project->id}")
        ->assertNotFound();
});

test('project show handles project without manager', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create(['manager_id' => null]);

    $this->actingAs($admin)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('project.manager', null)
        );
});

test('project show handles project without tasks', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 0)
        );
});

test('member can view tasks in their team project', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    $project = Project::factory()->create(['name' => 'Team Project']);
    $project->teams()->attach($team);

    $todoStatus = TaskStatus::where('name', 'To Do')->first();
    Task::factory()->forProject($project)->withStatus($todoStatus)->create([
        'title' => 'Team Task',
    ]);

    $this->actingAs($member)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->has('tasks.data.0.can', fn ($page) => $page
                ->where('view', true)  // Members CAN view tasks in their team's projects
                ->where('delete', false) // And cannot delete
                ->has('edit')
            )
        );
});

// ============================================================================
// Project Update Tests
// ============================================================================

test('admin can update project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create([
        'name' => 'Original Name',
        'description' => 'Original Description',
    ]);

    $status = ProjectStatus::first();

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'status_id' => $status->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $project->refresh();
    expect($project->name)->toBe('Updated Name');
    expect($project->description)->toBe('Updated Description');
});

test('manager can update their own project', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $team = Team::factory()->create();
    $manager->teams()->attach($team);

    $project = Project::factory()->create([
        'name' => 'Original Name',
        'manager_id' => $manager->id,
    ]);
    $project->teams()->attach($team);

    $status = ProjectStatus::first();

    $this->actingAs($manager)
        ->patch("/projects/{$project->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'status_id' => $status->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $project->refresh();
    expect($project->name)->toBe('Updated Name');
});

test('manager cannot update project they do not manage', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $team = Team::factory()->create();
    $manager->teams()->attach($team);

    $otherManager = User::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Original Name',
        'manager_id' => $otherManager->id,
    ]);
    // Manager has team access but doesn't manage the project
    $project->teams()->attach($team);

    $status = ProjectStatus::first();

    $this->actingAs($manager)
        ->patch("/projects/{$project->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'status_id' => $status->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
        ])
        ->assertForbidden();

    $project->refresh();
    expect($project->name)->toBe('Original Name');
});

test('member cannot update project', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    $project = Project::factory()->create(['name' => 'Original Name']);
    $project->teams()->attach($team);

    $status = ProjectStatus::first();

    $this->actingAs($member)
        ->patch("/projects/{$project->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'status_id' => $status->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
        ])
        ->assertForbidden();

    $project->refresh();
    expect($project->name)->toBe('Original Name');
});

test('project update validates required fields', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}", [
            'name' => '',
            'status_id' => '',
            'start_date' => '',
        ])
        ->assertSessionHasErrors(['name', 'status_id', 'start_date']);
});

test('project update validates start date is after or equal today', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $status = ProjectStatus::first();

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}", [
            'name' => 'Test Project',
            'status_id' => $status->id,
            'start_date' => now()->subDay()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['start_date']);
});

test('project update validates end date is after start date', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $status = ProjectStatus::first();

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}", [
            'name' => 'Test Project',
            'status_id' => $status->id,
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['end_date']);
});

test('project show page includes canUpdate flag', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUpdate', true)
        );
});

test('project show page includes canUpdate flag for manager', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $project = Project::factory()->create([
        'manager_id' => $manager->id,
    ]);

    $team = Team::factory()->create();
    $manager->teams()->attach($team);
    $project->teams()->attach($team);

    $this->actingAs($manager)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUpdate', true)
        );
});

test('project show page includes canUpdate flag as false for member', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    $project = Project::factory()->create();
    $project->teams()->attach($team);

    $this->actingAs($member)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUpdate', false)
        );
});

// ============================================================================
// Project Team Update Tests
// ============================================================================

test('admin can update project teams', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $team1 = Team::factory()->create(['name' => 'Team One']);
    $team2 = Team::factory()->create(['name' => 'Team Two']);

    // Initially no teams assigned
    expect($project->teams()->count())->toBe(0);

    // Assign first team
    $this->actingAs($admin)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => [$team1->id],
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->teams()->count())->toBe(1);
    expect($project->teams->first()->id)->toBe($team1->id);

    // Add second team
    $this->actingAs($admin)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => [$team1->id, $team2->id],
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->teams()->count())->toBe(2);
});

test('admin can remove teams from project', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    // Need to act as admin before attaching teams due to TeamMembershipScope
    $this->actingAs($admin);

    $project = Project::factory()->create();
    $team1 = Team::factory()->create(['name' => 'Team One']);
    $team2 = Team::factory()->create(['name' => 'Team Two']);

    // Initially assign both teams
    $project->teams()->attach([$team1->id, $team2->id]);
    $project->refresh();
    expect($project->teams()->count())->toBe(2);

    // Remove one team
    $this->actingAs($admin)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => [$team1->id],
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->teams()->count())->toBe(1);
    expect($project->teams->first()->id)->toBe($team1->id);
});

test('manager can update teams for their project', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $team = Team::factory()->create();
    $manager->teams()->attach($team);

    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->teams()->attach($team);

    $anotherTeam = Team::factory()->create(['name' => 'Another Team']);
    $manager->teams()->attach($anotherTeam);

    $this->actingAs($manager)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => [$team->id, $anotherTeam->id],
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->teams()->count())->toBe(2);
});

test('member cannot update project teams', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $team = Team::factory()->create();
    $member->teams()->attach($team);

    // Project NOT managed by this member
    $otherManager = User::factory()->create();
    $otherManager->roles()->attach($this->managerRole);
    
    $project = Project::factory()->create(['manager_id' => $otherManager->id]);
    $project->teams()->attach($team);

    $newTeam = Team::factory()->create();

    // Member tries to update teams - should be forbidden by policy
    $this->actingAs($member)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => [$team->id, $newTeam->id],
        ])
        ->assertStatus(403);
});

test('updateTeams requires at least one team', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => [],
        ])
        ->assertSessionHasErrors(['team_ids']);
});

test('updateTeams validates team_ids are integers', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => ['invalid', 'values'],
        ])
        ->assertSessionHasErrors(['team_ids.0', 'team_ids.1']);
});

test('updateTeams validates team_ids exist', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}/teams", [
            'team_ids' => [99999],
        ])
        ->assertSessionHasErrors(['team_ids.0']);
});
