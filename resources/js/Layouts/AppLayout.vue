<script setup>
import { ref } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';

const page = usePage();
const searchQuery = ref('');
const mobileMenuOpen = ref(false);

const categories = [
    { name: 'All Topics', slug: '' },
    { name: 'Technology', slug: 'technology' },
    { name: 'Design & UX', slug: 'design' },
    { name: 'Culture & Ideas', slug: 'culture' },
    { name: 'Writing & Craft', slug: 'writing' },
    { name: 'Science & Nature', slug: 'science' },
    { name: 'Business & Startups', slug: 'business' },
];

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        router.get(route('search'), { q: searchQuery.value.trim() });
    }
};

const handleCategorySelect = (category) => {
    if (category.slug) {
        router.get('/', { category: category.slug }, { preserveState: true });
    } else {
        router.get('/', {}, { preserveState: true });
    }
};
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50 text-gray-900 transition-colors duration-200 dark:bg-gray-950 dark:text-gray-100">
        <!-- Top Navigation -->
        <header class="sticky top-0 z-40 w-full border-b border-gray-200/80 bg-white/90 backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/90">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <!-- Left: Logo & Categories -->
                <div class="flex items-center gap-6">
                    <Link :href="route('home')" class="flex items-center gap-2 transition hover:opacity-90">
                        <ApplicationLogo />
                    </Link>

                    <!-- Category Dropdown (Desktop) -->
                    <div class="hidden md:block">
                        <Dropdown align="left" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
                                >
                                    <span>Topics</span>
                                    <svg
                                        class="h-4 w-4 text-gray-400"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="p-1">
                                    <button
                                        v-for="cat in categories"
                                        :key="cat.slug"
                                        type="button"
                                        @click="handleCategorySelect(cat)"
                                        class="w-full text-left rounded-md px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        {{ cat.name }}
                                    </button>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>

                <!-- Center: Search Box (Desktop) -->
                <div class="hidden flex-1 max-w-md md:block">
                    <form @submit.prevent="handleSearch" class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg
                                class="h-4 w-4 text-gray-400 dark:text-gray-500"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search stories, topics, authors..."
                            class="w-full rounded-full border border-gray-200 bg-gray-50 py-1.5 pl-9 pr-4 text-sm text-gray-900 placeholder-gray-400 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:border-indigo-400 dark:focus:bg-gray-800"
                        />
                    </form>
                </div>

                <!-- Right: Actions, Dark Mode, Write, Auth -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Write Button -->
                    <Link
                        :href="route('posts.create')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
                        title="Write a new story"
                    >
                        <svg
                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                            <path d="m15 5 4 4" />
                        </svg>
                        <span>Write</span>
                    </Link>

                    <!-- Dark Mode Toggle -->
                    <DarkModeToggle />

                    <!-- Auth Links or User Menu -->
                    <template v-if="page.props.auth?.user">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white p-1 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300">
                                        {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                                    </span>
                                    <span class="hidden max-w-[120px] truncate pr-2 text-xs font-medium sm:inline">
                                        {{ page.props.auth.user.name }}
                                    </span>
                                    <svg
                                        class="h-3.5 w-3.5 pr-1 text-gray-400"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700 text-xs">
                                    <p class="font-medium text-gray-900 dark:text-white truncate">{{ page.props.auth.user.name }}</p>
                                    <p class="text-gray-500 dark:text-gray-400 truncate">{{ page.props.auth.user.email }}</p>
                                </div>
                                <DropdownLink :href="route('dashboard')">
                                    Dashboard
                                </DropdownLink>
                                <DropdownLink :href="route('author.show', page.props.auth.user.username)">
                                    Public Profile
                                </DropdownLink>
                                <DropdownLink :href="route('profile.edit')">
                                    Settings
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    Sign Out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </template>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="hidden text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-white sm:inline-block px-2 py-1.5"
                        >
                            Sign In
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-indigo-600 px-3.5 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        >
                            Get Started
                        </Link>
                    </template>

                    <!-- Mobile Hamburger Button -->
                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 md:hidden"
                        aria-label="Toggle navigation"
                    >
                        <svg
                            v-if="!mobileMenuOpen"
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg
                            v-else
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Drawer Menu -->
            <div
                v-if="mobileMenuOpen"
                class="border-b border-gray-200 bg-white px-4 pt-2 pb-4 dark:border-gray-800 dark:bg-gray-900 md:hidden"
            >
                <!-- Mobile Search -->
                <form @submit.prevent="handleSearch" class="mb-3">
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search stories, topics, authors..."
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 px-3 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    />
                </form>

                <!-- Mobile Categories -->
                <div class="mb-4">
                    <p class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        Topics
                    </p>
                    <div class="grid grid-cols-2 gap-1">
                        <button
                            v-for="cat in categories"
                            :key="cat.slug"
                            type="button"
                            @click="handleCategorySelect(cat); mobileMenuOpen = false"
                            class="rounded-md px-2 py-1.5 text-start text-xs font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Mobile Auth Options -->
                <div class="border-t border-gray-100 pt-3 dark:border-gray-800">
                    <template v-if="page.props.auth?.user">
                        <Link
                            :href="route('dashboard')"
                            class="block py-2 text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-gray-200"
                        >
                            Dashboard
                        </Link>
                        <Link
                            :href="route('profile.edit')"
                            class="block py-2 text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-gray-200"
                        >
                            Profile Settings
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="block w-full py-2 text-start text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400"
                        >
                            Sign Out
                        </Link>
                    </template>
                    <template v-else>
                        <div class="flex flex-col gap-2 pt-1">
                            <Link
                                :href="route('login')"
                                class="text-center rounded-lg border border-gray-200 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                            >
                                Sign In
                            </Link>
                            <Link
                                :href="route('register')"
                                class="text-center rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white dark:bg-indigo-500"
                            >
                                Get Started
                            </Link>
                        </div>
                    </template>
                </div>
            </div>
        </header>

        <!-- Page Header Slot (Optional) -->
        <div v-if="$slots.header" class="border-b border-gray-200/80 bg-white/50 backdrop-blur dark:border-gray-800 dark:bg-gray-900/50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="mt-auto border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 transition-colors">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-4">
                    <!-- Brand info -->
                    <div class="md:col-span-2 space-y-4">
                        <Link :href="route('home')" class="inline-block">
                            <ApplicationLogo />
                        </Link>
                        <p class="max-w-md text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            A modern, open publishing platform built for meaningful words and curious readers.
                            Anyone can register and publish; anyone can read without logging in.
                        </p>
                        <div class="flex items-center gap-3 pt-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-400 dark:ring-emerald-500/30">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Open Platform
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Powered by SQLite & Laravel 12
                            </span>
                        </div>
                    </div>

                    <!-- Topics column -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-900 dark:text-white">
                            Explore Topics
                        </h3>
                        <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li>
                                <Link :href="route('search')" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Search & Discover
                                </Link>
                            </li>
                            <li>
                                <Link :href="route('archive.index')" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">
                                    The Archives
                                </Link>
                            </li>
                            <li v-for="cat in categories.slice(1, 5)" :key="cat.slug">
                                <button
                                    type="button"
                                    @click="handleCategorySelect(cat)"
                                    class="transition hover:text-indigo-600 dark:hover:text-indigo-400"
                                >
                                    {{ cat.name }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Platform links -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-900 dark:text-white">
                            Get Started
                        </h3>
                        <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li>
                                <Link :href="page.props.auth?.user ? route('dashboard') : route('register')" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Start Writing
                                </Link>
                            </li>
                            <li v-if="!page.props.auth?.user">
                                <Link :href="route('login')" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Sign In
                                </Link>
                            </li>
                            <li v-if="page.props.auth?.user">
                                <Link :href="route('dashboard')" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Dashboard
                                </Link>
                            </li>
                            <li>
                                <Link :href="route('profile.edit')" v-if="page.props.auth?.user" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Account
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Bottom Bar -->
                <div class="mt-12 border-t border-gray-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <p>&copy; {{ new Date().getFullYear() }} Inkwell. All rights reserved.</p>
                    <div class="flex items-center gap-6">
                        <span>Clean SQLite Architecture</span>
                        <span>•</span>
                        <span>Inertia + Vue 3</span>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
