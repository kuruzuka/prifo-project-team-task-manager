<script setup lang="ts">
import { ref, onMounted } from 'vue';

const isVisible = ref(false);

onMounted(() => {
    setTimeout(() => {
        isVisible.value = true;
    }, 100);
});
</script>

<template>
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
        <div
            class="absolute -left-40 -top-40 h-80 w-80 rounded-full bg-purple-300/30 blur-3xl dark:bg-purple-900/20"
            :class="{ 'animate-pulse': isVisible }"
        />
        <div
            class="absolute -right-40 top-1/4 h-96 w-96 rounded-full bg-blue-300/30 blur-3xl dark:bg-blue-900/20"
            :class="{ 'animate-pulse': isVisible }"
            style="animation-delay: 1s"
        />
        <div
            class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-emerald-300/20 blur-3xl dark:bg-emerald-900/20"
            :class="{ 'animate-pulse': isVisible }"
            style="animation-delay: 2s"
        />
    </div>
</template>
