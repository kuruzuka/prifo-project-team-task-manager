<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { ArrowLeftIcon } from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { toast } from 'vue-sonner';
import { update } from '@/actions/App/Http/Controllers/TaskController';

interface TaskStatus {
    id: number;
    name: string;
}

interface Task {
    id: number;
    title: string;
    description: string | null;
    project_id: number;
    project: string | null;
    status_id: number;
    priority: string;
    due_date: string | null;
    assignee_ids: number[];
}

const props = defineProps<{
    task: Task;
    statuses: TaskStatus[];
    backUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tasks', href: props.backUrl },
    { title: props.task.title, href: `/tasks/${props.task.id}` },
    { title: 'Edit', href: `/tasks/${props.task.id}/edit` },
];

const form = useForm({
    title: props.task.title,
    description: props.task.description ?? '',
    status_id: props.task.status_id,
    priority: props.task.priority,
    due_date: props.task.due_date ?? '',
});

function submitForm() {
    form.put(update.url({ task: props.task.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Task updated successfully.');
        },
        onError: () => {
            toast.error('Failed to update task. Please check the form for errors.');
        },
    });
}
</script>

<template>
    <Head :title="`Edit: ${task.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="my-5 ml-5 mr-40 max-w-2xl">
            <!-- Header with back button -->
            <div class="flex items-center gap-4 mb-6">
                <Link :href="`/tasks/${task.id}`">
                    <Button variant="outline" size="icon">
                        <ArrowLeftIcon class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Edit Task</h1>
                    <p class="text-gray-500">{{ task.project ?? 'No project' }}</p>
                </div>
            </div>

            <!-- Edit Form Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Task Details</CardTitle>
                    <CardDescription>Update the task information below.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm">
                        <FieldGroup>
                            <Field :data-invalid="!!form.errors.title">
                                <FieldLabel for="title">Title</FieldLabel>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    placeholder="Enter task title"
                                />
                                <FieldError v-if="form.errors.title" :errors="[form.errors.title]" />
                            </Field>

                            <Field :data-invalid="!!form.errors.description">
                                <FieldLabel for="description">Description</FieldLabel>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Describe the task"
                                    rows="4"
                                />
                                <FieldError v-if="form.errors.description" :errors="[form.errors.description]" />
                            </Field>

                            <Field :data-invalid="!!form.errors.status_id">
                                <FieldLabel for="status_id">Status</FieldLabel>
                                <Select v-model="form.status_id">
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
                                <FieldError v-if="form.errors.status_id" :errors="[form.errors.status_id]" />
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
                                <FieldLabel for="due_date">Due Date</FieldLabel>
                                <Input
                                    id="due_date"
                                    v-model="form.due_date"
                                    type="date"
                                />
                                <FieldError v-if="form.errors.due_date" :errors="[form.errors.due_date]" />
                            </Field>
                        </FieldGroup>

                        <div class="mt-6 flex gap-3">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                            <Link :href="`/tasks/${task.id}`">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
