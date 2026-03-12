<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Slider } from '@/components/ui/slider';
import {
    ArrowLeftIcon,
    PencilIcon,
    TrashIcon,
    SendIcon,
    UserPlusIcon,
    XIcon,
    MessageCircleIcon,
    ActivityIcon,
} from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    edit,
    destroy,
    updateStatus,
    updatePriority,
    updateProgress as updateProgressRoute,
    addAssignee,
    removeAssignee,
} from '@/actions/App/Http/Controllers/TaskController';
import { store as storeComment, update as updateComment } from '@/actions/App/Http/Controllers/CommentController';

interface User {
    id: number;
    name: string;
    team?: string;
}

interface Comment {
    id: number;
    text: string;
    user: User;
    created_at: string;
    updated_at: string;
    is_edited: boolean;
    can_edit: boolean;
}

interface Activity {
    id: number;
    type: string;
    metadata: Record<string, unknown> | null;
    actor: User | null;
    created_at: string;
}

interface Status {
    id: number;
    name: string;
}

interface Task {
    id: number;
    title: string;
    description: string | null;
    project_id: number | null;
    project: string | null;
    status_id: number | null;
    status: string | null;
    priority: string;
    progress: number;
    due_date: string | null;
    due_date_raw: string | null;
    assignees: User[];
    created_at: string;
    version: number;
}

interface Permissions {
    edit: boolean;
    update: boolean;
    delete: boolean;
    updateProgress: boolean;
    assign: boolean;
}

const props = defineProps<{
    task: Task;
    comments: Comment[];
    activities: Activity[];
    statuses: Status[];
    availableUsers: User[];
    hasProjectTeams: boolean;
    can: Permissions;
    backUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tasks', href: props.backUrl },
    { title: props.task.title, href: `/tasks/${props.task.id}` },
];

// Dialog states
const deleteDialogOpen = ref(false);
const assignDialogOpen = ref(false);

// Comment form
const commentForm = useForm({
    comment_text: '',
});

// Edit comment state
const editingCommentId = ref<number | null>(null);
const editCommentForm = useForm({
    comment_text: '',
});

// Progress state
const progressValue = ref([props.task.progress]);

// Assignee form
const assigneeForm = useForm({
    user_id: '',
});

const getPriorityVariant = (priority: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        critical: 'destructive',
        high: 'default',
        medium: 'secondary',
        low: 'outline',
    };
    return variants[priority] || 'secondary';
};

const priorityOptions = ['low', 'medium', 'high', 'critical'];

// Available users that aren't already assigned
const unassignedUsers = computed(() => 
    props.availableUsers.filter(user => 
        !props.task.assignees.some(a => a.id === user.id)
    )
);

function editTask() {
    router.visit(edit.url({ task: props.task.id }));
}

