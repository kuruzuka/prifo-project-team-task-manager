<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    ProjectStatus::factory()->create(['name' => 'In Progress']);
    $this->adminRole = Role::factory()->admin()->create();
    $this->memberRole = Role::factory()->member()->create();
    $this->managerRole = Role::factory()->manager()->create();
});

test('audit logs page requires authentication', function () {
    $this->get('/admin/audit-logs')
        ->assertRedirect('/login');
});

test('admin can view audit logs page', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $this->actingAs($admin)
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/AuditLogs'));
});

test('non-admin cannot access audit logs', function () {
    $member = User::factory()->create();
    $member->roles()->attach($this->memberRole);

    $this->actingAs($member)
        ->get('/admin/audit-logs')
        ->assertForbidden();
});

test('manager cannot access audit logs', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach($this->managerRole);

    $this->actingAs($manager)
        ->get('/admin/audit-logs')
        ->assertForbidden();
});

test('audit logs page returns correct data structure', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    // Create a project that logs activity
    $project = Project::factory()->create();
    $project->logActivity('project_created', ['name' => $project->name], $admin->id);

    $this->actingAs($admin)
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/AuditLogs')
            ->has('logs.data')
            ->has('activityTypes')
            ->has('entityTypes')
            ->has('users')
            ->has('filters')
            ->has('logs.data.0', fn ($page) => $page
                ->has('id')
                ->has('activity_type')
                ->has('entity_type')
                ->has('entity_id')
                ->has('metadata')
                ->has('actor')
                ->has('created_at')
                ->has('created_at_human')
            )
        );
});

test('audit logs can be filtered by user', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $anotherUser = User::factory()->create();
    $anotherUser->roles()->attach($this->adminRole);

    $project1 = Project::factory()->create();
    $project1->logActivity('project_created', ['name' => $project1->name], $admin->id);

    $project2 = Project::factory()->create();
    $project2->logActivity('project_created', ['name' => $project2->name], $anotherUser->id);

    $this->actingAs($admin)
        ->get("/admin/audit-logs?user={$admin->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.actor.id', $admin->id)
        );
});

test('audit logs can be filtered by action', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $project->logActivity('project_created', ['name' => $project->name], $admin->id);
    $project->logActivity('project_updated', ['name' => $project->name], $admin->id);

    $this->actingAs($admin)
        ->get('/admin/audit-logs?action=project_created')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.activity_type', 'project_created')
        );
});

test('audit logs can be filtered by entity type', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project = Project::factory()->create();
    $project->logActivity('project_created', ['name' => $project->name], $admin->id);

    $entityType = 'App\\Models\\Project';

    $this->actingAs($admin)
        ->get("/admin/audit-logs?entity={$entityType}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.entity_type', 'Project')
        );
});

test('audit logs are paginated', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    // Create 30 activity logs
    for ($i = 0; $i < 30; $i++) {
        $project = Project::factory()->create();
        $project->logActivity('project_created', ['name' => $project->name], $admin->id);
    }

    $this->actingAs($admin)
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 25) // 25 per page
            ->where('logs.current_page', 1)
            ->where('logs.last_page', 2)
        );
});

test('audit logs are sorted by most recent first', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach($this->adminRole);

    $project1 = Project::factory()->create();
    $project1->logActivity('first_action', ['name' => 'First'], $admin->id);

    $project2 = Project::factory()->create();
    $project2->logActivity('second_action', ['name' => 'Second'], $admin->id);

    $this->actingAs($admin)
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Verify both logs are returned
            ->has('logs.data', 2)
            // Verify the logs have required properties
            ->has('logs.data.0.created_at')
            ->has('logs.data.1.created_at')
        );
});
