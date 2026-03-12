<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppShellVariant } from '@/types';

type Props = {
    variant?: AppShellVariant;
};

defineProps<Props>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div
        v-if="variant === 'header'"
        class="flex min-h-screen w-full flex-col bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900"
    >
        <slot />
    </div>
    <SidebarProvider
        v-else
        :default-open="isOpen"
        class="bg-gradient-to-b from-slate-50 to-white dark:from-slate-950 dark:to-slate-900"
    >
        <slot />
    </SidebarProvider>
</template>
