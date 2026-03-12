<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { 
    ArrowLeftIcon, 
    ClipboardList,
    SquareArrowOutUpRightIcon,
    TrashIcon,
    PlusIcon,
    CalendarIcon,
    UsersIcon,
    ActivityIcon,
    PencilIcon,
} from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import type { DateValue } from '@internationalized/date';
import { DateFormatter, getLocalTimeZone, today, parseDate } from '@internationalized/date';
import { cn } from '@/lib/utils';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'vue-sonner';
import { show, destroy, store, update as updateTask } from '@/actions/App/Http/Controllers/TaskController';
import { updateTeams, update as updateProject } from '@/actions/App/Http/Controllers/ProjectController';

// Calendar state
const minDate = today(getLocalTimeZone());
const selectedDueDate = ref<DateValue>();
const df = new DateFormatter('en-US', { dateStyle: 'long' });

interface TaskPermissions {
    view: boolean;
    delete: boolean;
    edit: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTasks {
    data: Task[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface Status {
    id: number;
    name: string;
}

interface Task {
    id: number;
    title: string;
    description: string | null;
    status: string | null;
    status_id: number | null;
    priority: string;
    due_date: string | null;
    due_date_raw: string | null;
    assignee: string | null;
    created_by: string | null;
    can: TaskPermissions;
}

interface Manager {
    id: number;
    name: string;
}

interface TeamMember {
    id: number;
    name: string;
    initials: string;
}

interface Team {
    id: number;
    name: string;
    members: TeamMember[];
}

interface AvailableTeam {
    id: number;
    name: string;
}

interface Activity {
    id: string;
    source: 'project' | 'task';
    type: string;
    metadata: Record<string, unknown> | null;
    task_name: string | null;
    actor: { id: number; name: string } | null;
    created_at: string;
}

interface Project {
    id: number;
    name: string;
    description: string | null;
    status: string | null;
    status_id: number | null;
    manager: Manager | null;
    start_date: string | null;
    start_date_raw: string | null;
    end_date: string | null;
    end_date_raw: string | null;
    teams: Team[];
}

const props = defineProps<{
    project: Project;
    tasks: PaginatedTasks;
    taskStatuses: Status[];
    activities: Activity[];
    availableTeams: AvailableTeam[];
    backUrl: string;
    canCreateTask: boolean;
    canUpdateTeams: boolean;
    canUpdate: boolean;
    statuses: Status[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: props.backUrl },
    { title: props.project.name, href: `/projects/${props.project.id}` },
];

const getPriorityClass = (priority: string): string => {
    const classes: Record<string, string> = {
        critical: 'text-red-600 font-semibold',
        high: 'text-orange-500 font-medium',
        medium: 'text-yellow-600',
        low: 'text-gray-500',
    };
    return classes[priority] || '';
};

// Delete confirmation state
const deleteDialogOpen = ref(false);
const taskToDelete = ref<Task | null>(null);

// Create task dialog state
const createTaskDialogOpen = ref(false);

// Team management dialog state
const teamDialogOpen = ref(false);
const selectedTeamIds = ref<number[]>([]);

// Computed for save button disabled state
const canSaveTeams = computed(() => selectedTeamIds.value.length > 0);

// Function to check if a team is currently selected
function isTeamSelected(teamId: number): boolean {
    return selectedTeamIds.value.includes(teamId);
}

// Create task form - project_id is pre-filled
const form = useForm({
    project_id: props.project.id,
    title: '',
    description: '',
    priority: '' as string,
    due_date: null as string | null,
});

// Team form - tracks processing state
const isUpdatingTeams = ref(false);

// Edit project dialog state
const editProjectDialogOpen = ref(false);
const selectedEditProjectStartDate = ref<DateValue>();
const selectedEditProjectEndDate = ref<DateValue>();
const editProjectForm = useForm({
    name: props.project.name,
    description: props.project.description ?? '',
    status_id: props.project.status_id ?? undefined,
    start_date: props.project.start_date_raw ?? '',
    end_date: props.project.end_date_raw ?? '',
});

// Computed minimum end date (must be at least 1 day after start date)
const minEditProjectEndDate = computed(() => {
    if (selectedEditProjectStartDate.value) {
        return selectedEditProjectStartDate.value.add({ days: 1 });
    }
    return minDate;
});

// Sync edit project calendar selections to form
watch(selectedEditProjectStartDate, (newDate) => {
    editProjectForm.start_date = newDate ? newDate.toString() : '';
    
    // Auto-adjust end date if it's now invalid (less than or equal to start date)
    if (newDate && selectedEditProjectEndDate.value) {
        const minEndDate = newDate.add({ days: 1 });
        if (selectedEditProjectEndDate.value.compare(minEndDate) < 0) {
            selectedEditProjectEndDate.value = minEndDate;
        }
    }
});

watch(selectedEditProjectEndDate, (newDate) => {
    editProjectForm.end_date = newDate ? newDate.toString() : '';
});

// Edit task dialog state
const editTaskDialogOpen = ref(false);
const taskToEdit = ref<Task | null>(null);
const editTaskForm = useForm({
    title: '',
    description: '',
    status_id: undefined as number | undefined,
    priority: '' as string,
    due_date: '',
});

// Sync calendar selection to form
watch(selectedDueDate, (newDate) => {
    if (newDate) {
        form.due_date = newDate.toString();
    } else {
        form.due_date = null;
    }
});

// Reset team selection when dialog opens to reflect current project state
watch(teamDialogOpen, (open) => {
    if (open) {
        // Initialize with current project team IDs
        selectedTeamIds.value = (props.project.teams ?? []).map(t => t.id);
    }
});

function submitCreateTask() {
    const taskName = form.title;
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Task '${taskName}' has been created.`);
            createTaskDialogOpen.value = false;
            form.reset();
            form.project_id = props.project.id; // Re-set project_id after reset
            selectedDueDate.value = undefined;
        },
        onError: () => {
            toast.error('Failed to create task. Please check the form for errors.');
        },
    });
}

function openDeleteDialog(task: Task) {
    taskToDelete.value = task;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    if (!taskToDelete.value) return;
    
    const task = taskToDelete.value;
    router.delete(destroy.url({ task: task.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Task '${task.title}' has been deleted.`);
            deleteDialogOpen.value = false;
            taskToDelete.value = null;
        },
        onError: () => {
            toast.error('Failed to delete task.');
        },
    });
}

