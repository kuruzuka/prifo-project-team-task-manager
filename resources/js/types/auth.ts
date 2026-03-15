export type User = {
    id: number;
    first_name: string;
    last_name: string;
    middle_name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * Permission abilities for global actions.
 */
export type CanPermissions = {
    manageTeams: boolean;
    createProjects: boolean;
    createTasks: boolean;
    inviteMembers: boolean;
    assignRoles: boolean;
    viewActivityLogs: boolean;
};

/**
 * Navigation visibility permissions.
 * Controls which navigation items are visible to the user.
 */
export type NavPermissions = {
    viewAllProjects: boolean;
    viewAllTasks: boolean;
    viewAllTeams: boolean;
    viewMyProjects: boolean;
    viewMyTasks: boolean;
    viewMyTeams: boolean;
    viewDeveloperDocs: boolean;
};

/**
 * User permissions shared from backend via Inertia.
 */
export type Permissions = {
    isDeveloper: boolean;
    isAdmin: boolean;
    isManager: boolean;
    isMember: boolean;
    roles: string[];
    teamIds: number[];
    managedProjectIds: number[];
    can: CanPermissions;
    nav: NavPermissions;
};

export type Auth = {
    user: User;
    permissions: Permissions | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
