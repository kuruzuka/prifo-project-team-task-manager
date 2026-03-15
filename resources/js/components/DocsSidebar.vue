<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { ScrollArea } from '@/components/ui/scroll-area';

interface NavSection {
    title: string;
    items: { title: string; id: string }[];
}

const sections: NavSection[] = [
    {
        title: 'Getting Started',
        items: [
            { title: 'Overview', id: 'overview' },
            { title: 'Architecture', id: 'architecture' },
        ]
    },
    {
        title: 'Backend',
        items: [
            { title: 'Routing', id: 'routing' },
            { title: 'Controllers', id: 'controllers' },
            { title: 'Models & Database', id: 'models' },
            { title: 'Middleware', id: 'middleware' },
            { title: 'Policies', id: 'policies' },
            { title: 'Form Requests', id: 'requests' },
            { title: 'Services', id: 'services' },
        ]
    },
    {
        title: 'Frontend',
        items: [
            { title: 'Inertia Pages', id: 'pages' },
            { title: 'Vue Components', id: 'components' },
            { title: 'UI System (shadcn)', id: 'ui' },
        ]
    }
];

const activeId = ref('');

const scrollToSection = (e: MouseEvent, id: string) => {
    e.preventDefault();
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        activeId.value = id;
        history.pushState(null, '', `#${id}`);
    }
};

let observer: IntersectionObserver | null = null;

onMounted(() => {
    const options = {
        root: document.getElementById('docs-content'),
        rootMargin: '-20% 0px -70% 0px',
        threshold: 0
    };

    observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                activeId.value = entry.target.id;
            }
        });
    }, options);

    sections.forEach(section => {
        section.items.forEach(item => {
            const el = document.getElementById(item.id);
            if (el) observer?.observe(el);
        });
    });
});

onUnmounted(() => {
    observer?.disconnect();
});
</script>

<template>
    <ScrollArea class="h-full py-6">
        <div class="space-y-6 px-4">
            <div v-for="section in sections" :key="section.title">
                <h4 class="mb-2 px-2 text-xs font-semibold uppercase tracking-widest text-neutral-500 dark:text-neutral-400">
                    {{ section.title }}
                </h4>
                <div class="grid grid-flow-row auto-rows-max text-sm gap-0.5">
                    <a
                        v-for="item in section.items"
                        :key="item.id"
                        :href="`#${item.id}`"
                        @click="scrollToSection($event, item.id)"
                        class="group flex w-full items-center rounded-md border border-transparent px-2 py-1.5 transition-colors"
                        :class="[
                            activeId === item.id 
                                ? 'bg-blue-500/10 text-blue-600 font-medium dark:bg-blue-500/20 dark:text-blue-400' 
                                : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-neutral-100'
                        ]"
                    >
                        {{ item.title }}
                    </a>
                </div>
            </div>
        </div>
    </ScrollArea>
</template>
