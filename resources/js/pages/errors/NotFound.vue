<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { SearchX, Home, LayoutDashboard, ArrowLeft } from 'lucide-vue-next';
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

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <Head title="Not Found" />
    <div class="flex min-h-screen items-center justify-center bg-background p-6">
        <Empty class="max-w-md">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <SearchX class="text-muted-foreground" />
                </EmptyMedia>
                <EmptyTitle class="text-2xl">Page Not Found</EmptyTitle>
                <EmptyDescription>
                    This resource is no longer available or you no longer have permission to view it.
                    If you believe this is an error, please contact your administrator.
                </EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
                <div class="flex gap-2">
                    <Button @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Go Back
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="dashboard()">
                            <LayoutDashboard class="mr-2 h-4 w-4" />
                            Dashboard
                        </Link>
                    </Button>
                </div>
            </EmptyContent>
        </Empty>
    </div>
</template>
