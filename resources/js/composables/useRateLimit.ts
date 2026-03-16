import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

// State to share across the application
const isRateLimited = ref(false);
const rateLimitRemaining = ref(0);
const rateLimitContext = ref<'login' | 'register' | 'password-reset' | 'creation' | 'default'>('default');

let countdownTimer: ReturnType<typeof setInterval> | null = null;

const getContextFromUrl = (url: string): typeof rateLimitContext.value => {
    if (url.includes('/login')) return 'login';
    if (url.includes('/register')) return 'register';
    if (url.includes('/password') || url.includes('/forgot-password') || url.includes('/reset-password')) return 'password-reset';
    if (url.includes('/projects') || url.includes('/tasks') || url.includes('/teams') || url.includes('/comments')) return 'creation';
    return 'default';
};

const getStorageKey = (context: string) => `rate_limit_${context}_until`;

const startCountdown = (context: typeof rateLimitContext.value, retryAfterSeconds: number) => {
    rateLimitContext.value = context;
    const expiry = Math.floor(Date.now() / 1000) + retryAfterSeconds;
    localStorage.setItem(getStorageKey(context), expiry.toString());
    
    updateTimer(expiry);
    if (countdownTimer) clearInterval(countdownTimer);
    
    countdownTimer = setInterval(() => {
        updateTimer(expiry);
    }, 1000);
};

const updateTimer = (expiry: number) => {
    const now = Math.floor(Date.now() / 1000);
    const remaining = expiry - now;
    
    if (remaining > 0) {
        isRateLimited.value = true;
        rateLimitRemaining.value = remaining;
    } else {
        isRateLimited.value = false;
        rateLimitRemaining.value = 0;
        if (countdownTimer) clearInterval(countdownTimer);
        localStorage.removeItem(getStorageKey(rateLimitContext.value));
    }
};

const initFromStorage = () => {
    // Current URL context
    const currentContext = getContextFromUrl(window.location.pathname);
    const storedExpiry = localStorage.getItem(getStorageKey(currentContext));
    
    if (storedExpiry) {
        const expiry = parseInt(storedExpiry, 10);
        if (expiry > Math.floor(Date.now() / 1000)) {
            rateLimitContext.value = currentContext;
            updateTimer(expiry);
            if (countdownTimer) clearInterval(countdownTimer);
            countdownTimer = setInterval(() => {
                updateTimer(expiry);
            }, 1000);
        } else {
            localStorage.removeItem(getStorageKey(currentContext));
        }
    }
};

export function useRateLimit() {
    onMounted(() => {
        initFromStorage();
    });

    onUnmounted(() => {
        if (countdownTimer) {
            clearInterval(countdownTimer);
        }
    });

    return {
        isRateLimited,
        rateLimitRemaining,
        rateLimitContext,
    };
}

export function setupRateLimitInterceptor() {
    const extractRetryAfter = (headers: any): number => {
        if (!headers) return 60;
        let retryAfterStr = null;
        if (typeof headers.get === 'function') {
            // Fetch API style headers
            retryAfterStr = headers.get('retry-after');
        } else {
            // Axios style headers
            retryAfterStr = headers['retry-after'] || headers['Retry-After'];
        }
        return retryAfterStr ? parseInt(retryAfterStr, 10) : 60;
    };

    // Intercept Inertia requests to catch 429 Too Many Requests
    router.on('exception', (event) => {
        const error = event.detail.exception as any;
        if (error?.response?.status === 429) {
            event.preventDefault(); // Prevent default Inertia error modal
            
            const retryAfter = extractRetryAfter(error.response.headers);
            
            // Determine context based on the request URL that was rate limited
            const urlContext = error.config?.url || window.location.pathname;
            const context = getContextFromUrl(urlContext);
            
            startCountdown(context, retryAfter);
        }
    });

    // Also catch invalid responses just in case it triggers invalid instead of exception
    router.on('invalid', (event) => {
        const response = event.detail.response as any;
        if (response?.status === 429) {
            event.preventDefault();
            
            const retryAfter = extractRetryAfter(response.headers);
            
            const context = getContextFromUrl(window.location.pathname);
            startCountdown(context, retryAfter);
        }
    });
}