function viewTask(task: Task) {
    router.visit(show.url({ task: task.id }));
}

function toggleTeam(teamId: number, checked: boolean) {
    const currentIds = [...selectedTeamIds.value];
    const index = currentIds.indexOf(teamId);
    
    if (checked && index === -1) {
        currentIds.push(teamId);
    } else if (!checked && index !== -1) {
        currentIds.splice(index, 1);
    }
    
    // Create new array to trigger reactivity
    selectedTeamIds.value = currentIds;
}

function submitTeamChanges() {
    if (selectedTeamIds.value.length === 0) {
        toast.error('Please select at least one team.');
        return;
    }

    isUpdatingTeams.value = true;
    
    router.patch(updateTeams.url({ project: props.project.id }), {
        team_ids: selectedTeamIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Project teams updated successfully.');
            teamDialogOpen.value = false;
            isUpdatingTeams.value = false;
        },
        onError: (errors) => {
            console.error('Team update errors:', errors);
            toast.error('Failed to update teams.');
            isUpdatingTeams.value = false;
        },
        onFinish: () => {
            isUpdatingTeams.value = false;
        },
    });
}

function getActivityDescription(activity: Activity): string {
    const actor = activity.actor?.name ?? 'Someone';
    const meta = activity.metadata ?? {};

    // Handle task activities
    if (activity.source === 'task' && activity.task_name) {
        return formatTaskActivity(actor, activity.type, meta, activity.task_name);
    }

    // Handle project activities
    switch (activity.type) {
        case 'teams_updated':
            return formatTeamsUpdatedActivity(actor, meta);
        case 'project_created':
            return `${actor} created this project`;
        case 'project_updated':
            return formatProjectUpdatedActivity(actor, meta);
        case 'manager_changed':
            return `${actor} changed the manager from "${meta.old_manager}" to "${meta.new_manager}"`;
        case 'status_changed':
            return `${actor} changed status from "${meta.old_status}" to "${meta.new_status}"`;
        default:
            return `${actor} performed "${activity.type}"`;
    }
}

