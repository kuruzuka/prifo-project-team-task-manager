<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    // Ensure roles exist
    Role::firstOrCreate(['name' => 'Admin']);
    Role::firstOrCreate(['name' => 'Manager']);
    Role::firstOrCreate(['name' => 'Member']);

    // Ensure project statuses exist
    ProjectStatus::firstOrCreate(['id' => 1, 'name' => 'Planning']);
});

describe('project creation', function () {
    test('guests cannot create projects', function () {
        $response = $this->post(route('projects.store'), [
            'name' => 'Test Project',
        ]);

        $response->assertRedirect(route('login'));
    });

    test('regular members cannot create projects', function () {
        $member = User::factory()->create();
        $member->roles()->attach(Role::where('name', 'Member')->first());

        $response = $this->actingAs($member)->post(route('projects.store'), [
            'name' => 'Test Project',
        ]);

        $response->assertForbidden();
    });

    test('managers cannot create projects', function () {
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('name', 'Manager')->first());

        $response = $this->actingAs($manager)->post(route('projects.store'), [
            'name' => 'Test Project',
        ]);

        $response->assertForbidden();
    });

    test('admins can create projects', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'Admin')->first());

        $response = $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Test Project',
            'description' => 'Test Description',
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect(route('projects'));
        $response->assertSessionHas('success');

        expect(Project::where('name', 'Test Project')->exists())->toBeTrue();
    });

    test('project creation sets correct defaults', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'Admin')->first());

        $dueDate = now()->addMonth()->toDateString();

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Test Project',
            'due_date' => $dueDate,
        ]);

        $project = Project::where('name', 'Test Project')->first();

        expect($project)->not->toBeNull()
            ->and($project->manager_id)->toBe($admin->id)
            ->and($project->status_id)->toBe(1) // Planning
            ->and($project->start_date->toDateString())->toBe(now()->toDateString())
            ->and($project->end_date->toDateString())->toBe($dueDate);
    });

    test('project name is required', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'Admin')->first());

        $response = $this->actingAs($admin)->post(route('projects.store'), [
            'description' => 'No name provided',
        ]);

        $response->assertSessionHasErrors('name');
    });

    test('project due date must be today or later', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'Admin')->first());

        $response = $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Test Project',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('due_date');
    });
});
