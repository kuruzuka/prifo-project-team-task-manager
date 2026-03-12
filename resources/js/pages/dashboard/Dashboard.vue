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
        <div class="my-5 ml-5 mr-40">
            <div class="grid grid-cols-4 gap-7">
                <div class="sm:col-span-3 md:col-span-3 lg:col-span-1 col-span-3">
                    <Card class="flex w-full h-[140px]">
                        <CardHeader>
                            <CardDescription class="text-lg">Total Projects</CardDescription>
                            <CardTitle class="text-6xl justify-self-end-safe">{{ stats.totalProjects }}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>
               <div class="sm:col-span-3 md:col-span-3 lg:col-span-1 col-span-3">
                    <Card class="flex w-full h-[140px]">
                        <CardHeader>
                            <CardDescription class="text-lg">Active Tasks</CardDescription>
                            <CardTitle class="text-6xl justify-self-end-safe">{{ stats.activeTasks }}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>
                <div class="sm:col-span-3 md:col-span-3 lg:col-span-1 col-span-3">
                    <Card class="flex w-full h-[140px]">
                        <CardHeader>
                            <CardDescription class="text-lg">Tasks Done</CardDescription>
                            <CardTitle class="text-6xl justify-self-end-safe">{{ stats.tasksDone }}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>
                <div class="sm:col-span-3 md:col-span-3 lg:col-span-1 col-span-3">
                    <Card class="flex w-full h-[140px]">
                        <CardHeader>
                            <CardDescription class="text-lg">Team Members</CardDescription>
                            <CardTitle class="text-6xl justify-self-end-safe">{{ stats.teamMembers }}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>
           </div>
           <div class="grid grid-cols-2 gap-7 mt-10">
                <div class="col-span-1 h-[500px] border-2 rounded-xl">
                    <Table>
                        <TableHeader>
                            <TableRow class="h-15 hover:bg-transparent">
                                <TableHead class="text-xl p-5 pl-8">Recent Tasks</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="task in recentTasks" :key="task.id">
                                <TableCell class="relative p-3">
                                    <div class="flex justify-between">
                                        <p class="text-lg font-bold">{{ task.title }}</p>
                                        <Badge variant="outline"
                                            class="text-sm"
                                            :class="getPriorityClass(task.priority)"
                                            >{{ capitalize(task.priority) }}
                                        </Badge>
                                    </div>
                                    <p class="text-base mt-2">{{ task.project ?? 'No Project' }}</p>
                                    <p class="text-sm italic">{{ task.status ?? 'Unknown' }}</p>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="recentTasks.length === 0">
                                <TableCell class="p-5 text-center text-muted-foreground">
                                    No recent tasks
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="col-span-1 h-[500px] border-2 rounded-xl">
                    <Table>
                        <TableHeader>
                            <TableRow class="h-15 hover:bg-transparent">
                                <TableHead class="text-xl p-5 pl-8">Project Progress</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="project in projectProgress" :key="project.id">
                                <TableCell class="p-5">
                                    <div class="flex justify-between mb-2">
                                        <p class="text-lg font-bold">{{ project.name }}</p>
                                        <p class="text-base mt-2">{{ project.progress }}%</p>
                                    </div>
                                    <Progress :model-value="project.progress" />
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="projectProgress.length === 0">
                                <TableCell class="p-5 text-center text-muted-foreground">
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
