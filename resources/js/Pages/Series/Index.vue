<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    series: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters.search || '');

const handleSearch = () => {
    router.get(route('series.index'), {
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearSearch = () => {
    search.value = '';
    handleSearch();
};
</script>

<template>
    <Head :title="seo.title || 'Story Series — Inkwell'">
        <meta name="description" :content="seo.description || 'Explore story series on Inkwell.'" />
        <link v-if="seo.canonical_url" rel="canonical" :href="seo.canonical_url" />
    </Head>

    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 border-b border-gray-200 pb-8 dark:border-gray-800">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                        Multi-Part Publications
                    </span>
                    <h1 class="mt-2 font-serif text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Story Series
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-xl">
                        Deep dives, sequential sagas, and multi-chapter stories crafted by passionate writers.
                    </p>
                </div>

                <!-- Search -->
                <div class="w-full md:w-72">
                    <div class="relative">
                        <input
                            type="text"
                            v-model="search"
                            @keydown.enter="handleSearch"
                            placeholder="Search series..."
                            class="w-full rounded-xl border border-gray-300 bg-white py-2 pl-9 pr-8 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 placeholder:text-gray-400"
                        />
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <button
                            v-if="search"
                            type="button"
                            @click="clearSearch"
                            class="absolute right-2.5 top-2.5 text-xs text-gray-400 hover:text-gray-600"
                        >
                            &times;
                        </button>
                    </div>
                </div>
            </div>

            <!-- Series Grid -->
            <div v-if="series.data && series.data.length > 0" class="mt-10 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <article
                    v-for="s in series.data"
                    :key="s.id"
                    class="group flex flex-col justify-between rounded-2xl border border-gray-200/80 bg-white p-5 shadow-xs transition duration-200 hover:border-indigo-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-900"
                >
                    <div>
                        <!-- Cover Image Preview -->
                        <Link :href="route('series.show', s.slug)" class="block overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800 h-44 mb-4">
                            <img
                                v-if="s.cover_image_path"
                                :src="s.cover_image_path"
                                :alt="s.title"
                                loading="lazy"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                            <div v-else class="h-full w-full flex flex-col items-center justify-center bg-gradient-to-br from-indigo-50 to-indigo-100/50 dark:from-indigo-950/40 dark:to-gray-800 p-4 text-center">
                                <span class="font-serif text-lg font-bold text-indigo-900 dark:text-indigo-200 line-clamp-2">{{ s.title }}</span>
                                <span class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium mt-1">Inkwell Series</span>
                            </div>
                        </Link>

                        <!-- Status & Parts Count -->
                        <div class="flex items-center justify-between text-xs">
                            <span
                                :class="[
                                    s.status === 'completed'
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : (s.status === 'hiatus'
                                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300'
                                            : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'),
                                    'rounded-full px-2.5 py-0.5 text-[11px] font-semibold'
                                ]"
                            >
                                {{ s.status === 'completed' ? 'Completed' : (s.status === 'hiatus' ? 'Hiatus' : 'Ongoing') }}
                            </span>

                            <span class="text-gray-500 dark:text-gray-400 font-medium">
                                {{ s.published_posts_count || 0 }} {{ (s.published_posts_count || 0) === 1 ? 'part' : 'parts' }}
                            </span>
                        </div>

                        <!-- Title -->
                        <h2 class="mt-2.5 font-serif text-xl font-bold tracking-tight text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">
                            <Link :href="route('series.show', s.slug)">
                                {{ s.title }}
                            </Link>
                        </h2>

                        <!-- Description -->
                        <p v-if="s.description" class="mt-2 text-xs text-gray-600 dark:text-gray-300 line-clamp-3 leading-relaxed">
                            {{ s.description }}
                        </p>
                    </div>

                    <!-- Footer / Author info -->
                    <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <Link
                            v-if="s.author"
                            :href="route('author.show', s.author.username)"
                            class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 hover:text-indigo-600"
                        >
                            <img
                                :src="s.author.avatar_url"
                                :alt="s.author.name"
                                class="h-6 w-6 rounded-full object-cover"
                            />
                            <span class="font-medium truncate max-w-[120px]">{{ s.author.name }}</span>
                        </Link>
                        <span v-else></span>

                        <Link
                            :href="route('series.show', s.slug)"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                        >
                            Read Series →
                        </Link>
                    </div>
                </article>
            </div>

            <!-- Empty State -->
            <div v-else class="mt-16 text-center rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No series found</h3>
                <p class="mt-1 text-xs text-gray-500">Try adjusting your search query or check back later.</p>
                <div class="mt-6" v-if="search">
                    <button
                        type="button"
                        @click="clearSearch"
                        class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500"
                    >
                        Clear search
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="series.links && series.links.length > 3" class="mt-10 flex justify-center">
                <nav class="flex items-center space-x-1">
                    <template v-for="(link, i) in series.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            :class="[
                                link.active
                                    ? 'bg-indigo-600 text-white font-bold'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                                'rounded-lg px-3 py-1.5 text-xs transition'
                            ]"
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-2 py-1 text-xs text-gray-400 cursor-not-allowed"
                        />
                    </template>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
