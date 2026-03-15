<script setup lang="ts">
import DocsLayout from '@/layouts/DocsLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Head } from '@inertiajs/vue3';
import { 
    Server, 
    Database, 
    Shield, 
    Layout, 
    Component, 
    Wrench,
    FileText,
    ArrowRight,
    Lock,
    Key,
    Activity,
    ListChecks,
    Users
} from 'lucide-vue-next';

const breadcrumbs = [
    { title: 'Internal Documentation', href: '/docs' }
];

const backendStats = [
    { label: 'Models', count: 13, icon: Database },
    { label: 'Controllers', count: 12, icon: Server },
    { label: 'Routes', count: 64, icon: ArrowRight },
    { label: 'Policies', count: 6, icon: Shield },
];
</script>

<template>
    <Head title="Internal Developer Documentation" />
    <DocsLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-16">
            <!-- Header/Overview -->
            <section id="overview" class="scroll-mt-24 space-y-6">
                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="px-2 py-0.5">v1.0.0</Badge>
                    <Badge variant="secondary" class="px-2 py-0.5">Internal Only</Badge>
                </div>
                <h1 class="text-4xl font-extrabold tracking-tight lg:text-5xl">
                    Overview
                </h1>
                <p class="text-xl text-neutral-500 dark:text-neutral-400 max-w-2xl leading-relaxed">
                    A comprehensive guide to the Priflo architecture, backend systems, and frontend component library.
                </p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4">
                    <div v-for="stat in backendStats" :key="stat.label" class="border border-neutral-200 dark:border-neutral-800 rounded-xl p-4 bg-white dark:bg-neutral-950 shadow-sm">
                        <div class="flex items-center gap-2 mb-1">
                            <component :is="stat.icon" class="w-4 h-4 text-neutral-400" />
                            <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ stat.label }}</span>
                        </div>
                        <div class="text-2xl font-bold">{{ stat.count }}</div>
                    </div>
                </div>
            </section>

            <Separator />

            <!-- Architecture -->
            <section id="architecture" class="scroll-mt-24 space-y-6">
                <div class="flex items-center gap-2">
                    <div class="p-2 rounded-lg bg-blue-500/10 text-blue-500">
                        <Wrench class="w-6 h-6" />
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight">System Architecture</h2>
                </div>
                <p class="text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    Priflo is built with modern Laravel best practices, utilizing Inertia.js to create a single-page app experience with the simplicity of server-side routing.
                </p>
                <Card>
                    <CardHeader>
                        <CardTitle>Core Technology Stack</CardTitle>
                        <CardDescription>Frameworks and versions used in the project.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <div class="space-y-3">
                            <div class="font-semibold flex items-center gap-2 text-neutral-900 dark:text-neutral-100">
                                <Server class="w-4 h-4" /> Backend
                            </div>
                            <ul class="text-sm text-neutral-500 dark:text-neutral-400 space-y-2">
                                <li class="flex items-center gap-2"><ArrowRight class="w-3 h-3" /> Laravel 12 (PHP 8.4+)</li>
                                <li class="flex items-center gap-2"><ArrowRight class="w-3 h-3" /> Laravel Fortify (Auth)</li>
                                <li class="flex items-center gap-2"><ArrowRight class="w-3 h-3" /> MySQL (Database Engine)</li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <div class="font-semibold flex items-center gap-2 text-neutral-900 dark:text-neutral-100">
                                <Layout class="w-4 h-4" /> Frontend
                            </div>
                            <ul class="text-sm text-neutral-500 dark:text-neutral-400 space-y-2">
                                <li class="flex items-center gap-2"><ArrowRight class="w-3 h-3" /> Vue 3 (Composition API)</li>
                                <li class="flex items-center gap-2"><ArrowRight class="w-3 h-3" /> Inertia.js 2.0</li>
                                <li class="flex items-center gap-2"><ArrowRight class="w-3 h-3" /> Tailwind CSS 4</li>
                                <li class="flex items-center gap-2"><ArrowRight class="w-3 h-3" /> Shadcn-vue Components</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <Separator />

            <!-- Backend Deep Dive -->
            <div class="space-y-12">
                <h2 class="text-3xl font-bold tracking-tight">Backend Architecture</h2>

                <section id="routing" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <ArrowRight class="w-5 h-5 text-neutral-400" /> Routing
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Routes are logically separated. Web routes reside in <code>routes/web.php</code>, while settings and profile management are in <code>routes/settings.php</code>.
                    </p>
                    <div class="rounded-lg bg-neutral-900 p-5 overflow-x-auto shadow-xl">
                        <pre class="text-sm text-neutral-100 leading-6"><code>// Authentication & Dashboard
Route::get('dashboard', [DashboardRouter::class, 'index']);

