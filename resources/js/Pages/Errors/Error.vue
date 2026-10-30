<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    status: {
        type: Number,
        default: 404,
    },
    message: {
        type: String,
        default: '',
    },
});

const searchQuery = ref('');

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        router.get(route('search'), { q: searchQuery.value.trim() });
    }
};

const title = props.status === 410 ? '410 — Story Removed' : '404 — Page Not Found';
const defaultMessage = props.status === 410
    ? 'This story has been permanently removed by the author or moderators and is no longer available on Inkwell.'
    : 'We couldn’t find the page or story you were looking for. It may have been moved, renamed, or deleted.';
</script>

<template>
    <Head :title="`${title} — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
            <span class="inline-block rounded-2xl bg-indigo-50 px-4 py-1.5 font-mono text-base font-bold text-indigo-600 dark:bg-indigo-950/70 dark:text-indigo-400">
                HTTP {{ status }}
            </span>

            <h1 class="mt-4 font-serif text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                {{ status === 410 ? 'Story Gone Permanently' : 'Lost in the Margins?' }}
            </h1>

            <p class="mx-auto mt-4 max-w-xl text-base text-gray-600 dark:text-gray-300 leading-relaxed">
                {{ message || defaultMessage }}
            </p>

            <!-- Search Box -->
            <form @submit.prevent="handleSearch" class="mx-auto mt-8 max-w-md">
                <div class="relative">
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search Inkwell for topics or stories..."
                        class="w-full rounded-2xl border-gray-300 py-3 pl-4 pr-24 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                    <button
                        type="submit"
                        class="absolute right-1.5 top-1.5 rounded-xl bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    >
                        Search
                    </button>
                </div>
            </form>

            <!-- Navigation Links -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-4 text-sm font-semibold">
                <Link
                    :href="route('home')"
                    class="rounded-xl bg-gray-900 px-5 py-2.5 text-white hover:bg-gray-800 transition dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                >
                    Back to Home
                </Link>
                <Link
                    :href="route('search')"
                    class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-gray-700 hover:bg-gray-50 transition dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                    Explore Stories
                </Link>
                <Link
                    :href="route('archive.index')"
                    class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-gray-700 hover:bg-gray-50 transition dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                    The Archives
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
