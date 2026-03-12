<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShieldX, Home, LayoutDashboard } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { dashboard, home } from '@/routes';
</script>

<template>
    <Head title="Access Denied" />
    <div class="flex min-h-screen items-center justify-center bg-background p-6">
        <Empty class="max-w-md">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <ShieldX class="text-destructive" />
                </EmptyMedia>
                <EmptyTitle class="text-2xl">Access Denied</EmptyTitle>
                <EmptyDescription>
                    You don't have permission to access this page or resource.
                    If you believe this is an error, please contact your administrator.
                </EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
                <div class="flex gap-2">
                    <Button as-child>
                        <Link :href="dashboard()">
                            <LayoutDashboard class="mr-2 h-4 w-4" />
                            Dashboard
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="home()">
                            <Home class="mr-2 h-4 w-4" />
                            Home
                        </Link>
                    </Button>
                </div>
            </EmptyContent>
        </Empty>
    </div>
</template>