// Resource Management (Managed by Policies)
Route::get('projects', [ProjectController::class, 'index']);
Route::get('tasks', [TaskController::class, 'index']);</code></pre>
                    </div>
                </section>

                <section id="controllers" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Server class="w-5 h-5 text-neutral-400" /> Controllers
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Controllers are responsible for handling HTTP requests and returning Inertia responses. Business logic is often delegated to Services or Actions.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm uppercase tracking-widest text-neutral-500">Domain Controllers</CardTitle>
                            </CardHeader>
                            <CardContent class="text-sm">
                                ProjectController, TaskController, TeamController, CommentController.
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm uppercase tracking-widest text-neutral-500">System Controllers</CardTitle>
                            </CardHeader>
                            <CardContent class="text-sm">
                                ProfileController, PasswordController, TwoFactorAuthenticationController.
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section id="models" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Database class="w-5 h-5 text-neutral-400" /> Models & Database
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Eloquent models implement core business logic, relationships, and advanced database behavior like Optimistic Locking.
                    </p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div v-for="m in ['Project', 'Task', 'User', 'Team', 'Role', 'Comment', 'ActivityLog', 'TransactionLog']" :key="m" class="p-3 border rounded-lg text-sm font-medium text-center bg-white dark:bg-neutral-950">
                            {{ m }}
                        </div>
                    </div>
                </section>

                <section id="middleware" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Lock class="w-5 h-5 text-neutral-400" /> Middleware
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Custom middleware handles authorization and state management across requests.
                    </p>
                    <ul class="space-y-3">
                        <li class="p-4 border rounded-lg bg-neutral-50/50 dark:bg-neutral-900/50">
                            <span class="font-bold font-mono text-sm">EnsureRole</span>
                            <p class="text-sm text-neutral-500 mt-1">Restricts access to routes based on user roles (e.g., Admin only).</p>
                        </li>
                        <li class="p-4 border rounded-lg bg-neutral-50/50 dark:bg-neutral-900/50">
                            <span class="font-bold font-mono text-sm">EnsureTeamAccess</span>
                            <p class="text-sm text-neutral-500 mt-1">Ensures users belong to the team they are trying to access.</p>
                        </li>
                    </ul>
                </section>

                <section id="policies" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Shield class="w-5 h-5 text-neutral-400" /> Policies
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Granular authorization logic is encapsulated in Policies, checking permissions for specific model instances.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div v-for="p in ['ProjectPolicy', 'TaskPolicy', 'TeamPolicy', 'UserPolicy', 'CommentPolicy', 'ActivityLogPolicy']" :key="p" class="p-3 border border-dashed rounded-lg text-xs font-mono text-center">
                            {{ p }}
                        </div>
                    </div>
                </section>

                <section id="requests" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <ListChecks class="w-5 h-5 text-neutral-400" /> Form Requests
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Validation logic is extracted into dedicated Form Request classes to keep controllers clean.
                    </p>
                </section>

                <section id="services" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Wrench class="w-5 h-5 text-neutral-400" /> Services
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Complex cross-cutting concerns are handled by Singleton services.
                    </p>
                    <div class="space-y-3">
                        <div class="p-4 border rounded-lg">
                            <div class="font-bold text-sm">LockManager</div>
                            <p class="text-sm text-neutral-500">Handles database-level advisory locks for concurrency control.</p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <div class="font-bold text-sm">TransactionManager</div>
                            <p class="text-sm text-neutral-500">Manages complex DB transactions with automatic logging and error handling.</p>
                        </div>
                    </div>
                </section>
            </div>

            <Separator />

            <!-- Frontend Deep Dive -->
            <div class="space-y-12">
                <h2 class="text-3xl font-bold tracking-tight">Frontend Architecture</h2>

                <section id="pages" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Layout class="w-5 h-5 text-neutral-400" /> Inertia Pages
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Pages are located in <code>resources/js/Pages/</code> and represent the full views returned by the server.
                    </p>
                </section>

                <section id="components" class="scroll-mt-24 space-y-4">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Component class="w-5 h-5 text-neutral-400" /> Vue Components
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Shared components are in <code>resources/js/Components/</code>, organized by domain and utility.
                    </p>
                </section>

                <section id="ui" class="scroll-mt-24 space-y-4 pb-20">
                    <h3 class="text-xl font-bold flex items-center gap-2">
                        <Activity class="w-5 h-5 text-neutral-400" /> UI System (shadcn-vue)
                    </h3>
                    <p class="text-neutral-600 dark:text-neutral-400">
                        Base UI components are built using shadcn-vue primitives for accessibility and consistent styling.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="c in ['Button', 'Card', 'Dialog', 'Dropdown', 'Input', 'Popover', 'Select', 'Sheet', 'Sidebar', 'Table', 'Tabs', 'Tooltip']" :key="c" variant="secondary">
                            {{ c }}
                        </Badge>
                    </div>
                </section>
            </div>

            <Separator />

            <!-- Footer -->
            <div class="flex flex-col md:flex-row justify-between items-center py-8 text-sm text-neutral-500 gap-4">
                <div class="flex items-center gap-1">
                    <FileText class="w-4 h-4" />
                    <span>Last updated: March 2026</span>
                </div>
                <div class="flex items-center gap-6">
                    <a href="https://laravel.com/docs" target="_blank" class="hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">Laravel Docs</a>
                    <a href="https://inertiajs.com" target="_blank" class="hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">Inertia Docs</a>
                    <a href="https://vuejs.org" target="_blank" class="hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">Vue Docs</a>
                </div>
            </div>
        </div>
    </DocsLayout>
</template>
