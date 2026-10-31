<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    query: {
        type: String,
        default: '',
    },
    category: {
        type: String,
        default: '',
    },
    tag: {
        type: String,
        default: '',
    },
    series: {
        type: String,
        default: '',
    },
    date_from: {
        type: String,
        default: '',
    },
    date_to: {
        type: String,
        default: '',
    },
    posts: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    tags: {
        type: Array,
        default: () => [],
    },
    seriesList: {
        type: Array,
        default: () => [],
    },
});

const searchTerm = ref(props.query);
const selectedCategory = ref(props.category || '');
const selectedTag = ref(props.tag || '');
const selectedSeries = ref(props.series || '');
const selectedDateFrom = ref(props.date_from || '');
const selectedDateTo = ref(props.date_to || '');

const handleSearch = () => {
    router.get(route('search'), {
        q: searchTerm.value,
        category: selectedCategory.value || undefined,
        tag: selectedTag.value || undefined,
        series: selectedSeries.value || undefined,
        from: selectedDateFrom.value || undefined,
        to: selectedDateTo.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchTerm.value = '';
    selectedCategory.value = '';
    selectedTag.value = '';
    selectedSeries.value = '';
    selectedDateFrom.value = '';
    selectedDateTo.value = '';
    router.get(route('search'));
};
</script>

<template>
    <Head :title="`Search Stories — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                Explore & Search
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Discover essays, ideas, and stories across all categories and tags using full-text search.
            </p>

            <!-- Search Bar Form -->
            <form @submit.prevent="handleSearch" class="mt-6 space-y-4">
                <div class="relative">
                    <input
                        v-model="searchTerm"
                        type="search"
                        placeholder="Search by keywords, titles, or plain-text content..."
                        class="w-full rounded-2xl border-gray-300 py-3.5 pl-5 pr-28 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                    <button
                        type="submit"
                        class="absolute right-2 top-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 dark:bg-indigo-500"
                    >
                        Search
                    </button>
                </div>

                <!-- Filters Row: Category, Tag, Date Range -->
                <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 text-xs dark:border-gray-800 dark:bg-gray-900 shadow-sm">
                    <!-- Category Select -->
                    <div class="flex items-center gap-1.5">
                        <label for="filter-category" class="font-semibold text-gray-700 dark:text-gray-300">Category:</label>
                        <select
                            id="filter-category"
                            v-model="selectedCategory"
                            @change="handleSearch"
                            class="rounded-lg border-gray-300 py-1 pl-2.5 pr-8 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option value="">All Categories</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.slug">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Tag Select -->
                    <div class="flex items-center gap-1.5">
                        <label for="filter-tag" class="font-semibold text-gray-700 dark:text-gray-300">Tag:</label>
                        <select
                            id="filter-tag"
                            v-model="selectedTag"
                            @change="handleSearch"
                            class="rounded-lg border-gray-300 py-1 pl-2.5 pr-8 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option value="">All Tags</option>
                            <option v-for="t in tags" :key="t.id" :value="t.slug">
                                #{{ t.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Series Select -->
                    <div v-if="seriesList && seriesList.length > 0" class="flex items-center gap-1.5">
                        <label for="filter-series" class="font-semibold text-gray-700 dark:text-gray-300">Series:</label>
                        <select
                            id="filter-series"
                            v-model="selectedSeries"
                            @change="handleSearch"
                            class="rounded-lg border-gray-300 py-1 pl-2.5 pr-8 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            <option value="">All Series</option>
                            <option v-for="s in seriesList" :key="s.id" :value="s.slug">
                                {{ s.title }}
                            </option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="flex items-center gap-1.5">
                        <label for="filter-from" class="font-semibold text-gray-700 dark:text-gray-300">From:</label>
                        <input
                            id="filter-from"
                            v-model="selectedDateFrom"
                            type="date"
                            @change="handleSearch"
                            class="rounded-lg border-gray-300 py-1 px-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        />
                    </div>

                    <div class="flex items-center gap-1.5">
                        <label for="filter-to" class="font-semibold text-gray-700 dark:text-gray-300">To:</label>
                        <input
                            id="filter-to"
                            v-model="selectedDateTo"
                            type="date"
                            @change="handleSearch"
                            class="rounded-lg border-gray-300 py-1 px-2 text-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        />
                    </div>

                    <!-- Reset Button -->
                    <button
                        v-if="query || category || tag || date_from || date_to"
                        type="button"
                        @click="clearFilters"
                        class="ml-auto text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                    >
                        Reset filters
                    </button>
                </div>
            </form>

            <!-- Results Summary -->
            <div class="mt-8 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800 text-sm text-gray-500">
                <p>
                    Found <span class="font-bold text-gray-900 dark:text-white">{{ posts.total }}</span> results
                    <span v-if="query"> for "{{ query }}"</span>
                </p>
            </div>

            <!-- Posts List -->
            <div class="mt-6 space-y-6">
                <article
                    v-for="post in posts.data"
                    :key="post.id"
                    class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm hover:border-indigo-300 transition dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex flex-col sm:flex-row gap-6 justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <Link :href="route('author.show', post.user.username)" class="font-semibold text-gray-800 dark:text-gray-200 hover:text-indigo-600">
                                    {{ post.user.name }}
                                </Link>
                                <span>•</span>
                                <span>{{ post.reading_time_minutes }} min read</span>
                                <span>•</span>
                                <span>{{ new Date(post.published_at).toLocaleDateString() }}</span>
                                <template v-if="post.category">
                                    <span>•</span>
                                    <Link :href="route('categories.show', post.category.slug)" class="text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ post.category.name }}
                                    </Link>
                                </template>
                                <template v-if="post.series">
                                    <span>•</span>
                                    <Link
                                        :href="route('series.show', post.series.slug)"
                                        class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 transition"
                                    >
                                        Series · Part {{ post.series_order || 1 }}
                                    </Link>
                                </template>
                            </div>

                            <h3 class="mt-2 font-serif text-xl font-bold tracking-tight text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400 transition">
                                <Link :href="route('posts.show.canonical', { category_slug: post.category ? post.category.slug : 'story', post_slug: post.slug })">
                                    {{ post.title }}
                                </Link>
                            </h3>

                            <!-- Highlighted snippet if available from FTS5, else excerpt -->
                            <div
                                v-if="post.search_snippet"
                                class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed font-sans"
                                v-html="post.search_snippet"
                            ></div>
                            <p v-else class="mt-2 text-sm text-gray-600 line-clamp-2 dark:text-gray-300 leading-relaxed">
                                {{ post.excerpt }}
                            </p>

                            <!-- Tags if present -->
                            <div v-if="post.tags && post.tags.length > 0" class="mt-3 flex flex-wrap gap-1.5">
                                <span
                                    v-for="t in post.tags"
                                    :key="t.id"
                                    class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    #{{ t.name }}
                                </span>
                            </div>
                        </div>

                        <div v-if="post.cover_image_path" class="w-full sm:w-44 h-32 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                            <img :src="post.cover_image_path" :alt="post.title" class="h-full w-full object-cover" />
                        </div>
                    </div>
                </article>

                <div v-if="posts.data.length === 0" class="rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800">
                    <p class="text-sm text-gray-500">No stories match your search query and filters.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="posts.links && posts.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link
                    v-for="(link, i) in posts.links"
                    :key="i"
                    :href="link.url || '#'"
                    :class="[
                        link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                        'px-3.5 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 font-medium'
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
