<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    private const MAX_VISIBLE_MEMBERS = 6;

    /**
     * Display the teams listing page.
     *
     * Query optimization:
     * 1. withCount('members') - Adds members_count via a single correlated subquery
     * 2. with() constrained eager load - Limits members to 6 per team using Laravel 12's
     *    native limit support on eager loads (avoids loading all members)
     * 3. Select only required fields from users (id, first_name, last_name)
     * 4. Final transformation computes overflow and formats member data
     *
     * Total queries: 3 (teams + members count subquery + limited members eager load)
     *
     * Authorization: viewAny policy check + TeamMembershipScope filters data by role:
     * - Admins see all teams
     * - Managers/Members see only teams they belong to
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Team::class);

        $teams = Team::query()
            ->select(['id', 'name', 'description'])
            ->withCount('members')
            ->with([
                'members' => fn ($query) => $query
                    ->select('users.id', 'users.first_name', 'users.last_name')
                    ->limit(self::MAX_VISIBLE_MEMBERS),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $team->members->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->first_name . ' ' . $member->last_name,
                    'initials' => strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)),
                    'avatar' => null, // Placeholder for future avatar URL
                ]),
                'members_count' => $team->members_count,
                'overflow_count' => max(0, $team->members_count - self::MAX_VISIBLE_MEMBERS),
            ]);

        return Inertia::render('teams/Teams', [
            'teams' => $teams,
        ]);
    }

    /**
     * Display teams that a specific user belongs to.
     *
     * Authorization:
     * - Admins can view any user's teams
     * - Managers can view teams of users who share a team with them
     * - Users can view their own teams
     *
     * Filters teams to only show those where the given user is a member.
     */
    public function forUser(User $user, Request $request): Response
    {
        $viewer = $request->user();

        // Authorization: Check if viewer can see this user's teams
        $canViewUserTeams = $viewer->isAdmin()
            || $viewer->id === $user->id
            || ($viewer->isManager() && $this->sharesTeam($viewer, $user));

        if (! $canViewUserTeams) {
            abort(403, 'You do not have permission to view this user\'s teams.');
        }

        $teams = Team::query()
            ->select(['id', 'name', 'description'])
            ->whereHas('members', fn ($query) => $query->where('users.id', $user->id))
            ->withCount('members')
            ->with([
                'members' => fn ($query) => $query
                    ->select('users.id', 'users.first_name', 'users.last_name')
                    ->limit(self::MAX_VISIBLE_MEMBERS),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $team->members->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->first_name . ' ' . $member->last_name,
                    'initials' => strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)),
                    'avatar' => null,
                ]),
                'members_count' => $team->members_count,
                'overflow_count' => max(0, $team->members_count - self::MAX_VISIBLE_MEMBERS),
            ]);

        return Inertia::render('teams/Teams', [
            'teams' => $teams,
            'user' => [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
            ],
        ]);
    }

    /**
     * Check if two users share at least one team.
     */
    private function sharesTeam(User $viewer, User $target): bool
    {
        $viewerTeamIds = $viewer->teams()->pluck('teams.id');

        return $target->teams()->whereIn('teams.id', $viewerTeamIds)->exists();
    }

    /**
     * Display the team details page.
     *
     * Authorization: Uses TeamPolicy@view which checks team membership.
     *
     * Query optimizations:
     * 1. Eager load members with job title and roles
     * 2. Eager load projects with status and manager
     * 3. Paginate members for large teams
     */
    public function show(Team $team, Request $request): Response
    {
        Gate::authorize('view', $team);

        $user = $request->user();
        $backUrl = $this->resolveTeamListUrl();

        // Load members with their job titles and roles
        $members = $team->members()
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.job_title_id',
            ])
            ->with([
                'jobTitle:id,name',
                'roles:id,name',
            ])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->paginate(10, ['*'], 'members_page')
            ->withQueryString()
            ->through(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->first_name . ' ' . $member->last_name,
                'initials' => strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)),
                'email' => $member->email,
                'job_title' => $member->jobTitle?->name,
                'role' => $member->roles->first()?->name ?? 'Member',
                'avatar' => null,
            ]);

        // Load projects assigned to this team
        $projects = $team->projects()
            ->select([
                'projects.id',
                'projects.name',
                'projects.description',
                'projects.status_id',
                'projects.manager_id',
                'projects.end_date',
            ])
            ->withCount('tasks')
            ->withAvg('tasks', 'progress')
            ->with([
                'status:id,name',
                'manager:id,first_name,last_name',
            ])
            ->orderBy('projects.created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status?->name,
                'progress' => (int) round($project->tasks_avg_progress ?? 0),
                'tasks_count' => $project->tasks_count,
                'manager' => $project->manager ? [
                    'id' => $project->manager->id,
                    'name' => $project->manager->first_name . ' ' . $project->manager->last_name,
                    'initials' => strtoupper(substr($project->manager->first_name, 0, 1) . substr($project->manager->last_name, 0, 1)),
                ] : null,
                'deadline' => $project->end_date?->format('F j, Y'),
            ]);

        // Get users not in this team (for add member dialog)
        $availableUsers = [];
        if (Gate::allows('addMember', $team)) {
            $availableUsers = User::query()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->whereDoesntHave('teams', fn ($query) => $query->where('teams.id', $team->id))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->limit(100)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->first_name . ' ' . $u->last_name,
                    'email' => $u->email,
                ]);
        }

        return Inertia::render('teams/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
            ],
            'members' => $members,
            'projects' => $projects,
            'availableUsers' => $availableUsers,
            'backUrl' => $backUrl,
            'permissions' => [
                'canAddMember' => Gate::allows('addMember', $team),
                'canRemoveMember' => Gate::allows('removeMember', $team),
            ],
        ]);
    }

    /**
     * Add a member to the team.
     *
     * Authorization: Uses TeamPolicy@addMember
     * - Admins can add to any team
     * - Managers can add to teams they belong to
     */
    public function addMember(Team $team, Request $request): RedirectResponse
    {
        Gate::authorize('addMember', $team);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        // Check if user is already in the team
        if ($team->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['user_id' => 'This user is already a member of this team.']);
        }

        $team->members()->attach($user->id);

        return back()->with('success', "{$user->first_name} {$user->last_name} has been added to the team.");
    }

    /**
     * Remove a member from the team.
     *
     * Authorization: Uses TeamPolicy@removeMember (Admin only)
     */
    public function removeMember(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        // Check if user is actually in the team
        if (! $team->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['user' => 'This user is not a member of this team.']);
        }

        $team->members()->detach($user->id);

        return back()->with('success', "{$user->first_name} {$user->last_name} has been removed from the team.");
    }

    /**
     * Resolve the appropriate URL to navigate back to the teams list.
     * Falls back to current user's teams if coming from an invalid referrer.
     */
    private function resolveTeamListUrl(): string
    {
        $referer = request()->headers->get('referer');
        $user = request()->user();

        if ($referer) {
            // Check if coming from admin teams list
            if (str_contains($referer, '/teams') && ! str_contains($referer, '/users/')) {
                return route('teams');
            }

            // Check if coming from a user's teams page
            if (preg_match('/\/users\/(\d+)\/teams/', $referer, $matches)) {
                return route('users.teams', ['user' => $matches[1]]);
            }
        }

        // Default to current user's teams
        return route('users.teams', ['user' => $user->id]);
    }
}
