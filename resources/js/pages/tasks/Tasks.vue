<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { 
    PlusIcon, SearchIcon,
    TrashIcon,
    SquareArrowOutUpRightIcon,
} from 'lucide-vue-next';
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from '@/components/ui/input-group'
import {
    ToggleGroup,
    ToggleGroupItem,
} from '@/components/ui/toggle-group';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ref, watch, computed } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { usePermissions } from '@/composables/usePermissions';
import { toast } from 'vue-sonner';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import type { DateValue } from '@internationalized/date'
import { DateFormatter, getLocalTimeZone, today } from '@internationalized/date'
import { CalendarIcon } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { Calendar } from '@/components/ui/calendar'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { store, show, destroy } from '@/actions/App/Http/Controllers/TaskController';

// Calendar state
const minDate = today(getLocalTimeZone());
const selectedDueDate = ref<DateValue>();
const df = new DateFormatter('en-US', { dateStyle: 'long' });

// Dialog state
const dialogOpen = ref(false);

// Inertia form
const form = useForm({
    project_id: null as number | null,
    title: '',
    description: '',
    priority: '' as string,
    due_date: null as string | null,
});

// Sync calendar selection to form
watch(selectedDueDate, (newDate) => {
    if (newDate) {
        form.due_date = newDate.toString();
    } else {
        form.due_date = null;
    }
});

function submitForm() {
    const taskName = form.title;
    const projectName = props.projects.find(p => p.id === form.project_id)?.name ?? 'Unknown';
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Task '${taskName}' in '${projectName}' has been created.`);
            dialogOpen.value = false;
            form.reset();
            selectedDueDate.value = undefined;
        },
        onError: () => {
            toast.error('Failed to create task. Please check the form for errors.');
        },
    });
}

interface TaskPermissions {
    view: boolean;
    delete: boolean;
}

interface Task {
    id: number;
    title: string;
    project: string | null;
    assignee: string | null;
    created_by: string | null;
    status: string | null;
    priority: string;
    due_date: string | null;
    can: TaskPermissions;
}

interface TaskStatus {
    id: number;
    name: string;
}

interface Project {
    id: number;
    name: string;
}

interface PaginatedTasks {
    data: Task[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
    search: string;
    status: string;
}

interface UserInfo {
    id: number;
    name: string;
}

const props = defineProps<{
    tasks: PaginatedTasks;
    statuses: TaskStatus[];
    filters: Filters;
    user?: UserInfo;
    projects: Project[];
}>();

const breadcrumbs: BreadcrumbItem[] = props.user
    ? [
        { title: 'Tasks', href: '/tasks' },
        { title: props.user.name, href: `/users/${props.user.id}/tasks` },
    ]
    : [
        { title: 'Tasks', href: '/tasks' },
    ];

const pageTitle = computed(() => props.user ? `${props.user.name}'s Tasks` : 'Tasks');
const pageDescription = computed(() => 
    props.user 
        ? `Tasks assigned to ${props.user.name}.` 
        : 'Manage your tasks here.'
);

const search = ref(props.filters.search);
const statusFilter = ref(props.filters.status);

const getFilterRoute = () => {
    return props.user ? `/users/${props.user.id}/tasks` : '/tasks';
};

const applyFilters = () => {
    router.get(getFilterRoute(), {
        search: search.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const debouncedSearch = useDebounceFn(applyFilters, 300);

watch(search, () => {
    debouncedSearch();
});

watch(statusFilter, () => {
    applyFilters();
});

const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

const getPriorityClass = (priority: string): string => {
    const classes: Record<string, string> = {
        critical: 'text-red-600 font-semibold',
        high: 'text-orange-500 font-medium',
        medium: 'text-yellow-600',
        low: 'text-gray-500',
    };
    return classes[priority] || '';
};

const { canCreateTasks } = usePermissions();

// Delete confirmation state
const deleteDialogOpen = ref(false);
const taskToDelete = ref<Task | null>(null);

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
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="my-5 ml-5 mr-40">
            <div class="flex justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ pageTitle }}</h1>
                    <p class="text-gray-500">{{ pageDescription }}</p>
                </div>
                <!-- <Button v-if="canCreateTasks" size="lg">
                    <PlusIcon class="h-4 w-4 mr-2" />
                    Create Task
                </Button> -->
                <div>
                    <Dialog v-model:open="dialogOpen">
                        <DialogTrigger as-child>
                            <Button v-if="canCreateTasks" size="lg">
                                <PlusIcon class="h-4 w-4 mr-2" />
                                Create Task
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Create Task</DialogTitle>
                                <DialogDescription>
                                    Fill in the details for your new task.
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitForm">
                                <FieldGroup>
                                    <Field :data-invalid="!!form.errors.project_id">
                                        <FieldLabel for="project_id">Project</FieldLabel>
                                        <Select v-model="form.project_id">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Select a project" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectLabel>Projects</SelectLabel>
                                                    <SelectItem
                                                        v-for="project in projects"
                                                        :key="project.id"
                                                        :value="project.id"
                                                    >
                                                        {{ project.name }}
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError v-if="form.errors.project_id" :errors="[form.errors.project_id]" />
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
                                        {{ form.processing ? 'Creating...' : 'Create' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
            <div class="mt-4">
                <InputGroup>
                    <InputGroupInput 
                        v-model="search"
                        placeholder="Search by task, project, assignee, status, or priority..." 
                    />
                    <InputGroupAddon>
                        <SearchIcon />
                    </InputGroupAddon>
                </InputGroup>
            </div>
            <div class="mt-5">
                <ToggleGroup v-model="statusFilter" variant="outline" type="single" size="lg">
                    <ToggleGroupItem value="all" aria-label="All tasks" class="px-5 py-4">
                        <p>All</p>
                    </ToggleGroupItem>
                    <ToggleGroupItem value="todo" aria-label="To Do tasks" class="px-5 py-4">   
                        <p>To Do</p>
                    </ToggleGroupItem>
                    <ToggleGroupItem value="in_progress" aria-label="In Progress tasks" class="px-5 py-4">
                        <p>In Progress</p>
                    </ToggleGroupItem>
                    <ToggleGroupItem value="in_review" aria-label="In Review tasks" class="px-5 py-4">
                        <p>In Review</p>
                    </ToggleGroupItem>
                    <ToggleGroupItem value="blocked" aria-label="Blocked tasks" class="px-5 py-4">
                        <p>Blocked</p>
                    </ToggleGroupItem>
                    <ToggleGroupItem value="done" aria-label="Done tasks" class="px-5 py-4">
                        <p>Done</p>
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
            <div class="mt-5">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Task</TableHead>
                            <TableHead>Project</TableHead>
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
                            <TableCell>{{ task.project ?? '—' }}</TableCell>
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
                        <TableRow v-if="tasks.data.length === 0">
                            <TableCell colspan="8" class="text-center py-8 text-gray-500">
                                No tasks found.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pagination -->
                <div v-if="tasks.last_page > 1" class="flex items-center justify-between mt-4">
                    <p class="text-sm text-gray-500">
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
            </div>
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
    </AppLayout>
</template>