<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const isMobileMenuOpen = ref(false);
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen">
        <!-- Animated background blobs -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div
                class="absolute -left-20 -top-20 h-48 w-48 rounded-full bg-purple-300/30 blur-3xl sm:h-64 sm:w-64 md:-left-40 md:-top-40 md:h-80 md:w-80 dark:bg-purple-900/20 animate-pulse"
            />
            <div
                class="absolute -right-20 top-1/4 h-56 w-56 rounded-full bg-blue-300/30 blur-3xl sm:h-72 sm:w-72 md:-right-40 md:h-96 md:w-96 dark:bg-blue-900/20 animate-pulse"
                style="animation-delay: 1s"
            />
            <div
                class="absolute bottom-0 left-1/4 h-48 w-48 rounded-full bg-emerald-300/20 blur-3xl sm:h-64 sm:w-64 sm:left-1/3 md:h-80 md:w-80 dark:bg-emerald-900/20 animate-pulse"
                style="animation-delay: 2s"
            />
        </div>

        <!-- Navigation -->
        <header class="relative z-50">
            <nav class="mx-auto flex max-w-7xl items-center justify-between p-6 lg:px-8">
                <!-- Logo -->
                <div class="flex lg:flex-1">
                    <span class="text-2xl font-bold text-slate-900 dark:text-white">
                        Priflo
                    </span>
                </div>

                <!-- Mobile menu button -->
                <div class="flex lg:hidden">
                    <button
                        type="button"
                        class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-slate-700 dark:text-slate-300"
                        @click="isMobileMenuOpen = !isMobileMenuOpen"
                    >
                        <span class="sr-only">Open main menu</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Desktop navigation -->
                <div class="hidden lg:flex lg:gap-x-12">
                    <a href="#features" class="text-sm font-semibold leading-6 text-slate-900 hover:text-slate-600 dark:text-slate-200 dark:hover:text-white">
                        Features
                    </a>
                    <a href="#how-it-works" class="text-sm font-semibold leading-6 text-slate-900 hover:text-slate-600 dark:text-slate-200 dark:hover:text-white">
                        How it works
                    </a>
                    <a href="#benefits" class="text-sm font-semibold leading-6 text-slate-900 hover:text-slate-600 dark:text-slate-200 dark:hover:text-white">
                        Benefits
                    </a>
                </div>

                <!-- Desktop auth buttons -->
                <div class="hidden lg:flex lg:flex-1 lg:justify-end lg:gap-x-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="text-sm font-semibold leading-6 text-slate-900 hover:text-slate-600 dark:text-slate-200 dark:hover:text-white px-4 py-2"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                        >
                            Get started
                        </Link>
                    </template>
                </div>
            </nav>

            <!-- Mobile menu -->
            <div
                v-if="isMobileMenuOpen"
                class="lg:hidden"
            >
                <div class="space-y-1 border-t border-slate-200 bg-white/95 px-6 py-4 backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
                    <a href="#features" class="block rounded-lg px-3 py-2 text-base font-semibold text-slate-900 hover:bg-slate-50 dark:text-white dark:hover:bg-slate-800">
                        Features
                    </a>
                    <a href="#how-it-works" class="block rounded-lg px-3 py-2 text-base font-semibold text-slate-900 hover:bg-slate-50 dark:text-white dark:hover:bg-slate-800">
                        How it works
                    </a>
                    <a href="#benefits" class="block rounded-lg px-3 py-2 text-base font-semibold text-slate-900 hover:bg-slate-50 dark:text-white dark:hover:bg-slate-800">
                        Benefits
                    </a>
                    <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="block rounded-lg bg-slate-900 px-3 py-2.5 text-center text-base font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900"
                        >
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="block rounded-lg px-3 py-2.5 text-base font-semibold text-slate-900 hover:bg-slate-50 dark:text-white dark:hover:bg-slate-800"
                            >
                                Log in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="mt-2 block rounded-lg bg-slate-900 px-3 py-2.5 text-center text-base font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900"
                            >
                                Get started
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="relative">
            <div class="mx-auto max-w-7xl px-6 py-24 sm:py-32 lg:px-8 lg:py-40">
                <div
                    class="mx-auto max-w-3xl text-center"
                >
                    <!-- Badge -->
                    <div class="mb-8 flex justify-center">
                        <span class="inline-flex items-center gap-x-2 rounded-full bg-slate-100 px-4 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Project Management Made Simple
                        </span>
                    </div>

                    <!-- Headline -->
                    <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl lg:text-7xl dark:text-white">
                        Manage projects with
                        <span class="bg-gradient-to-r from-purple-600 to-blue-500 bg-clip-text text-transparent">
                            clarity
                        </span>
                    </h1>

                    <!-- Subheadline -->
                    <p class="mt-6 text-lg leading-8 text-slate-600 dark:text-slate-400 sm:text-xl">
                        Priflo helps teams organize projects, track tasks, and collaborate effectively.
                        Built for teams that value reliability and simplicity.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-x-6">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="w-full rounded-xl bg-slate-900 px-8 py-4 text-base font-semibold text-white shadow-lg transition-all hover:bg-slate-700 hover:shadow-xl sm:w-auto dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                        >
                            Go to Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="w-full rounded-xl bg-slate-900 px-8 py-4 text-base font-semibold text-white shadow-lg transition-all hover:bg-slate-700 hover:shadow-xl sm:w-auto dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                            >
                                Start for free
                            </Link>
                            <Link
                                :href="login()"
                                class="group flex w-full items-center justify-center gap-x-2 rounded-xl px-8 py-4 text-base font-semibold text-slate-700 transition-all hover:text-slate-900 sm:w-auto dark:text-slate-300 dark:hover:text-white"
                            >
                                Sign in
                                <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- Hero Image / Dashboard Preview -->
                <div
                    class="mx-auto mt-16 max-w-5xl sm:mt-24"
                >
                    <div class="relative rounded-2xl bg-slate-900/5 p-2 ring-1 ring-inset ring-slate-900/10 dark:bg-white/5 dark:ring-white/10 lg:rounded-3xl lg:p-3">
                        <div class="overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-800 dark:ring-white/10">
                            <!-- Mock Dashboard -->
                            <div class="p-4 sm:p-6">
                                <!-- Mock header -->
                                <div class="flex items-center justify-between border-b border-slate-200 pb-4 dark:border-slate-700">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-purple-500 to-blue-500"></div>
                                        <div class="h-4 w-24 rounded bg-slate-200 dark:bg-slate-600"></div>
                                    </div>
                                    <div class="flex gap-2">
                                        <div class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-700"></div>
                                        <div class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-700"></div>
                                    </div>
                                </div>
                                <!-- Mock stats -->
                                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-700/50">
                                        <div class="h-3 w-16 rounded bg-slate-200 dark:bg-slate-600"></div>
                                        <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">12</div>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-700/50">
                                        <div class="h-3 w-16 rounded bg-slate-200 dark:bg-slate-600"></div>
                                        <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">8</div>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-700/50">
                                        <div class="h-3 w-16 rounded bg-slate-200 dark:bg-slate-600"></div>
                                        <div class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">5</div>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-700/50">
                                        <div class="h-3 w-16 rounded bg-slate-200 dark:bg-slate-600"></div>
                                        <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">24</div>
                                    </div>
                                </div>
                                <!-- Mock task list -->
                                <div class="mt-6 space-y-3">
                                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50">
                                        <div class="h-4 w-4 rounded border-2 border-emerald-500 bg-emerald-500"></div>
                                        <div class="h-3 flex-1 rounded bg-slate-200 dark:bg-slate-600"></div>
                                        <div class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400">Done</div>
                                    </div>
                                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50">
                                        <div class="h-4 w-4 rounded border-2 border-blue-500 bg-blue-500/20"></div>
                                        <div class="h-3 flex-1 rounded bg-slate-200 dark:bg-slate-600"></div>
                                        <div class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/50 dark:text-blue-400">In Progress</div>
                                    </div>
                                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50">
                                        <div class="h-4 w-4 rounded border-2 border-slate-300 dark:border-slate-600"></div>
                                        <div class="h-3 flex-1 rounded bg-slate-200 dark:bg-slate-600"></div>
                                        <div class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-400">To Do</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <section id="features" class="relative py-24 sm:py-32">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-base font-semibold leading-7 text-purple-600 dark:text-purple-400">Everything you need</h2>
                        <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            Powerful features for modern teams
                        </p>
                        <p class="mt-6 text-lg leading-8 text-slate-600 dark:text-slate-400">
                            All the tools your team needs to stay organized, collaborate effectively, and deliver projects on time.
                        </p>
                    </div>

                    <div class="mx-auto mt-12 max-w-5xl sm:mt-16">
                        <div class="grid gap-4 sm:gap-6 md:gap-8 sm:grid-cols-2 lg:grid-cols-3">
                            <!-- Feature 1 -->
                            <div class="group relative rounded-xl p-5 sm:rounded-2xl sm:p-6 md:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 transition-all hover:shadow-lg dark:bg-slate-800 dark:ring-white/10">
                                <div class="mb-3 inline-flex h-10 w-10 sm:mb-4 sm:h-12 sm:w-12 items-center justify-center rounded-lg sm:rounded-xl bg-purple-100 text-purple-600 dark:bg-purple-900/50 dark:text-purple-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                    </svg>
                                </div>
                                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Project Management</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Organize work into projects with teams, managers, and clear timelines.
                                </p>
                            </div>

                            <!-- Feature 2 -->
                            <div class="group relative rounded-xl p-5 sm:rounded-2xl sm:p-6 md:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 transition-all hover:shadow-lg dark:bg-slate-800 dark:ring-white/10">
                                <div class="mb-3 inline-flex h-10 w-10 sm:mb-4 sm:h-12 sm:w-12 items-center justify-center rounded-lg sm:rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Task Tracking</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Track tasks with statuses, priorities, progress, and due dates.
                                </p>
                            </div>

                            <!-- Feature 3 -->
                            <div class="group relative rounded-xl p-5 sm:rounded-2xl sm:p-6 md:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 transition-all hover:shadow-lg dark:bg-slate-800 dark:ring-white/10">
                                <div class="mb-3 inline-flex h-10 w-10 sm:mb-4 sm:h-12 sm:w-12 items-center justify-center rounded-lg sm:rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Team Collaboration</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Assign tasks to team members and collaborate through comments.
                                </p>
                            </div>

                            <!-- Feature 4 -->
                            <div class="group relative rounded-xl p-5 sm:rounded-2xl sm:p-6 md:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 transition-all hover:shadow-lg dark:bg-slate-800 dark:ring-white/10">
                                <div class="mb-3 inline-flex h-10 w-10 sm:mb-4 sm:h-12 sm:w-12 items-center justify-center rounded-lg sm:rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                    </svg>
                                </div>
                                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Activity Logging</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Complete audit trail of all changes for transparency and accountability.
                                </p>
                            </div>

                            <!-- Feature 5 -->
                            <div class="group relative rounded-xl p-5 sm:rounded-2xl sm:p-6 md:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 transition-all hover:shadow-lg dark:bg-slate-800 dark:ring-white/10">
                                <div class="mb-3 inline-flex h-10 w-10 sm:mb-4 sm:h-12 sm:w-12 items-center justify-center rounded-lg sm:rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </div>
                                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Role-Based Access</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Control access with Admin, Manager, and Member roles.
                                </p>
                            </div>

                            <!-- Feature 6 -->
                            <div class="group relative rounded-xl p-5 sm:rounded-2xl sm:p-6 md:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 transition-all hover:shadow-lg dark:bg-slate-800 dark:ring-white/10">
                                <div class="mb-3 inline-flex h-10 w-10 sm:mb-4 sm:h-12 sm:w-12 items-center justify-center rounded-lg sm:rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
                                    </svg>
                                </div>
                                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Dashboard Analytics</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Real-time overview of tasks, projects, and team progress.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How it works Section -->
            <section id="how-it-works" class="relative bg-slate-50 py-24 sm:py-32 dark:bg-slate-900/50">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-base font-semibold leading-7 text-purple-600 dark:text-purple-400">Simple workflow</h2>
                        <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            Get started in minutes
                        </p>
                    </div>

                    <div class="mx-auto mt-12 max-w-4xl sm:mt-16">
                        <div class="grid gap-8 sm:gap-6 md:gap-8 grid-cols-1 sm:grid-cols-3">
                            <div class="text-center">
                                <div class="mx-auto flex h-12 w-12 sm:h-14 sm:w-14 md:h-16 md:w-16 items-center justify-center rounded-full bg-purple-600 text-xl sm:text-2xl font-bold text-white">
                                    1
                                </div>
                                <h3 class="mt-4 sm:mt-6 text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Create a project</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Set up your project with a name, timeline, and assign a team.
                                </p>
                            </div>
                            <div class="text-center">
                                <div class="mx-auto flex h-12 w-12 sm:h-14 sm:w-14 md:h-16 md:w-16 items-center justify-center rounded-full bg-purple-600 text-xl sm:text-2xl font-bold text-white">
                                    2
                                </div>
                                <h3 class="mt-4 sm:mt-6 text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Add tasks</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Break down work into tasks with priorities and due dates.
                                </p>
                            </div>
                            <div class="text-center">
                                <div class="mx-auto flex h-12 w-12 sm:h-14 sm:w-14 md:h-16 md:w-16 items-center justify-center rounded-full bg-purple-600 text-xl sm:text-2xl font-bold text-white">
                                    3
                                </div>
                                <h3 class="mt-4 sm:mt-6 text-base sm:text-lg font-semibold text-slate-900 dark:text-white">Track progress</h3>
                                <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-400">
                                    Monitor task status and team progress from your dashboard.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Benefits Section -->
            <section id="benefits" class="relative py-16 sm:py-24 md:py-32">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto grid max-w-5xl gap-10 sm:gap-12 md:gap-16 md:grid-cols-2 lg:gap-24">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                                Built for reliability
                            </h2>
                            <p class="mt-6 text-lg leading-8 text-slate-600 dark:text-slate-400">
                                Priflo is designed with enterprise-grade features to ensure your data is always safe and your team can work without conflicts.
                            </p>

                            <dl class="mt-10 space-y-6">
                                <div class="flex gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-600 text-white">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <div>
                                        <dt class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Optimistic Locking</dt>
                                        <dd class="mt-1 text-sm sm:text-base text-slate-600 dark:text-slate-400">Prevents data conflicts when multiple users edit simultaneously.</dd>
                                    </div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-600 text-white">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <div>
                                        <dt class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Complete Audit Trail</dt>
                                        <dd class="mt-1 text-sm sm:text-base text-slate-600 dark:text-slate-400">Every change is logged with timestamps and actor information.</dd>
                                    </div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-600 text-white">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <div>
                                        <dt class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white">Two-Factor Authentication</dt>
                                        <dd class="mt-1 text-sm sm:text-base text-slate-600 dark:text-slate-400">Secure your account with TOTP-based 2FA protection.</dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        <div class="flex items-center">
                            <div class="relative w-full rounded-xl sm:rounded-2xl bg-gradient-to-br from-purple-500 to-blue-600 p-1">
                                <div class="rounded-lg sm:rounded-xl bg-slate-900 p-4 sm:p-6 md:p-8 overflow-x-auto">
                                    <div class="space-y-3 sm:space-y-4 font-mono text-xs sm:text-sm min-w-[280px]">
                                        <div class="text-slate-400">// Concurrency protection</div>
                                        <div>
                                            <span class="text-purple-400">$task</span><span class="text-slate-300">-></span><span class="text-blue-400">updateWithVersionCheck</span><span class="text-slate-300">([</span>
                                        </div>
                                        <div class="pl-4">
                                            <span class="text-emerald-400">'status'</span><span class="text-slate-300"> => </span><span class="text-amber-400">'completed'</span><span class="text-slate-300">,</span>
                                        </div>
                                        <div class="pl-4">
                                            <span class="text-emerald-400">'progress'</span><span class="text-slate-300"> => </span><span class="text-amber-400">100</span><span class="text-slate-300">,</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-300">], </span><span class="text-purple-400">$expectedVersion</span><span class="text-slate-300">);</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="relative bg-slate-900 py-24 sm:py-32 dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                            Ready to streamline your workflow?
                        </h2>
                        <p class="mt-6 text-lg leading-8 text-slate-300">
                            Join teams who trust Priflo to manage their projects efficiently.
                        </p>
                        <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-x-6">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="dashboard()"
                                class="w-full rounded-xl bg-white px-8 py-4 text-base font-semibold text-slate-900 shadow-lg transition-all hover:bg-slate-100 sm:w-auto"
                            >
                                Go to Dashboard
                            </Link>
                            <template v-else>
                                <Link
                                    v-if="canRegister"
                                    :href="register()"
                                    class="w-full rounded-xl bg-white px-8 py-4 text-base font-semibold text-slate-900 shadow-lg transition-all hover:bg-slate-100 sm:w-auto"
                                >
                                    Get started for free
                                </Link>
                                <Link
                                    :href="login()"
                                    class="flex items-center justify-center gap-x-2 text-base font-semibold text-white hover:text-slate-300"
                                >
                                    Already have an account? Sign in
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="relative border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-bold text-slate-900 dark:text-white">Priflo</span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        &copy; {{ new Date().getFullYear() }} Priflo. All rights reserved.
                    </p>
                    <div class="flex gap-6">
                        <a href="#" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                            <span class="sr-only">GitHub</span>
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
