<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { 
    SearchIcon,
    FilterIcon,
    XIcon,
    ChevronDownIcon,
    ChevronRightIcon,
} from 'lucide-vue-next';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
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
} from '@/components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref, computed, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';

interface Actor {
    id: number;
    name: string;
    email: string;
}

interface ActivityLog {
    id: number;
    activity_type: string;
    entity_type: string;
    entity_id: number;
    metadata: Record<string, unknown> | null;
    actor: Actor | null;
    created_at: string;
    created_at_human: string;
}

interface EntityType {
    value: string;
    label: string;
}

interface UserOption {
    id: number;
    name: string;
}

interface PaginatedLogs {
    data: ActivityLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
    user: string | null;
    action: string | null;
    entity: string | null;
    date_from: string | null;
    date_to: string | null;
}

const props = defineProps<{
    logs: PaginatedLogs;
    activityTypes: string[];
    entityTypes: EntityType[];
    users: UserOption[];
    filters: Filters;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Audit Logs', href: '/admin/audit-logs' },
];

// Filter state
const userFilter = ref(props.filters.user ?? 'all');
const actionFilter = ref(props.filters.action ?? 'all');
const entityFilter = ref(props.filters.entity ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

// Metadata modal state
const metadataDialogOpen = ref(false);
const selectedLog = ref<ActivityLog | null>(null);

// Row expansion state
const expandedRows = ref<Set<number>>(new Set());

const hasActiveFilters = computed(() => {
    return (
        (userFilter.value && userFilter.value !== 'all') ||
        (actionFilter.value && actionFilter.value !== 'all') ||
        (entityFilter.value && entityFilter.value !== 'all') ||
        dateFrom.value ||
        dateTo.value
    );
});

const applyFilters = () => {
    router.get('/admin/audit-logs', {
        user: userFilter.value !== 'all' ? userFilter.value : undefined,
        action: actionFilter.value !== 'all' ? actionFilter.value : undefined,
        entity: entityFilter.value !== 'all' ? entityFilter.value : undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    userFilter.value = 'all';
    actionFilter.value = 'all';
    entityFilter.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    router.get('/admin/audit-logs', {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const debouncedApplyFilters = useDebounceFn(applyFilters, 300);

watch([userFilter, actionFilter, entityFilter], () => {
    debouncedApplyFilters();
});

const goToPage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

const toggleRow = (logId: number) => {
    if (expandedRows.value.has(logId)) {
        expandedRows.value.delete(logId);
    } else {
        expandedRows.value.add(logId);
    }
};

const openMetadataDialog = (log: ActivityLog) => {
    selectedLog.value = log;
    metadataDialogOpen.value = true;
};

const formatActivityType = (type: string): string => {
    return type
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const getActivityBadgeVariant = (type: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (type.includes('created')) return 'default';
    if (type.includes('deleted') || type.includes('removed')) return 'destructive';
    if (type.includes('updated') || type.includes('changed')) return 'secondary';
    return 'outline';
};

const formatMetadata = (metadata: Record<string, unknown> | null): string => {
    if (!metadata) return 'No details';
    return JSON.stringify(metadata, null, 2);
};

const getMetadataSummary = (metadata: Record<string, unknown> | null): string => {
    if (!metadata) return '—';
    const keys = Object.keys(metadata);
    if (keys.length === 0) return '—';
    
    // Show first few meaningful values
    const summaryParts: string[] = [];
    for (const key of keys.slice(0, 2)) {
        const value = metadata[key];
        if (typeof value === 'string' && value.length > 0) {
            summaryParts.push(`${value}`);
        } else if (typeof value === 'number') {
            summaryParts.push(`${key}: ${value}`);
        }
    }
    
    if (summaryParts.length === 0) {
        return `${keys.length} field${keys.length > 1 ? 's' : ''}`;
    }
    
    return summaryParts.join(', ') + (keys.length > 2 ? '...' : '');
};
</script>

<template>
    <Head title="Audit Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="my-5 mx-5 max-w-screen-2xl">
            <!-- Header -->
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Audit Logs</h1>
                    <p class="text-muted-foreground">
                        View all system activity and user actions across the platform.
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <Card class="mt-6">
                <CardHeader class="pb-3">
                    <CardTitle class="text-sm font-medium flex items-center gap-2">
                        <FilterIcon class="h-4 w-4" />
                        Filters
                        <Button 
                            v-if="hasActiveFilters"
                            variant="ghost" 
                            size="sm"
                            @click="clearFilters"
                            class="ml-auto"
                        >
                            <XIcon class="h-4 w-4 mr-1" />
                            Clear
                        </Button>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <!-- User Filter -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">User</label>
                            <Select v-model="userFilter">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="All users" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All users</SelectItem>
                                    <SelectGroup>
                                        <SelectLabel>Users</SelectLabel>
                                        <SelectItem 
                                            v-for="user in users" 
                                            :key="user.id" 
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Action Filter -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Action</label>
                            <Select v-model="actionFilter">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="All actions" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All actions</SelectItem>
                                    <SelectGroup>
                                        <SelectLabel>Actions</SelectLabel>
                                        <SelectItem 
                                            v-for="action in activityTypes" 
                                            :key="action" 
                                            :value="action"
                                        >
                                            {{ formatActivityType(action) }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Entity Filter -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">Entity Type</label>
                            <Select v-model="entityFilter">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="All entities" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All entities</SelectItem>
                                    <SelectGroup>
                                        <SelectLabel>Entities</SelectLabel>
                                        <SelectItem 
                                            v-for="entity in entityTypes" 
                                            :key="entity.value" 
                                            :value="entity.value"
                                        >
                                            {{ entity.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Date From -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">From</label>
                            <Input 
                                v-model="dateFrom"
                                type="date" 
                                @change="applyFilters"
                            />
                        </div>

                        <!-- Date To -->
                        <div>
                            <label class="text-sm font-medium mb-1.5 block">To</label>
                            <Input 
                                v-model="dateTo"
                                type="date" 
                                @change="applyFilters"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Logs Table -->
            <div class="mt-6">
                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[30px]"></TableHead>
                                <TableHead>Timestamp</TableHead>
                                <TableHead>User</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Entity</TableHead>
                                <TableHead>Entity ID</TableHead>
                                <TableHead>Details</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-for="log in logs.data" :key="log.id">
                                <TableRow 
                                    class="cursor-pointer hover:bg-muted/50 transition-colors"
                                    @click="log.metadata && Object.keys(log.metadata).length > 0 ? toggleRow(log.id) : null"
                                >
                                    <TableCell class="w-[30px]">
                                        <Button 
                                            v-if="log.metadata && Object.keys(log.metadata).length > 0"
                                            variant="ghost" 
                                            size="sm"
                                            class="h-6 w-6 p-0"
                                            @click.stop="toggleRow(log.id)"
                                        >
                                            <ChevronDownIcon 
                                                v-if="expandedRows.has(log.id)" 
                                                class="h-4 w-4" 
                                            />
                                            <ChevronRightIcon v-else class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">{{ log.created_at }}</span>
                                            <span class="text-xs text-muted-foreground">{{ log.created_at_human }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div v-if="log.actor" class="flex flex-col">
                                            <span class="font-medium">{{ log.actor.name }}</span>
                                            <span class="text-xs text-muted-foreground">{{ log.actor.email }}</span>
                                        </div>
                                        <span v-else class="text-muted-foreground">System</span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getActivityBadgeVariant(log.activity_type)">
                                            {{ formatActivityType(log.activity_type) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {{ log.entity_type }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="font-mono text-sm">
                                        #{{ log.entity_id }}
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-muted-foreground truncate max-w-[200px] block">
                                            {{ getMetadataSummary(log.metadata) }}
                                        </span>
                                    </TableCell>
                                </TableRow>
                                
                                <!-- Expanded Metadata Row -->
                                <TableRow v-if="expandedRows.has(log.id) && log.metadata">
                                    <TableCell colspan="7" class="bg-muted/30 p-0">
                                        <div class="p-4">
                                            <h4 class="text-sm font-medium mb-2">Metadata Details</h4>
                                            <pre class="text-xs bg-background p-3 rounded-md overflow-x-auto font-mono border">{{ formatMetadata(log.metadata) }}</pre>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                            
                            <TableRow v-if="logs.data.length === 0">
                                <TableCell colspan="7" class="text-center py-12 text-muted-foreground">
                                    <div class="flex flex-col items-center gap-2">
                                        <SearchIcon class="h-8 w-8 opacity-50" />
                                        <p>No audit logs found.</p>
                                        <p v-if="hasActiveFilters" class="text-sm">
                                            Try adjusting your filters.
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </Card>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between mt-4">
                    <p class="text-sm text-muted-foreground">
                        Showing {{ (logs.current_page - 1) * logs.per_page + 1 }} to 
                        {{ Math.min(logs.current_page * logs.per_page, logs.total) }} of 
                        {{ logs.total }} logs
                    </p>
                    <div class="flex gap-2">
                        <Button
                            v-for="link in logs.links"
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

        <!-- Metadata Dialog (alternative view) -->
        <Dialog v-model:open="metadataDialogOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Activity Details</DialogTitle>
                    <DialogDescription v-if="selectedLog">
                        {{ formatActivityType(selectedLog.activity_type) }} on {{ selectedLog.entity_type }} #{{ selectedLog.entity_id }}
                    </DialogDescription>
                </DialogHeader>
                <div v-if="selectedLog" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium">Timestamp:</span>
                            <p class="text-muted-foreground">{{ selectedLog.created_at }}</p>
                        </div>
                        <div>
                            <span class="font-medium">User:</span>
                            <p class="text-muted-foreground">
                                {{ selectedLog.actor?.name ?? 'System' }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <span class="font-medium text-sm">Metadata:</span>
                        <pre class="text-xs bg-muted p-3 rounded-md overflow-x-auto font-mono mt-2">{{ formatMetadata(selectedLog.metadata) }}</pre>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
