<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useRateLimit } from '@/composables/useRateLimit';

const props = defineProps<{
    context?: 'login' | 'register' | 'password-reset' | 'creation' | 'default';
}>();

const { isRateLimited, rateLimitRemaining, rateLimitContext } = useRateLimit();

const shouldShow = computed(() => {
    if (!isRateLimited.value) return false;
    // Show if context matches, or if no explicit context provided (global)
    if (props.context && props.context !== rateLimitContext.value) return false;
    // Also show if current page context matches the rate limited context
    const currentUrlContext = window.location.pathname.includes('/login') ? 'login' :
                              window.location.pathname.includes('/register') ? 'register' :
                              (window.location.pathname.includes('/password') || window.location.pathname.includes('/reset')) ? 'password-reset' :
                              (window.location.pathname.includes('/projects') || window.location.pathname.includes('/tasks')) ? 'creation' : 'default';
                              
    return !props.context || currentUrlContext === rateLimitContext.value;
});

const message = computed(() => {
    switch (rateLimitContext.value) {
        case 'login':
            return `Too many failed login attempts. Try again in ${rateLimitRemaining.value} seconds.`;
        case 'register':
            return `Too many registration attempts. Try again in ${rateLimitRemaining.value} seconds.`;
        case 'password-reset':
            return `Too many reset attempts. Try again in ${rateLimitRemaining.value} seconds.`;
        case 'creation':
            return `Too many actions performed. Please wait ${rateLimitRemaining.value} seconds.`;
        default:
            return `Too many requests. Please wait ${rateLimitRemaining.value} seconds.`;
    }
});
</script>

<template>
    <Alert v-if="shouldShow" variant="destructive" class="mb-6 animate-in fade-in slide-in-from-top-2">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>Rate Limit Exceeded</AlertTitle>
        <AlertDescription>
            {{ message }}
        </AlertDescription>
    </Alert>
</template>
