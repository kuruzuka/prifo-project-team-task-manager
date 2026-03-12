<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { 
    ArrowLeftIcon, 
    UsersIcon,
    FolderKanbanIcon,
    PlusIcon,
    TrashIcon,
    SquareArrowOutUpRightIcon,
} from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { toast } from 'vue-sonner';
import { show as showProject } from '@/actions/App/Http/Controllers/ProjectController';
import { addMember, removeMember } from '@/actions/App/Http/Controllers/TeamController';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedMembers {
    data: Member[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface Member {
    id: number;
    name: string;
    initials: string;
    email: string;
    job_title: string | null;
    role: string;
    avatar: string | null;
}

interface Manager {
    id: number;
    name: string;
    initials: string;
}

interface Project {
    id: number;
    name: string;
    description: string | null;
    status: string | null;
    progress: number;
    tasks_count: number;
    manager: Manager | null;
    deadline: string | null;
}

interface AvailableUser {
    id: number;
    name: string;
    email: string;
}

interface Team {
    id: number;
    name: string;
    description: string | null;
}

interface Permissions {
    canAddMember: boolean;
    canRemoveMember: boolean;
}

const props = defineProps<{
    team: Team;
    members: PaginatedMembers;
    projects: Project[];
    availableUsers: AvailableUser[];
    backUrl: string;
    permissions: Permissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Teams', href: props.backUrl },
    { title: props.team.name, href: `/teams/${props.team.id}` },
];

// Add member dialog state
const addMemberDialogOpen = ref(false);
const selectedUserId = ref<number | undefined>(undefined);
const isAddingMember = ref(false);

// Remove member confirmation state
const removeDialogOpen = ref(false);
const memberToRemove = ref<Member | null>(null);

// Computed for add member button disabled state
const canAddSelectedMember = computed(() => selectedUserId.value !== undefined);

function getRoleClass(role: string): string {
    const classes: Record<string, string> = {
        Admin: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        Manager: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        Member: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };
    return classes[role] || classes.Member;
}

function submitAddMember() {
    if (!selectedUserId.value) {
        toast.error('Please select a user to add.');
        return;
    }

    isAddingMember.value = true;

    router.post(addMember.url({ team: props.team.id }), {
        user_id: selectedUserId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Member added successfully.');
            addMemberDialogOpen.value = false;
            selectedUserId.value = undefined;
            isAddingMember.value = false;
        },
        onError: (errors) => {
            const errorMsg = errors.user_id || 'Failed to add member.';
            toast.error(errorMsg);
            isAddingMember.value = false;
        },
        onFinish: () => {
            isAddingMember.value = false;
        },
    });
}

function openRemoveDialog(member: Member) {
    memberToRemove.value = member;
    removeDialogOpen.value = true;
}

function confirmRemove() {
    if (!memberToRemove.value) return;

    const member = memberToRemove.value;
    router.delete(removeMember.url({ team: props.team.id, user: member.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`${member.name} has been removed from the team.`);
            removeDialogOpen.value = false;
            memberToRemove.value = null;
        },
        onError: () => {
            toast.error('Failed to remove member.');
        },
    });
}

function viewProject(project: Project) {
    router.visit(showProject.url({ project: project.id }));
}

