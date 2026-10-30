<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const isAdmin = computed(() => currentUser.value?.role === 'admin');

const sidebarOpen = ref(false);

const navigation = computed(() => [
    { name: 'Overview', href: route('admin.dashboard'), active: route().current('admin.dashboard') },
    { name: 'Stories', href: route('admin.posts.index'), active: route().current('admin.posts.*') },
    { name: 'Moderation Queue', href: route('admin.reports.index'), active: route().current('admin.reports.*') },
    { name: 'Users', href: route('admin.users.index'), active: route().current('admin.users.*') },
    { name: 'Categories', href: route('admin.categories.index'), active: route().current('admin.categories.*') },
    { name: 'Tags', href: route('admin.tags.index'), active: route().current('admin.tags.*') },
    ...(isAdmin.value ? [
        { name: 'Site Settings', href: route('admin.settings.index'), active: route().current('admin.settings.*') },
        { name: 'Audit Logs', href: route('admin.audit-logs.index'), active: route().current('admin.audit-logs.*') },
    ] : []),
]);

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error || (page.props.errors ? Object.values(page.props.errors)[0] : null));
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex flex-col md:flex-row">
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/50 backdrop-blur-xs md:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar Navigation -->
        <aside
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                'fixed inset-y-0 left-0 z-50 w-64 border-r border-gray-200 bg-white p-5 transition-transform duration-200 dark:border-gray-800 dark:bg-gray-900 md:static md:translate-x-0 flex flex-col justify-between'
            ]"
        >
            <div>
                <!-- Brand / Logo -->
                <div class="flex items-center justify-between pb-6 border-b border-gray-100 dark:border-gray-800">
                    <Link :href="route('home')" class="flex items-center gap-2">
                        <ApplicationLogo />
                        <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                            {{ currentUser?.role === 'admin' ? 'Admin' : 'Mod' }}
                        </span>
                    </Link>
                    <button
                        type="button"
                        class="md:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400"
                        @click="sidebarOpen = false"
                    >
                        ✕
                    </button>
                </div>

                <!-- Nav Items -->
                <nav class="mt-6 space-y-1.5">
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            item.active
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white',
                            'flex items-center gap-3 rounded-xl px-3.5 py-2 text-sm transition'
                        ]"
                    >
                        <span>{{ item.name }}</span>
                    </Link>
                </nav>
            </div>

            <!-- Footer / User info -->
            <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <img
                        :src="currentUser?.avatar_url"
                        :alt="currentUser?.name"
                        class="h-9 w-9 rounded-full object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-gray-900 dark:text-white">
                            {{ currentUser?.name }}
                        </p>
                        <p class="truncate text-[10px] capitalize text-indigo-600 dark:text-indigo-400 font-medium">
                            {{ currentUser?.role }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
                    <Link
                        :href="route('home')"
                        class="text-xs font-medium text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                    >
                        ← Exit to Inkwell
                    </Link>
                    <DarkModeToggle />
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Mobile Top Bar -->
            <header class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 dark:border-gray-800 dark:bg-gray-900 md:hidden">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        Admin Console
                    </span>
                </div>
                <DarkModeToggle />
            </header>

            <!-- Flash notifications -->
            <div v-if="flashSuccess" class="m-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm font-medium text-emerald-800 dark:bg-emerald-950/50 dark:border-emerald-900 dark:text-emerald-300">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="m-4 rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm font-medium text-rose-800 dark:bg-rose-950/50 dark:border-rose-900 dark:text-rose-300">
                {{ flashError }}
            </div>

            <!-- Page Body -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
