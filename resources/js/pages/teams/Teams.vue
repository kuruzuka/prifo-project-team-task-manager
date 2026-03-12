<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { PlusIcon } from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar';
import { 
    Empty, 
    EmptyHeader, 
    EmptyMedia, 
    EmptyTitle, 
    EmptyDescription 
} from '@/components/ui/empty';
import { FolderCode } from 'lucide-vue-next';
import { usePermissions } from '@/composables/usePermissions';
import { show } from '@/actions/App/Http/Controllers/TeamController';

interface TeamMember {
    id: number;
    name: string;
    initials: string;
    avatar: string | null;
}

interface Team {
    id: number;
    name: string;
    description: string;
    members: TeamMember[];
    members_count: number;
    overflow_count: number;
}

defineProps<{
    teams: Team[];
}>();

const { canInviteMembers } = usePermissions();

const breadcrumbItems = [
    {
        title: 'Teams',
        href: '/teams',
    },
];
</script>

<template>
    <Head title="Teams" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="my-5 ml-5 mr-40">
            <div class="flex justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Teams</h1>
                    <p class="text-gray-500">You can view your teams and team members here.</p>
                </div>
                <Button v-if="canInviteMembers" size="lg">
                    <PlusIcon class="h-4 w-4 mr-2" />
                    Invite Member
                </Button>
            </div>
            <div class="mt-5 grid grid-cols-3 gap-4">
                <div v-for="team in teams" :key="team.id" class="col-span-1">
                    <Card class="h-full flex flex-col">
                        <CardHeader>
                            <CardTitle>{{ team.name }}</CardTitle>
                            <CardDescription>
                                {{ team.description }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex-1">
                            <p class="mb-2 text-sm">Team Members:</p>
                            <div class="*:data-[slot=avatar]:ring-background flex -space-x-2 *:data-[slot=avatar]:ring-2">
                                <!-- Display up to 6 member avatars -->
                                <Avatar v-for="member in team.members" :key="member.id">
                                    <AvatarImage 
                                        v-if="member.avatar" 
                                        :src="member.avatar" 
                                        :alt="member.name" 
                                    />
                                    <AvatarFallback>{{ member.initials }}</AvatarFallback>
                                </Avatar>
                                <!-- Overflow indicator showing +X -->
                                <Avatar v-if="team.overflow_count > 0">
                                    <AvatarFallback class="bg-muted text-muted-foreground">
                                        +{{ team.overflow_count }}
                                    </AvatarFallback>
                                </Avatar>
                            </div>
                            <p v-if="team.members_count === 0" class="text-sm text-gray-500">
                                No members yet
                            </p>
                        </CardContent>
                        <CardFooter>
                            <Link :href="show.url({ team: team.id })">
                                <Button variant="outline">View Team</Button>
                            </Link>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Empty state -->
                <div v-if="teams.length === 0" class="col-span-3 text-center py-12">
                    <Empty>
                        <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <FolderCode />
                        </EmptyMedia>
                        <EmptyTitle>No Teams Yet</EmptyTitle>
                        <EmptyDescription>
                            You haven't joined or assigned on any teams yet. Get started by creating your first
                            team.
                        </EmptyDescription>
                        </EmptyHeader>
                        <!-- <EmptyContent>
                            <div class="flex gap-2">
                                <Button>Create Team</Button>
                                <Button variant="outline">
                                Import Team
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