function confirmDelete() {
    router.delete(destroy.url({ task: props.task.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Task '${props.task.title}' has been deleted.`);
        },
        onError: () => {
            toast.error('Failed to delete task.');
        },
    });
}

function handleStatusChange(statusId: unknown) {
    if (!statusId) return;
    router.patch(updateStatus.url({ task: props.task.id }), {
        status_id: parseInt(String(statusId)),
        version: props.task.version,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('Status updated.'),
        onError: (errors) => {
            const message = errors.database || errors.status_id || 'Failed to update status.';
            toast.error(message);
        },
    });
}

function handlePriorityChange(priority: unknown) {
    if (!priority) return;
    router.patch(updatePriority.url({ task: props.task.id }), {
        priority: String(priority),
        version: props.task.version,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('Priority updated.'),
        onError: (errors) => {
            const message = errors.database || errors.priority || 'Failed to update priority.';
            toast.error(message);
        },
    });
}

function handleProgressChange(value: number[] | undefined) {
    if (!value || value.length === 0) return;
    // Round to nearest 5
    const rounded = Math.round(value[0] / 5) * 5;
    router.patch(updateProgressRoute.url({ task: props.task.id }), {
        progress: rounded,
        version: props.task.version,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            progressValue.value = [rounded];
            toast.success(`Progress updated to ${rounded}%.`);
        },
        onError: (errors) => {
            const message = errors.database || errors.progress || 'Failed to update progress.';
            toast.error(message);
        },
    });
}

function submitComment() {
    commentForm.post(storeComment.url({ task: props.task.id }), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            toast.success('Comment added.');
        },
        onError: () => toast.error('Failed to add comment.'),
    });
}

function startEditComment(comment: Comment) {
    editingCommentId.value = comment.id;
    editCommentForm.comment_text = comment.text;
}

function cancelEditComment() {
    editingCommentId.value = null;
    editCommentForm.reset();
}

function saveEditComment(commentId: number) {
    editCommentForm.patch(updateComment.url({ comment: commentId }), {
        preserveScroll: true,
        onSuccess: () => {
            editingCommentId.value = null;
            editCommentForm.reset();
            toast.success('Comment updated.');
        },
        onError: () => toast.error('Failed to update comment.'),
    });
}

function handleAddAssignee() {
    if (!assigneeForm.user_id) return;
    
    assigneeForm.post(addAssignee.url({ task: props.task.id }), {
        preserveScroll: true,
        onSuccess: () => {
            assigneeForm.reset();
            assignDialogOpen.value = false;
            toast.success('Assignee added.');
        },
        onError: () => toast.error('Failed to add assignee.'),
    });
}

function handleRemoveAssignee(userId: number) {
    router.delete(removeAssignee.url({ task: props.task.id, user: userId }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Assignee removed.'),
        onError: () => toast.error('Failed to remove assignee.'),
    });
}

function getActivityDescription(activity: Activity): string {
    const actor = activity.actor?.name ?? 'Someone';
    const meta = activity.metadata ?? {};

    switch (activity.type) {
        case 'task_created':
            return `${actor} created this task`;
        case 'comment_added':
            return `${actor} added a comment`;
        case 'comment_edited':
            return `${actor} edited a comment`;
        case 'status_changed':
            return `${actor} changed status from "${meta.old_status}" to "${meta.new_status}"`;
        case 'priority_changed':
            return `${actor} changed priority from "${meta.old_priority}" to "${meta.new_priority}"`;
        case 'progress_updated':
            return `${actor} updated progress from ${meta.old_progress}% to ${meta.new_progress}%`;
        case 'assignee_added':
            return `${actor} assigned ${meta.user_name}`;
        case 'assignee_removed':
            return `${actor} unassigned ${meta.user_name}`;
        case 'task_updated':
            return formatTaskUpdatedActivity(actor, meta);
        default:
            return `${actor} performed "${activity.type}"`;
    }
}

function formatTaskUpdatedActivity(actor: string, meta: Record<string, unknown>): string {
    const changes = meta.changes as Record<string, { old: unknown; new: unknown }> | undefined;
    
    if (!changes || Object.keys(changes).length === 0) {
        return `${actor} updated the task`;
    }

    const fieldLabels: Record<string, string> = {
        title: 'title',
        description: 'description',
        priority: 'priority',
        due_date: 'due date',
        status: 'status',
    };

    const descriptions: string[] = [];
    
    for (const [field, change] of Object.entries(changes)) {
        const label = fieldLabels[field] || field;
        const oldVal = change.old ?? 'none';
        const newVal = change.new ?? 'none';
        
        // Truncate long descriptions
        const formatValue = (val: unknown): string => {
            if (val === null || val === undefined || val === '') return 'none';
            const str = String(val);
            return str.length > 50 ? str.substring(0, 47) + '...' : str;
        };
        
        descriptions.push(`${label} from "${formatValue(oldVal)}" to "${formatValue(newVal)}"`);
    }

    if (descriptions.length === 1) {
        return `${actor} updated the ${descriptions[0]}`;
    }
    
    return `${actor} updated: ${descriptions.join(', ')}`;
}
</script>

<template>
    <Head :title="task.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="my-5 mx-5 lg:mr-40 pb-32">
            <!-- Header with back button and actions -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <Link :href="backUrl">
                        <Button variant="outline" size="icon">
                            <ArrowLeftIcon class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ task.title }}</h1>
                        <p class="text-gray-500">{{ task.project ?? 'No project' }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button v-if="can.edit" variant="outline" @click="editTask">
                        <PencilIcon class="h-4 w-4 mr-2" />
                        Edit
                    </Button>
                    <Button v-if="can.delete" variant="destructive" @click="deleteDialogOpen = true">
                        <TrashIcon class="h-4 w-4 mr-2" />
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Task Details Card -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Task Details</CardTitle>
                    <CardDescription>Created on {{ task.created_at }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Description -->
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Description</p>
                            <p class="text-base">{{ task.description ?? 'No description provided.' }}</p>
                        </div>

                        <!-- Info Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Status</p>
                                <Select 
                                    v-if="can.update" 
                                    :model-value="task.status_id?.toString()" 
                                    @update:model-value="handleStatusChange"
                                >
                                    <SelectTrigger class="w-[140px]">
                                        <SelectValue :placeholder="task.status ?? 'Select'" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem 
                                            v-for="status in statuses" 
                                            :key="status.id" 
                                            :value="status.id.toString()"
                                        >
                                            {{ status.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-else class="text-base font-medium">{{ task.status ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Priority</p>
                                <Select 
                                    v-if="can.update" 
                                    :model-value="task.priority" 
                                    @update:model-value="handlePriorityChange"
                                >
                                    <SelectTrigger class="w-[120px]">
                                        <SelectValue>
                                            <Badge :variant="getPriorityVariant(task.priority)" class="capitalize">
                                                {{ task.priority }}
                                            </Badge>
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem 
                                            v-for="priority in priorityOptions" 
                                            :key="priority" 
                                            :value="priority"
                                        >
                                            <span class="capitalize">{{ priority }}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Badge v-else :variant="getPriorityVariant(task.priority)" class="capitalize">
                                    {{ task.priority }}
                                </Badge>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Due Date</p>
                                <p class="text-base font-medium">{{ task.due_date ?? 'No deadline' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Project</p>
                                <p class="text-base font-medium">{{ task.project ?? '—' }}</p>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div v-if="can.updateProgress">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm text-gray-500">Progress</p>
                                <span class="text-sm font-medium">{{ progressValue[0] }}%</span>
                            </div>
                            <Slider
                                v-model="progressValue"
                                :max="100"
                                :step="5"
                                class="w-full"
                                @update:model-value="handleProgressChange"
                            />
                        </div>
                        <div v-else>
                            <p class="text-sm text-gray-500 mb-1">Progress</p>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="bg-primary h-2 rounded-full transition-all" 
                                        :style="{ width: `${task.progress}%` }"
                                    />
                                </div>
                                <span class="text-sm font-medium">{{ task.progress }}%</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assignees Card -->
            <Card class="mb-6">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Assignees</CardTitle>
                        <CardDescription>
                            {{ task.assignees.length }} {{ task.assignees.length === 1 ? 'person' : 'people' }} assigned
                        </CardDescription>
                    </div>
                    <Button 
                        v-if="can.assign && unassignedUsers.length > 0" 
                        variant="outline" 
                        size="sm"
                        @click="assignDialogOpen = true"
                    >
                        <UserPlusIcon class="h-4 w-4 mr-2" />
                        Add
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="task.assignees.length > 0" class="flex flex-wrap gap-2">
                        <Badge 
                            v-for="assignee in task.assignees" 
                            :key="assignee.id" 
                            variant="secondary"
                            class="flex items-center gap-1"
                        >
                            {{ assignee.name }}
                            <button 
                                v-if="can.assign"
                                class="ml-1 hover:text-destructive"
                                @click="handleRemoveAssignee(assignee.id)"
                            >
                                <XIcon class="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>
                    <div v-else-if="can.assign && availableUsers.length === 0 && !hasProjectTeams" class="text-sm text-muted-foreground">
                        No available users. This project has no teams assigned yet.
                    </div>
                    <div v-else-if="can.assign && unassignedUsers.length === 0 && task.assignees.length > 0" class="text-sm text-muted-foreground">
                        All available team members are already assigned.
                    </div>
                    <p v-else class="text-gray-500">No one is assigned to this task yet.</p>
                </CardContent>
            </Card>

            <!-- Comments Section -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageCircleIcon class="h-5 w-5" />
                        Comments
                    </CardTitle>
                    <CardDescription>
                        {{ comments.length }} {{ comments.length === 1 ? 'comment' : 'comments' }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Comment Form -->
                    <form @submit.prevent="submitComment" class="mb-6">
                        <Textarea
                            v-model="commentForm.comment_text"
                            placeholder="Write a comment..."
                            rows="3"
                            class="mb-2"
                        />
                        <div class="flex justify-end">
                            <Button 
                                type="submit" 
                                :disabled="!commentForm.comment_text.trim() || commentForm.processing"
                            >
                                <SendIcon class="h-4 w-4 mr-2" />
                                Post Comment
                            </Button>
                        </div>
                    </form>

                    <!-- Comments List -->
                    <div 
                        v-if="comments.length > 0" 
                        class="space-y-4 divide-y"
                    >
                        <div 
                            v-for="comment in comments" 
                            :key="comment.id" 
                            class="pt-4 first:pt-0"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ comment.user.name }}</span>
                                    <span class="text-sm text-gray-500">{{ comment.created_at }}</span>
                                    <Badge v-if="comment.is_edited" variant="outline" class="text-xs">
                                        edited
                                    </Badge>
                                </div>
                                <Button 
                                    v-if="comment.can_edit && editingCommentId !== comment.id"
                                    variant="ghost" 
                                    size="sm"
                                    @click="startEditComment(comment)"
                                >
                                    <PencilIcon class="h-3 w-3" />
                                </Button>
                            </div>
                            
                            <!-- Edit mode -->
                            <div v-if="editingCommentId === comment.id" class="mt-2">
                                <Textarea
                                    v-model="editCommentForm.comment_text"
                                    rows="3"
                                    class="mb-2"
                                />
                                <div class="flex gap-2 justify-end">
                                    <Button variant="outline" size="sm" @click="cancelEditComment">
                                        Cancel
                                    </Button>
                                    <Button 
                                        size="sm" 
                                        :disabled="!editCommentForm.comment_text.trim() || editCommentForm.processing"
                                        @click="saveEditComment(comment.id)"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </div>
                            
                            <!-- Display mode -->
                            <p v-else class="mt-1 text-gray-700 whitespace-pre-wrap">{{ comment.text }}</p>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-center py-4">
                        No comments yet. Be the first to comment!
                    </p>
                </CardContent>
            </Card>

            <!-- Activity Log -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ActivityIcon class="h-5 w-5" />
                        Activity
                    </CardTitle>
                    <CardDescription>Recent activity on this task</CardDescription>
                </CardHeader>
                <CardContent>
                    <div 
                        v-if="activities.length > 0" 
                        class="space-y-3"
                    >
                        <div 
                            v-for="activity in activities" 
                            :key="activity.id" 
                            class="flex items-start gap-3 text-sm"
                        >
                            <div class="w-2 h-2 rounded-full bg-gray-400 mt-1.5 flex-shrink-0" />
                            <div>
                                <p>{{ getActivityDescription(activity) }}</p>
                                <span class="text-gray-500 text-xs">{{ activity.created_at }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-center py-4">
                        No activity recorded yet.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Task</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete "{{ task.title }}"? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete">Delete</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Add Assignee Dialog -->
        <Dialog v-model:open="assignDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Assignee</DialogTitle>
                    <DialogDescription>
                        Select a team member to assign to this task.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label for="assignee">Team Member</Label>
                    <Select v-model="assigneeForm.user_id">
                        <SelectTrigger class="mt-1">
                            <SelectValue placeholder="Select a person" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem 
                                v-for="user in unassignedUsers" 
                                :key="user.id" 
                                :value="user.id.toString()"
                            >
                                {{ user.name }}<span v-if="user.team" class="text-muted-foreground"> ({{ user.team }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="assignDialogOpen = false">Cancel</Button>
                    <Button 
                        :disabled="!assigneeForm.user_id || assigneeForm.processing"
                        @click="handleAddAssignee"
                    >
                        Add Assignee
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
