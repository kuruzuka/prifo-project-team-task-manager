<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { PlusIcon, FolderCode, CalendarIcon } from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import {
    Empty,
    EmptyHeader,
    EmptyDescription,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { computed, ref, watch } from 'vue';
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
} from '@/components/ui/dialog';
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { DateValue } from '@internationalized/date';
import { DateFormatter, getLocalTimeZone, today } from '@internationalized/date';
import { cn } from '@/lib/utils';
import { Calendar } from '@/components/ui/calendar';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { store } from '@/actions/App/Http/Controllers/ProjectController';

// Calendar state
const minDate = today(getLocalTimeZone());
const selectedDate = ref<DateValue>();
const df = new DateFormatter('en-US', { dateStyle: 'long' });

// Dialog state
const dialogOpen = ref(false);

// Inertia form
const form = useForm({
    name: '',
    description: '',
    due_date: null as string | null,
});

// Sync calendar selection to form
watch(selectedDate, (newDate) => {
    if (newDate) {
        form.due_date = newDate.toString();
    } else {
        form.due_date = null;
    }
});

function submitForm() {
    const projectName = form.name;
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Project "${projectName}" has been created`);
            dialogOpen.value = false;
            form.reset();
            selectedDate.value = undefined;
        },
        onError: () => {
            toast.error(`Failed to create project "${projectName}"`);
        },
    });
}

interface Project {
    id: number;
    name: string;
    description: string | null;
    status: string | null;
    progress: number;
    tasks_count: number;
    team_members_count: number;
    deadline: string | null;
    start_date: string | null;
}

interface UserInfo {
    id: number;
    name: string;
}

const props = defineProps<{
    projects: Project[];
    user?: UserInfo;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => 
    props.user
        ? [
            { title: 'Projects', href: '/projects' },
            { title: props.user.name, href: `/users/${props.user.id}/projects` },
        ]
        : [
            { title: 'Projects', href: '/projects' },
        ]
);

const pageTitle = computed(() => props.user ? `${props.user.name}'s Projects` : 'Projects');
const pageDescription = computed(() => 
    props.user 
        ? `Projects managed by or involving ${props.user.name}.` 
        : 'Manage your team projects.'
);

const { canCreateProjects } = usePermissions();
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
                <!-- <Button v-if="canCreateProjects" size="lg">
                    <PlusIcon class="h-4 w-4 mr-2" />
                    Create Project
                </Button> -->
                <div>
                    <Dialog v-model:open="dialogOpen">
                        <DialogTrigger as-child>
                            <Button v-if="canCreateProjects" size="lg">
                                <PlusIcon class="h-4 w-4 mr-2" />
                                Create Project
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Create Project</DialogTitle>
                                <DialogDescription>
                                    Fill in the details for your new project.
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitForm">
                                <FieldGroup>
                                    <Field :data-invalid="!!form.errors.name">
                                        <FieldLabel for="name">Project Name</FieldLabel>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            placeholder="My Awesome Project"
                                        />
                                        <FieldError v-if="form.errors.name" :errors="[form.errors.name]" />
                                    </Field>
                                    <Field :data-invalid="!!form.errors.description">
                                        <FieldLabel for="description">Project Description</FieldLabel>
                                        <Input
                                            id="description"
                                            v-model="form.description"
                                            type="text"
                                            placeholder="Describe your project"
                                        />
                                        <FieldError v-if="form.errors.description" :errors="[form.errors.description]" />
                                    </Field>
                                    <Field :data-invalid="!!form.errors.due_date">
                                        <FieldLabel>Due Date</FieldLabel>
                                        <Popover>
                                            <PopoverTrigger as-child>
                                                <Button
                                                    variant="outline"
                                                    :class="cn(
                                                        'w-full justify-start text-left font-normal',
                                                        !selectedDate && 'text-muted-foreground',
                                                    )"
                                                >
                                                    <CalendarIcon class="mr-2 h-4 w-4" />
                                                    {{ selectedDate ? df.format(selectedDate.toDate(getLocalTimeZone())) : 'Pick a date' }}
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-auto p-0">
                                                <Calendar v-model="selectedDate" :min-value="minDate" :initial-focus="true" />
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
            <div class="grid grid-cols-3 gap-7 mt-5">
                <div v-for="project in projects" :key="project.id" class="col-span-1">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ project.name }}</CardTitle>
                            <Separator class="my-2 border" />
                            <div class="flex justify-between">
                                <CardDescription>Progress</CardDescription>
                                <p class="text-sm">{{ project.progress }}%</p>
                            </div>
                            <Progress :model-value="project.progress" class="mt-1" />
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="relative col-span-1 border-2 rounded-lg p-3">
                                    <p class="text-sm text-gray-500">Tasks</p>
                                    <p class="text-base font-medium">{{ project.tasks_count }}</p>
                                </div>
                                <div class="col-span-1 border-2 rounded-lg p-3">
                                    <p class="text-sm text-gray-500">Team Members</p>
                                    <p class="text-base font-medium">{{ project.team_members_count }}</p>
                                </div>
                            </div>
                            <Separator class="my-4 border" />
                            <p class="text-sm text-gray-500">Deadline</p>
                            <p class="text-base font-medium">{{ project.deadline ?? 'No deadline set' }}</p>
                        </CardContent>
                        <CardFooter>
                            <Link :href="`/projects/${project.id}`">
                                <Button variant="secondary">View Details</Button>
                            </Link>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Empty state when no projects -->
                <div v-if="projects.length === 0" class="col-span-3 text-center py-12">
                    <Empty>
                        <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <FolderCode />
                        </EmptyMedia>
                        <EmptyTitle>No Projects Yet</EmptyTitle>
                        <EmptyDescription>
                            You haven't created or assigned on any projects yet. Get started by creating your first
                            project.
                        </EmptyDescription>
                        </EmptyHeader>
                        <!-- <EmptyContent>
                            <div class="flex gap-2">
                                <Button>Create Project</Button>
                                <Button variant="outline">
                                Import Project
                                </Button>
                            </div>
                        </EmptyContent>
                        <Button variant="link" as-child class="text-muted-foreground" size="sm">
                        <a href="#">
                            Learn More <ArrowUpRightIcon />
                        </a>
                        </Button> -->
                    </Empty>
                </div>
            </div>
        </div>
    </AppLayout>
</template>