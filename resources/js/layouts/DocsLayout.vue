<script setup lang="ts">
import AppShell from '@/components/AppShell.vue';
import DocsSidebar from '@/components/DocsSidebar.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLogo from '@/components/AppLogo.vue';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="header">
        <div class="flex flex-col md:flex-row h-screen w-full overflow-hidden bg-white dark:bg-neutral-950">
            <!-- Fixed Documentation Sidebar -->
            <aside class="hidden md:flex flex-col w-64 shrink-0 border-r border-neutral-200 dark:border-neutral-800 h-full">
                <div class="h-16 flex items-center px-6 border-b border-neutral-200 dark:border-neutral-800">
                    <Link :href="dashboard()" class="flex items-center gap-2">
                        <AppLogo class="w-8 h-8" />
                        <span class="font-bold text-lg tracking-tight">Docs</span>
                    </Link>
                </div>
                <div class="flex-1 overflow-hidden">
                    <DocsSidebar />
                </div>
            </aside>
            
            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col min-w-0 h-full">
                <!-- Simple Header -->
                <header class="h-16 shrink-0 flex items-center px-6 border-b border-neutral-200 dark:border-neutral-800 bg-white/80 dark:bg-neutral-950/80 backdrop-blur-sm sticky top-0 z-10">
                    <div class="md:hidden mr-4">
                         <Link :href="dashboard()">
                            <AppLogo class="w-8 h-8" />
                        </Link>
                    </div>
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </header>
                
                <!-- Scrollable Content -->
                <main id="docs-content" class="flex-1 overflow-y-auto scroll-smooth">
                    <div class="max-w-4xl mx-auto px-6 py-12 lg:px-8">
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </AppShell>
</template>
