<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
    TableFooter,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';

interface Stats {
    totalProjects: number;
    activeTasks: number;
    tasksDone: number;
    teamMembers: number;
}

interface RecentTask {
    id: number;
    title: string;
    project: string | null;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: string | null;
}

interface ProjectProgress {
    id: number;
    name: string;
    progress: number;
}

const props = defineProps<{
    stats: Stats;
    recentTasks: RecentTask[];
    projectProgress: ProjectProgress[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const priorityClasses: Record<string, string> = {
    low: 'bg-blue-200 text-blue-800 border-blue-400',
    medium: 'bg-yellow-200 text-yellow-800 border-yellow-400',
    high: 'bg-red-200 text-red-800 border-red-400',
    critical: 'bg-purple-200 text-purple-800 border-purple-400',
};

const getPriorityClass = (priority: string): string => {
    return priorityClasses[priority] || 'bg-gray-200 text-gray-800 border-gray-400';
};

const capitalize = (str: string): string => {
    return str.charAt(0).toUpperCase() + str.slice(1);
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 sm:p-5 md:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 md:gap-7">
                <Card class="flex w-full h-auto min-h-[100px] sm:min-h-[120px] md:min-h-[140px]">
                    <CardHeader class="w-full">
                        <CardDescription class="text-sm sm:text-base md:text-lg">Total Projects</CardDescription>
                        <CardTitle class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl">{{ stats.totalProjects }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="flex w-full h-auto min-h-[100px] sm:min-h-[120px] md:min-h-[140px]">
                    <CardHeader class="w-full">
                        <CardDescription class="text-sm sm:text-base md:text-lg">Active Tasks</CardDescription>
                        <CardTitle class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl">{{ stats.activeTasks }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="flex w-full h-auto min-h-[100px] sm:min-h-[120px] md:min-h-[140px]">
                    <CardHeader class="w-full">
                        <CardDescription class="text-sm sm:text-base md:text-lg">Tasks Done</CardDescription>
                        <CardTitle class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl">{{ stats.tasksDone }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="flex w-full h-auto min-h-[100px] sm:min-h-[120px] md:min-h-[140px]">
                    <CardHeader class="w-full">
                        <CardDescription class="text-sm sm:text-base md:text-lg">Team Members</CardDescription>
                        <CardTitle class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl">{{ stats.teamMembers }}</CardTitle>
                    </CardHeader>
                </Card>
           </div>
           <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 md:gap-7 mt-6 sm:mt-8 md:mt-10">
                <div class="min-h-[300px] sm:min-h-[400px] md:min-h-[500px] max-h-[500px] overflow-auto border-2 rounded-xl">
                    <Table>
                        <TableHeader>
                            <TableRow class="h-12 sm:h-14 md:h-15 hover:bg-transparent">
                                <TableHead class="text-base sm:text-lg md:text-xl p-3 sm:p-4 md:p-5 pl-4 sm:pl-6 md:pl-8">Recent Tasks</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="task in recentTasks" :key="task.id">
                                <TableCell class="relative p-2 sm:p-3">
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 sm:gap-2">
                                        <p class="text-sm sm:text-base md:text-lg font-bold">{{ task.title }}</p>
                                        <Badge variant="outline"
                                            class="text-xs sm:text-sm w-fit"
                                            :class="getPriorityClass(task.priority)"
                                            >{{ capitalize(task.priority) }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm sm:text-base mt-1 sm:mt-2">{{ task.project ?? 'No Project' }}</p>
                                    <p class="text-xs sm:text-sm italic">{{ task.status ?? 'Unknown' }}</p>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="recentTasks.length === 0">
                                <TableCell class="p-4 sm:p-5 text-center text-muted-foreground text-sm sm:text-base">
                                    No recent tasks
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="min-h-[300px] sm:min-h-[400px] md:min-h-[500px] max-h-[500px] overflow-auto border-2 rounded-xl">
                    <Table>
                        <TableHeader>
                            <TableRow class="h-12 sm:h-14 md:h-15 hover:bg-transparent">
                                <TableHead class="text-base sm:text-lg md:text-xl p-3 sm:p-4 md:p-5 pl-4 sm:pl-6 md:pl-8">Project Progress</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="project in projectProgress" :key="project.id">
                                <TableCell class="p-3 sm:p-4 md:p-5">
                                    <div class="flex justify-between mb-1 sm:mb-2">
                                        <p class="text-sm sm:text-base md:text-lg font-bold truncate mr-2">{{ project.name }}</p>
                                        <p class="text-sm sm:text-base mt-1 sm:mt-2 shrink-0">{{ project.progress }}%</p>
                                    </div>
                                    <Progress :model-value="project.progress" />
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="projectProgress.length === 0">
                                <TableCell class="p-4 sm:p-5 text-center text-muted-foreground text-sm sm:text-base">
                                    No projects with tasks
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
           </div>
        </div>
    </AppLayout>
</template>