// Pagination function
function goToPage(url: string | null) {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="team.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="my-5 ml-5 mr-5 lg:mr-40">
            <!-- Header with back button -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <Link :href="backUrl">
                        <Button variant="outline" size="icon">
                            <ArrowLeftIcon class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ team.name }}</h1>
                        <p class="text-gray-500">{{ team.description ?? 'No description' }}</p>
                    </div>
                </div>
            </div>

            <!-- Team Info Card -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Team Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Total Members</p>
                            <p class="text-base font-medium">{{ members.total }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Active Projects</p>
                            <p class="text-base font-medium">{{ projects.length }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Team ID</p>
                            <p class="text-base font-medium">#{{ team.id }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Members Section -->
            <Card class="mb-6">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <UsersIcon class="h-5 w-5" />
                            Team Members
                        </CardTitle>
                        <CardDescription>
                            {{ members.total }} {{ members.total === 1 ? 'member' : 'members' }} in this team
                        </CardDescription>
                    </div>
                    <Dialog v-model:open="addMemberDialogOpen">
                        <Button 
                            v-if="permissions.canAddMember && availableUsers.length > 0" 
                            size="sm"
                            @click="addMemberDialogOpen = true"
                        >
                            <PlusIcon class="h-4 w-4 mr-2" />
                            Add Member
                        </Button>
                        <DialogContent class="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Add Team Member</DialogTitle>
                                <DialogDescription>
                                    Select a user to add to {{ team.name }}.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="py-4">
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel for="user">Select User</FieldLabel>
                                        <Select v-model="selectedUserId">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Choose a user..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectLabel>Available Users</SelectLabel>
                                                    <SelectItem 
                                                        v-for="user in availableUsers" 
                                                        :key="user.id"
                                                        :value="user.id"
                                                    >
                                                        {{ user.name }} ({{ user.email }})
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </FieldGroup>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" @click="addMemberDialogOpen = false">
                                    Cancel
                                </Button>
                                <Button 
                                    @click="submitAddMember" 
                                    :disabled="isAddingMember || !canAddSelectedMember"
                                >
                                    {{ isAddingMember ? 'Adding...' : 'Add Member' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <Table v-if="members.data.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Member</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Job Title</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead v-if="permissions.canRemoveMember">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="member in members.data" :key="member.id">
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Avatar class="h-8 w-8">
                                            <AvatarFallback>{{ member.initials }}</AvatarFallback>
                                        </Avatar>
                                        <span class="font-medium">{{ member.name }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ member.email }}</TableCell>
                                <TableCell>{{ member.job_title ?? '—' }}</TableCell>
                                <TableCell>
                                    <Badge 
                                        variant="secondary" 
                                        :class="getRoleClass(member.role)"
                                    >
                                        {{ member.role }}
                                    </Badge>
                                </TableCell>
                                <TableCell v-if="permissions.canRemoveMember">
                                    <Button 
                                        variant="outline" 
                                        size="sm"
                                        @click="openRemoveDialog(member)"
                                        title="Remove member"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div v-if="members.data.length > 0 && members.last_page > 1" class="flex items-center justify-between mt-4">
                        <p class="text-sm text-muted-foreground">
                            Showing {{ (members.current_page - 1) * members.per_page + 1 }} to 
                            {{ Math.min(members.current_page * members.per_page, members.total) }} of 
                            {{ members.total }} members
                        </p>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in members.links"
                                :key="link.label"
                                variant="outline"
                                size="sm"
                                :disabled="!link.url"
                                :class="{ 'bg-primary text-primary-foreground': link.active }"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>

                    <!-- Empty state -->
                    <Empty v-if="members.data.length === 0" class="py-8">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>No Team Members</EmptyTitle>
                            <EmptyDescription>
                                This team doesn't have any members yet.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>

            <!-- Projects Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FolderKanbanIcon class="h-5 w-5" />
                        Assigned Projects
                    </CardTitle>
                    <CardDescription>
                        Projects this team is working on
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="projects.length > 0" class="space-y-4">
                        <div 
                            v-for="project in projects" 
                            :key="project.id" 
                            class="border rounded-lg p-4 hover:bg-muted/50 transition-colors"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h4 class="font-medium">{{ project.name }}</h4>
                                    <p class="text-sm text-muted-foreground line-clamp-1">
                                        {{ project.description ?? 'No description' }}
                                    </p>
                                </div>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    @click="viewProject(project)"
                                    title="View project"
                                >
                                    <SquareArrowOutUpRightIcon class="h-4 w-4" />
                                </Button>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                <Badge variant="outline">{{ project.status ?? 'Unknown' }}</Badge>
                                <span class="text-muted-foreground">
                                    {{ project.tasks_count }} {{ project.tasks_count === 1 ? 'task' : 'tasks' }}
                                </span>
                                <span v-if="project.manager" class="text-muted-foreground">
                                    Manager: {{ project.manager.name }}
                                </span>
                                <span v-if="project.deadline" class="text-muted-foreground">
                                    Due: {{ project.deadline }}
                                </span>
                            </div>
                            <div class="mt-3">
                                <div class="flex items-center gap-2">
                                    <Progress :model-value="project.progress" class="flex-1 h-2" />
                                    <span class="text-sm text-muted-foreground w-12 text-right">
                                        {{ project.progress }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <Empty v-else class="py-6">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FolderKanbanIcon />
                            </EmptyMedia>
                            <EmptyTitle>No Projects Assigned</EmptyTitle>
                            <EmptyDescription>
                                This team hasn't been assigned to any projects yet.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        </div>

        <!-- Remove Member Confirmation Dialog -->
        <AlertDialog v-model:open="removeDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Remove Team Member</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to remove "{{ memberToRemove?.name }}" from this team? 
                        They will lose access to team projects.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="confirmRemove">Remove</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