function formatTaskActivity(actor: string, type: string, meta: Record<string, unknown>, taskName: string): string {
    switch (type) {
        case 'task_created':
            return `${actor} created task "${taskName}"`;
        case 'task_updated':
            return formatTaskUpdatedActivity(actor, meta, taskName);
        case 'status_changed': {
            const oldStatus = meta.old_status ?? 'none';
            const newStatus = meta.new_status ?? 'none';
            return `${actor} updated the status from "${oldStatus}" to "${newStatus}" in task "${taskName}"`;
        }
        case 'priority_changed': {
            const oldPriority = meta.old_priority ?? 'none';
            const newPriority = meta.new_priority ?? 'none';
            return `${actor} changed the priority from "${oldPriority}" to "${newPriority}" in task "${taskName}"`;
        }
        case 'progress_updated': {
            const oldProgress = meta.old_progress ?? 0;
            const newProgress = meta.new_progress ?? 0;
            return `${actor} updated progress from ${oldProgress}% to ${newProgress}% in task "${taskName}"`;
        }
        case 'assignee_added': {
            const assigneeName = meta.assignee_name ?? 'a user';
            return `${actor} assigned ${assigneeName} to task "${taskName}"`;
        }
        case 'comment_added':
            return `${actor} added a comment on task "${taskName}"`;
        case 'comment_edited':
            return `${actor} edited a comment on task "${taskName}"`;
        default:
            return `${actor} performed "${type}" on task "${taskName}"`;
    }
}

function formatTaskUpdatedActivity(actor: string, meta: Record<string, unknown>, taskName: string): string {
    const changes = meta.changes as Record<string, { old: unknown; new: unknown }> | undefined;
    if (!changes || Object.keys(changes).length === 0) {
        return `${actor} updated task "${taskName}"`;
    }
    
    const parts: string[] = [];
    
    for (const [field, change] of Object.entries(changes)) {
        const fieldName = field.replace(/_/g, ' ');
        const oldVal = change.old ?? 'none';
        const newVal = change.new ?? 'none';
        parts.push(`${fieldName} from "${oldVal}" to "${newVal}"`);
    }
    
    if (parts.length === 1) {
        return `${actor} changed ${parts[0]} in task "${taskName}"`;
    }
    
    return `${actor} changed: ${parts.join(', ')} in task "${taskName}"`;
}

function formatProjectUpdatedActivity(actor: string, meta: Record<string, unknown>): string {
    const changes = meta.changes as Record<string, { old: unknown; new: unknown }> | undefined;
    if (!changes || Object.keys(changes).length === 0) {
        return `${actor} updated this project`;
    }
    
    const parts: string[] = [];
    
    for (const [field, change] of Object.entries(changes)) {
        const fieldName = field.replace(/_/g, ' ');
        const oldVal = change.old ?? 'none';
        const newVal = change.new ?? 'none';
        parts.push(`${fieldName} from "${oldVal}" to "${newVal}"`);
    }
    
    if (parts.length === 1) {
        return `${actor} changed ${parts[0]}`;
    }
    
    return `${actor} changed: ${parts.join(', ')}`;
}

function formatTeamsUpdatedActivity(actor: string, meta: Record<string, unknown>): string {
    const added = meta.teams_added as string[] | undefined;
    const removed = meta.teams_removed as string[] | undefined;

    const parts: string[] = [];

    if (added && added.length > 0) {
        parts.push(`assigned ${added.join(', ')}`);
    }

    if (removed && removed.length > 0) {
        parts.push(`removed ${removed.join(', ')}`);
    }

    if (parts.length === 0) {
        return `${actor} updated project teams`;
    }

    return `${actor} ${parts.join(' and ')}`;
}

// Compute total team members across all teams (unique)
const totalTeamMembers = computed(() => {
    const memberIds = new Set<number>();
    props.project.teams.forEach(team => {
        team.members.forEach(member => memberIds.add(member.id));
    });
    return memberIds.size;
});

// Pagination function
function goToPage(url: string | null) {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}

// Edit project functions
function openEditProjectDialog() {
    editProjectForm.name = props.project.name;
    editProjectForm.description = props.project.description ?? '';
    editProjectForm.status_id = props.project.status_id ?? undefined;
    editProjectForm.start_date = props.project.start_date_raw ?? '';
    editProjectForm.end_date = props.project.end_date_raw ?? '';
    
    // Parse existing dates for calendar
    if (props.project.start_date_raw) {
        try {
            selectedEditProjectStartDate.value = parseDate(props.project.start_date_raw);
        } catch {
            selectedEditProjectStartDate.value = undefined;
        }
    } else {
        selectedEditProjectStartDate.value = undefined;
    }
    
    if (props.project.end_date_raw) {
        try {
            selectedEditProjectEndDate.value = parseDate(props.project.end_date_raw);
        } catch {
            selectedEditProjectEndDate.value = undefined;
        }
    } else {
        selectedEditProjectEndDate.value = undefined;
    }
    
    editProjectDialogOpen.value = true;
}

