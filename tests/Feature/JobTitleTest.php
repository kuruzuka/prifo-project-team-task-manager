<?php

use App\Models\JobTitle;
use App\Models\User;

test('job title can be created with factory', function () {
    $jobTitle = JobTitle::factory()->create([
        'name' => 'Software Engineer',
        'description' => 'Develops software applications.',
    ]);

    expect($jobTitle)
        ->name->toBe('Software Engineer')
        ->description->toBe('Develops software applications.');
});

test('job title has many users relationship', function () {
    $jobTitle = JobTitle::factory()->create();
    $users = User::factory(3)->create(['job_title_id' => $jobTitle->id]);

    expect($jobTitle->users)->toHaveCount(3);
    expect($jobTitle->users->first())->toBeInstanceOf(User::class);
});

test('user belongs to a job title', function () {
    $jobTitle = JobTitle::factory()->create(['name' => 'Lead Developer']);
    $user = User::factory()->create(['job_title_id' => $jobTitle->id]);

    expect($user->jobTitle)
        ->toBeInstanceOf(JobTitle::class)
        ->name->toBe('Lead Developer');
});

test('user can exist without a job title', function () {
    $user = User::factory()->create(['job_title_id' => null]);

    expect($user->jobTitle)->toBeNull();
});

test('job title name is unique', function () {
    JobTitle::factory()->create(['name' => 'Unique Title']);

    expect(fn () => JobTitle::factory()->create(['name' => 'Unique Title']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('deleting job title sets user job_title_id to null', function () {
    $jobTitle = JobTitle::factory()->create();
    $user = User::factory()->create(['job_title_id' => $jobTitle->id]);

    expect($user->job_title_id)->toBe($jobTitle->id);

    $jobTitle->delete();
    $user->refresh();

    expect($user->job_title_id)->toBeNull();
});

test('user factory assigns random job title when titles exist', function () {
    // Create some job titles first
    JobTitle::factory(3)->create();

    $user = User::factory()->create();

    expect($user->job_title_id)->not->toBeNull();
    expect($user->jobTitle)->toBeInstanceOf(JobTitle::class);
});

test('user factory withJobTitle state sets specific job title', function () {
    $jobTitle = JobTitle::factory()->create(['name' => 'QA Engineer']);

    $user = User::factory()->withJobTitle($jobTitle)->create();

    expect($user->job_title_id)->toBe($jobTitle->id);
    expect($user->jobTitle->name)->toBe('QA Engineer');
});
