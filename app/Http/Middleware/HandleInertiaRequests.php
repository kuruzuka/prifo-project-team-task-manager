<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'permissions' => fn () => $this->getUserPermissions($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Get the authenticated user's permissions for frontend rendering.
     *
     * Security Note: These permissions are for UI rendering only.
     * All actual authorization is enforced server-side via policies.
     *
     * @return array<string, mixed>|null
     */
    protected function getUserPermissions(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        // Eager load roles and teams to avoid N+1 queries
        $user->loadMissing(['roles', 'teams', 'managedProjects']);

        return [
            // Role-based permissions
            'isAdmin' => $user->isAdmin(),
            'isManager' => $user->isManager(),
            'isMember' => $user->isMember(),
            'roles' => $user->roles->pluck('name')->toArray(),

            // Team context
            'teamIds' => $user->getTeamIds()->toArray(),

            // Project management context
            'managedProjectIds' => $user->managedProjects->pluck('id')->toArray(),

            // Ability checks derived from policies
            // These control what users CAN do (actions)
            'can' => [
                'manageTeams' => $user->isAdmin(),
                'createProjects' => $user->isAdmin(),
                'createTasks' => $user->isAdmin() || ($user->isManager() && $user->managedProjects->isNotEmpty()),
                'inviteMembers' => $user->isAdmin() || $user->isManager(),
                'assignRoles' => $user->isAdmin(),
                'viewActivityLogs' => $user->isAdmin(),
            ],

            // Navigation visibility permissions
            // These control what users SEE in navigation
            // Based on whether they have global vs scoped access
            'nav' => $this->getNavigationPermissions($user),
        ];
    }

    /**
     * Get navigation visibility permissions.
     *
     * Determines which navigation items should be visible.
     * "viewAll" permissions indicate global access (shows "All" links)
     * Users without global access only see "My" links.
     *
     * @return array<string, bool>
     */
    protected function getNavigationPermissions(mixed $user): array
    {
        // Admin has global view access to all resources
        $hasGlobalAccess = $user->isAdmin();

        return [
            // "All Projects" visible to users with global project access
            'viewAllProjects' => $hasGlobalAccess,

            // "All Tasks" visible to users with global task access
            'viewAllTasks' => $hasGlobalAccess,

            // "All Teams" visible to users with global team access
            'viewAllTeams' => $hasGlobalAccess,

            // "My Projects/Tasks/Teams" always visible to authenticated users
            'viewMyProjects' => true,
            'viewMyTasks' => true,
            'viewMyTeams' => true,
        ];
    }
}
