import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Permissions, NavPermissions } from '@/types';

/**
 * Composable for accessing user permissions in Vue components.
 *
 * Provides reactive access to role-based permissions shared from the backend
 * via Inertia middleware. Use this for conditional UI rendering based on
 * user capabilities.
 *
 * Security Note: This provides UI hints only. All authorization is enforced
 * server-side via Laravel Policies. Never rely solely on frontend checks.
 *
 * @example
 * ```vue
 * <script setup>
 * const { isAdmin, nav, canManageTeams } = usePermissions();
 * </script>
 *
 * <template>
 *   <NavLink v-if="nav.viewAllProjects" href="/projects">All Projects</NavLink>
 *   <button v-if="canManageTeams">Create Team</button>
 * </template>
 * ```
 */
export function usePermissions() {
    const page = usePage<{ auth: { permissions: Permissions | null } }>();

    /**
     * Raw permissions object from backend.
     */
    const permissions = computed(() => page.props.auth?.permissions ?? null);

    /**
     * Whether the user is authenticated and has permissions loaded.
     */
    const isAuthenticated = computed(() => permissions.value !== null);

    /**
     * Whether the user has the Admin role.
     */
    const isAdmin = computed(() => permissions.value?.isAdmin ?? false);

    /**
     * Whether the user has the Manager role.
     */
    const isManager = computed(() => permissions.value?.isManager ?? false);

    /**
     * Whether the user has the Member role.
     */
    const isMember = computed(() => permissions.value?.isMember ?? false);

    /**
     * Array of role names the user has.
     */
    const roles = computed(() => permissions.value?.roles ?? []);

    /**
     * Array of team IDs the user belongs to.
     */
    const teamIds = computed(() => permissions.value?.teamIds ?? []);

    /**
     * Array of project IDs the user manages.
     */
    const managedProjectIds = computed(() => permissions.value?.managedProjectIds ?? []);

    // Global ability checks
    const canManageTeams = computed(() => permissions.value?.can.manageTeams ?? false);
    const canCreateProjects = computed(() => permissions.value?.can.createProjects ?? false);
    const canCreateTasks = computed(() => permissions.value?.can.createTasks ?? false);
    const canInviteMembers = computed(() => permissions.value?.can.inviteMembers ?? false);
    const canAssignRoles = computed(() => permissions.value?.can.assignRoles ?? false);
    const canViewActivityLogs = computed(() => permissions.value?.can.viewActivityLogs ?? false);

    /**
     * Check if user has a specific role.
     */
    function hasRole(roleName: string): boolean {
        return roles.value.includes(roleName);
    }

    /**
     * Check if user has any of the given roles.
     */
    function hasAnyRole(roleNames: string[]): boolean {
        return roleNames.some((role) => roles.value.includes(role));
    }

    /**
     * Check if user belongs to a specific team.
     */
    function belongsToTeam(teamId: number): boolean {
        return teamIds.value.includes(teamId);
    }

    /**
     * Check if user manages a specific project.
     */
    function managesProject(projectId: number): boolean {
        if (isAdmin.value) return true;
        return managedProjectIds.value.includes(projectId);
    }

    /**
     * Check if user can edit a project.
     * Admins can edit any project.
     * Managers can edit projects they manage.
     */
    function canEditProject(projectId: number): boolean {
        if (isAdmin.value) return true;
        if (isManager.value) return managesProject(projectId);
        return false;
    }

    /**
     * Check if user can manage tasks in a project.
     * Admins and project managers can manage tasks.
     */
    function canManageProjectTasks(projectId: number): boolean {
        return isAdmin.value || managesProject(projectId);
    }

    /**
     * Check if user can update a task.
     * Members can only update progress on assigned tasks.
     * This is a UI hint - actual permission depends on task assignment.
     */
    function canUpdateTask(projectId: number, isAssigned: boolean = false): boolean {
        if (isAdmin.value) return true;
        if (isManager.value && managesProject(projectId)) return true;
        if (isMember.value && isAssigned) return true;
        return false;
    }

    /**
     * Navigation visibility permissions.
     * Controls which navigation items should be rendered.
     */
    const nav = computed<NavPermissions>(() => ({
        viewAllProjects: permissions.value?.nav?.viewAllProjects ?? false,
        viewAllTasks: permissions.value?.nav?.viewAllTasks ?? false,
        viewAllTeams: permissions.value?.nav?.viewAllTeams ?? false,
        viewMyProjects: permissions.value?.nav?.viewMyProjects ?? true,
        viewMyTasks: permissions.value?.nav?.viewMyTasks ?? true,
        viewMyTeams: permissions.value?.nav?.viewMyTeams ?? true,
    }));

    return {
        // Computed permissions
        permissions,
        isAuthenticated,
        isAdmin,
        isManager,
        isMember,
        roles,
        teamIds,
        managedProjectIds,

        // Navigation permissions
        nav,

        // Global abilities
        canManageTeams,
        canCreateProjects,
        canCreateTasks,
        canInviteMembers,
        canAssignRoles,
        canViewActivityLogs,

        // Helper functions
        hasRole,
        hasAnyRole,
        belongsToTeam,
        managesProject,
        canEditProject,
        canManageProjectTasks,
        canUpdateTask,
    };
}