function submitEditProject() {
    editProjectForm.patch(updateProject.url({ project: props.project.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Project updated successfully.');
            editProjectDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Failed to update project. Please check the form for errors.');
        },
    });
}

// Edit task functions
function openEditTaskDialog(task: Task) {
    taskToEdit.value = task;
    editTaskForm.title = task.title;
    editTaskForm.description = task.description ?? '';
    editTaskForm.status_id = task.status_id ?? undefined;
    editTaskForm.priority = task.priority;
    editTaskForm.due_date = task.due_date_raw ?? '';
    editTaskDialogOpen.value = true;
}

function submitEditTask() {
    if (!taskToEdit.value) return;
    
    editTaskForm.put(updateTask.url({ task: taskToEdit.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Task updated successfully.');
            editTaskDialogOpen.value = false;
            taskToEdit.value = null;
        },
        onError: (errors) => {
            const message = errors.database || errors.status_id || errors.title || errors.description || 'Failed to update task.';
            toast.error(message);
        },
    });
}
</script>

<template>
    <Head :title="project.name" />

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
                        <h1 class="text-2xl font-semibold tracking-tight">{{ project.name }}</h1>
                        <p class="text-gray-500">{{ project.description ?? 'No description' }}</p>
                    </div>
                </div>
                <Button v-if="canUpdate" variant="outline" @click="openEditProjectDialog">
                    <PencilIcon class="h-4 w-4 mr-2" />
                    Edit Project
                </Button>
            </div>

            <!-- Project Info Card -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Project Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Manager</p>
                            <p class="text-base font-medium">{{ project.manager?.name ?? 'Unassigned' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="text-base font-medium">{{ project.status ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Start Date</p>
                            <p class="text-base font-medium">{{ project.start_date ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">End Date</p>
                            <p class="text-base font-medium">{{ project.end_date ?? 'No deadline' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assigned Teams Card -->
            <Card class="mb-6">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <UsersIcon class="h-5 w-5" />
                            Assigned Teams
                        </CardTitle>
                        <CardDescription>
                            {{ project.teams.length }} {{ project.teams.length === 1 ? 'team' : 'teams' }} · 
                            {{ totalTeamMembers }} {{ totalTeamMembers === 1 ? 'member' : 'members' }}
                        </CardDescription>
                    </div>
                    <Dialog v-model:open="teamDialogOpen">
                        <DialogTrigger as-child>
                            <Button v-if="canUpdateTeams" variant="outline" size="sm">
                                <PencilIcon class="h-4 w-4 mr-2" />
                                Change Teams
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Manage Project Teams</DialogTitle>
                                <DialogDescription>
                                    Select the teams that should be assigned to this project.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="py-4">
                                <div class="h-[300px] overflow-y-auto pr-4">
                                    <div class="space-y-3">
                                        <label 
                                            v-for="team in availableTeams" 
                                            :key="team.id"
                                            class="flex items-center space-x-3 p-3 rounded-lg border hover:bg-muted/50 cursor-pointer"
                                        >
                                            <Checkbox 
                                                :model-value="isTeamSelected(team.id)"
                                                @update:model-value="(checked) => toggleTeam(team.id, checked === true)"
                                            />
                                            <div class="flex-1">
                                                <p class="font-medium">{{ team.name }}</p>
                                            </div>
                                        </label>
                                        <div v-if="availableTeams.length === 0" class="text-center py-8 text-muted-foreground">
                                            No teams available
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" @click="teamDialogOpen = false">
                                    Cancel
                                </Button>
                                <Button 
                                    @click="submitTeamChanges" 
                                    :disabled="isUpdatingTeams || !canSaveTeams"
                                >
                                    {{ isUpdatingTeams ? 'Saving...' : 'Save Changes' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <div v-if="project.teams.length > 0" class="space-y-4">
                        <div v-for="team in project.teams" :key="team.id" class="border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium">{{ team.name }}</h4>
                                <Badge variant="secondary">
                                    {{ team.members.length }} {{ team.members.length === 1 ? 'member' : 'members' }}
                                </Badge>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <div 
                                    v-for="member in team.members" 
                                    :key="member.id"
                                    class="flex items-center gap-2 bg-muted/50 rounded-full pl-1 pr-3 py-1"
                                >
                                    <Avatar class="h-6 w-6">
                                        <AvatarFallback class="text-xs">{{ member.initials }}</AvatarFallback>
                                    </Avatar>
                                    <span class="text-sm">{{ member.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <Empty v-else class="py-6">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UsersIcon />
                            </EmptyMedia>
                            <EmptyTitle>No Teams Assigned</EmptyTitle>
                            <EmptyDescription>
                                This project doesn't have any teams assigned yet.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>

            <!-- Tasks Section -->
            <Card class="mb-6">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Tasks</CardTitle>
                        <CardDescription>
                            {{ tasks.total }} {{ tasks.total === 1 ? 'task' : 'tasks' }} in this project
                        </CardDescription>
                    </div>
                    <Dialog v-model:open="createTaskDialogOpen">
                        <DialogTrigger as-child>
                            <Button v-if="canCreateTask" size="sm">
                                <PlusIcon class="h-4 w-4 mr-2" />
                                Create Task
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Create Task</DialogTitle>
                                <DialogDescription>
                                    Create a new task in {{ project.name }}.
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitCreateTask">
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel>Project</FieldLabel>
                                        <Input
                                            :value="project.name"
                                            type="text"
                                            disabled
                                            class="disabled:opacity-100 disabled:cursor-not-allowed bg-muted"
                                        />
                                    </Field>
                                    <Field :data-invalid="!!form.errors.title">
                                        <FieldLabel for="title">Task Name</FieldLabel>
                                        <Input
                                            id="title"
                                            v-model="form.title"
                                            type="text"
                                            placeholder="Enter task name"
                                        />
                                        <FieldError v-if="form.errors.title" :errors="[form.errors.title]" />
                                    </Field>
                                    <Field :data-invalid="!!form.errors.description">
                                        <FieldLabel for="description">Description</FieldLabel>
                                        <Input
                                            id="description"
                                            v-model="form.description"
                                            type="text"
                                            placeholder="Describe the task"
                                        />
                                        <FieldError v-if="form.errors.description" :errors="[form.errors.description]" />
                                    </Field>
                                    <Field :data-invalid="!!form.errors.priority">
                                        <FieldLabel for="priority">Priority</FieldLabel>
                                        <Select v-model="form.priority">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Select a priority" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectLabel>Priorities</SelectLabel>
                                                    <SelectItem value="low">Low</SelectItem>
                                                    <SelectItem value="medium">Medium</SelectItem>
                                                    <SelectItem value="high">High</SelectItem>
                                                    <SelectItem value="critical">Critical</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError v-if="form.errors.priority" :errors="[form.errors.priority]" />
                                    </Field>
                                    <Field :data-invalid="!!form.errors.due_date">
                                        <FieldLabel>Due Date</FieldLabel>
                                        <Popover>
                                            <PopoverTrigger as-child>
                                                <Button
                                                    variant="outline"
                                                    :class="cn(
                                                        'w-full justify-start text-left font-normal',
                                                        !selectedDueDate && 'text-muted-foreground',
                                                    )"
                                                >
                                                    <CalendarIcon class="mr-2 h-4 w-4" />
                                                    {{ selectedDueDate ? df.format(selectedDueDate.toDate(getLocalTimeZone())) : 'Pick a date' }}
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-auto p-0">
                                                <Calendar v-model="selectedDueDate" :min-value="minDate" :initial-focus="true" />
                                            </PopoverContent>
                                        </Popover>
                                        <FieldError v-if="form.errors.due_date" :errors="[form.errors.due_date]" />
                                    </Field>
                                </FieldGroup>
                                <DialogFooter class="mt-4">
                                    <Button type="submit" :disabled="form.processing">
                                        {{ form.processing ? 'Creating...' : 'Create Task' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <Table v-if="tasks.data.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Task</TableHead>
                                <TableHead>Assignee</TableHead>
                                <TableHead>Created By</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Priority</TableHead>
                                <TableHead>Due Date</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="task in tasks.data" :key="task.id">
                                <TableCell class="font-medium">{{ task.title }}</TableCell>
                                <TableCell>{{ task.assignee ?? 'Unassigned' }}</TableCell>
                                <TableCell>{{ task.created_by ?? '—' }}</TableCell>
                                <TableCell>{{ task.status ?? '—' }}</TableCell>
                                <TableCell>
                                    <span :class="getPriorityClass(task.priority)" class="capitalize">
                                        {{ task.priority }}
                                    </span>
                                </TableCell>
                                <TableCell>{{ task.due_date ?? 'No due date' }}</TableCell>
                                <TableCell class="flex gap-2">
                                    <Button 
                                        v-if="task.can.view"
                                        variant="outline" 
                                        size="sm"
                                        @click="viewTask(task)"
                                        title="View task"
                                    >
                                        <SquareArrowOutUpRightIcon class="h-4 w-4" />
                                    </Button>
                                    <Button 
                                        v-if="task.can.edit"
                                        variant="outline" 
                                        size="sm"
                                        @click="openEditTaskDialog(task)"
                                        title="Edit task"
                                    >
                                        <PencilIcon class="h-4 w-4" />
                                    </Button>
                                    <Button 
                                        v-if="task.can.delete"
                                        variant="outline" 
                                        size="sm"
                                        @click="openDeleteDialog(task)"
                                        title="Delete task"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div v-if="tasks.data.length > 0 && tasks.last_page > 1" class="flex items-center justify-between mt-4">
                        <p class="text-sm text-muted-foreground">
                            Showing {{ (tasks.current_page - 1) * tasks.per_page + 1 }} to 
                            {{ Math.min(tasks.current_page * tasks.per_page, tasks.total) }} of 
                            {{ tasks.total }} tasks
                        </p>
                        <div class="flex gap-2">
                            <Button
                                v-for="link in tasks.links"
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
                    <Empty v-if="tasks.data.length === 0" class="py-8">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ClipboardList />
                            </EmptyMedia>
                            <EmptyTitle>No Tasks Yet</EmptyTitle>
                            <EmptyDescription>
                                This project doesn't have any tasks yet.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>

            <!-- Project Activity Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ActivityIcon class="h-5 w-5" />
                        Project Activity
                    </CardTitle>
                    <CardDescription>
                        Recent changes to this project
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="activities.length > 0" class="max-h-[400px] overflow-y-auto">
                        <div class="space-y-3">
                            <div 
                                v-for="activity in activities" 
                                :key="activity.id"
                                class="flex items-start gap-3 pb-3 border-b last:border-0"
                            >
                                <Avatar class="h-8 w-8 mt-0.5">
                                    <AvatarFallback class="text-xs">
                                        {{ activity.actor?.name.split(' ').map(n => n[0]).join('') ?? '?' }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm">{{ getActivityDescription(activity) }}</p>
                                    <p class="text-xs text-muted-foreground">{{ activity.created_at }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <Empty v-else class="py-6">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ActivityIcon />
                            </EmptyMedia>
                            <EmptyTitle>No Activity Yet</EmptyTitle>
                            <EmptyDescription>
                                Project activity will appear here.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Task</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete "{{ taskToDelete?.title }}"? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete">Delete</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Edit Project Dialog -->
        <Dialog v-model:open="editProjectDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Edit Project</DialogTitle>
                    <DialogDescription>
                        Update the project details below.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitEditProject">
                    <FieldGroup>
                        <Field :data-invalid="!!editProjectForm.errors.name">
                            <FieldLabel for="edit-project-name">Project Name</FieldLabel>
                            <Input
                                id="edit-project-name"
                                v-model="editProjectForm.name"
                                type="text"
                                placeholder="Enter project name"
                            />
                            <FieldError v-if="editProjectForm.errors.name" :errors="[editProjectForm.errors.name]" />
                        </Field>
                        <Field :data-invalid="!!editProjectForm.errors.description">
                            <FieldLabel for="edit-project-description">Description</FieldLabel>
                            <Textarea
                                id="edit-project-description"
                                v-model="editProjectForm.description"
                                placeholder="Describe the project"
                                rows="3"
                            />
                            <FieldError v-if="editProjectForm.errors.description" :errors="[editProjectForm.errors.description]" />
                        </Field>
                        <Field :data-invalid="!!editProjectForm.errors.status_id">
                            <FieldLabel for="edit-project-status">Status</FieldLabel>
                            <Select v-model="editProjectForm.status_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select a status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Statuses</SelectLabel>
                                        <SelectItem
                                            v-for="status in statuses"
                                            :key="status.id"
                                            :value="status.id"
                                        >
                                            {{ status.name }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError v-if="editProjectForm.errors.status_id" :errors="[editProjectForm.errors.status_id]" />
                        </Field>
                        <Field :data-invalid="!!editProjectForm.errors.start_date">
                            <FieldLabel>Start Date</FieldLabel>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="cn(
                                            'w-full justify-start text-left font-normal',
                                            !selectedEditProjectStartDate && 'text-muted-foreground',
                                        )"
                                    >
                                        <CalendarIcon class="mr-2 h-4 w-4" />
                                        {{ selectedEditProjectStartDate ? df.format(selectedEditProjectStartDate.toDate(getLocalTimeZone())) : 'Pick a start date' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <Calendar v-model="selectedEditProjectStartDate" :min-value="minDate" :initial-focus="true" />
                                </PopoverContent>
                            </Popover>
                            <FieldError v-if="editProjectForm.errors.start_date" :errors="[editProjectForm.errors.start_date]" />
                        </Field>
                        <Field :data-invalid="!!editProjectForm.errors.end_date">
                            <FieldLabel>Due Date</FieldLabel>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="cn(
                                            'w-full justify-start text-left font-normal',
                                            !selectedEditProjectEndDate && 'text-muted-foreground',
                                        )"
                                    >
                                        <CalendarIcon class="mr-2 h-4 w-4" />
                                        {{ selectedEditProjectEndDate ? df.format(selectedEditProjectEndDate.toDate(getLocalTimeZone())) : 'Pick a due date' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <Calendar v-model="selectedEditProjectEndDate" :min-value="minEditProjectEndDate" :initial-focus="true" />
                                </PopoverContent>
                            </Popover>
                            <FieldError v-if="editProjectForm.errors.end_date" :errors="[editProjectForm.errors.end_date]" />
                        </Field>
                    </FieldGroup>
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="editProjectDialogOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="editProjectForm.processing">
                            {{ editProjectForm.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Task Dialog -->
        <Dialog v-model:open="editTaskDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Edit Task</DialogTitle>
                    <DialogDescription>
                        Update the task details below.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitEditTask">
                    <FieldGroup>
                        <Field :data-invalid="!!editTaskForm.errors.title">
                            <FieldLabel for="edit-task-title">Title</FieldLabel>
                            <Input
                                id="edit-task-title"
                                v-model="editTaskForm.title"
                                type="text"
                                placeholder="Enter task title"
                            />
                            <FieldError v-if="editTaskForm.errors.title" :errors="[editTaskForm.errors.title]" />
                        </Field>
                        <Field :data-invalid="!!editTaskForm.errors.description">
                            <FieldLabel for="edit-task-description">Description</FieldLabel>
                            <Textarea
                                id="edit-task-description"
                                v-model="editTaskForm.description"
                                placeholder="Describe the task"
                                rows="3"
                            />
                            <FieldError v-if="editTaskForm.errors.description" :errors="[editTaskForm.errors.description]" />
                        </Field>
                        <Field :data-invalid="!!editTaskForm.errors.status_id">
                            <FieldLabel for="edit-task-status">Status</FieldLabel>
                            <Select v-model="editTaskForm.status_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select a status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Statuses</SelectLabel>
                                        <SelectItem
                                            v-for="status in taskStatuses"
                                            :key="status.id"
                                            :value="status.id"
                                        >
                                            {{ status.name }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError v-if="editTaskForm.errors.status_id" :errors="[editTaskForm.errors.status_id]" />
                        </Field>
                        <Field :data-invalid="!!editTaskForm.errors.priority">
                            <FieldLabel for="edit-task-priority">Priority</FieldLabel>
                            <Select v-model="editTaskForm.priority">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Select a priority" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Priorities</SelectLabel>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="critical">Critical</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError v-if="editTaskForm.errors.priority" :errors="[editTaskForm.errors.priority]" />
                        </Field>
                        <Field :data-invalid="!!editTaskForm.errors.due_date">
                            <FieldLabel for="edit-task-due-date">Due Date</FieldLabel>
                            <Input
                                id="edit-task-due-date"
                                v-model="editTaskForm.due_date"
                                type="date"
                            />
                            <FieldError v-if="editTaskForm.errors.due_date" :errors="[editTaskForm.errors.due_date]" />
                        </Field>
                    </FieldGroup>
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="editTaskDialogOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="editTaskForm.processing">
                            {{ editTaskForm.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
