<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    BookOpen, FolderGit2, LayoutGrid,
    FolderKanbanIcon, UsersRoundIcon,
    UsersIcon, FolderOpenDotIcon, ListChecksIcon,
    ListTodoIcon,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';

const page = usePage();
const { nav } = usePermissions();

const userId = page.props.auth.user.id;

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

/**
 * Project navigation items.
 * "All" only visible to users with global project access (Admins).
 * "My Projects" visible to all authenticated users.
 */
const projectNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    
    if (nav.value.viewAllProjects) {
        items.push({
            title: 'All',
            href: '/projects',
            icon: FolderKanbanIcon,
        });
    }
    
    if (nav.value.viewMyProjects) {
        items.push({
            title: 'My Projects',
            href: `/users/${userId}/projects`,
            icon: FolderOpenDotIcon,
        });
    }
    
    return items;
});

/**
 * Task navigation items.
 * "All" only visible to users with global task access (Admins).
 * "My Tasks" visible to all authenticated users.
 */
const taskNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    
    if (nav.value.viewAllTasks) {
        items.push({
            title: 'All',
            href: '/tasks',
            icon: ListChecksIcon,
        });
    }
    
    if (nav.value.viewMyTasks) {
        items.push({
            title: 'My Tasks',
            href: `/users/${userId}/tasks`,
            icon: ListTodoIcon,
        });
    }
    
    return items;
});

/**
 * Team navigation items.
 * "All" only visible to users with global team access (Admins).
 * "My Teams" visible to all authenticated users.
 */
const teamNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    
    if (nav.value.viewAllTeams) {
        items.push({
            title: 'All',
            href: '/teams',
            icon: UsersIcon,
        });
    }
    
    if (nav.value.viewMyTeams) {
        items.push({
            title: 'My Teams',
            href: `/users/${userId}/teams`,
            icon: UsersRoundIcon,
        });
    }
    
    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
        
        <SidebarContent>
            <NavMain :items="mainNavItems" nav-title="Main" />
            <NavMain :items="projectNavItems" nav-title="Projects" />
            <NavMain :items="taskNavItems" nav-title="Tasks" />
            <NavMain :items="teamNavItems" nav-title="Teams" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
