<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    posts: {
        type: Object,
        required: true,
    },
    counts: {
        type: Object,
        default: () => ({
            published: 0,
            drafts: 0,
            scheduled: 0,
            archived: 0,
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            total_views: 0,
            total_likes: 0,
            total_comments: 0,
            total_stories: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            tab: 'published',
            search: '',
            sort: 'latest',
        }),
    },
});

const search = ref(props.filters.search || '');
const sort = ref(props.filters.sort || 'latest');
const selectedIds = ref([]);
const isAllSelected = computed(() => {
    return props.posts.data.length > 0 && selectedIds.value.length === props.posts.data.length;
});

// Toggle select all
const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.posts.data.map((p) => p.id);
    }
};

// Apply filters (tab, search, sort)
const switchTab = (tab) => {
    selectedIds.value = [];
    router.get(
        route('dashboard'),
        { tab, search: search.value || undefined, sort: sort.value },
        { preserveState: true, replace: true }
    );
};

const applyFilters = () => {
    selectedIds.value = [];
    router.get(
        route('dashboard'),
        { tab: props.filters.tab, search: search.value || undefined, sort: sort.value },
        { preserveState: true, replace: true }
    );
};

// Debounce search
let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// Bulk action execution
const executeBulkAction = (action) => {
    if (selectedIds.value.length === 0) return;

    if (action === 'delete' && !confirm(`Are you sure you want to delete ${selectedIds.value.length} selected story(ies)?`)) {
        return;
    }

    router.post(
        route('dashboard.bulk-action'),
        {
            ids: selectedIds.value,
            action,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
        }
    );
};
</script>

<template>
    <Head title="Writer Dashboard — Inkwell" />

    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Writer Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your stories, view readership performance, and organize your publication queue.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('posts.create')"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 transition"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Write New Story
                    </Link>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/60">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Stories</p>
                    <p class="mt-1 font-serif text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_stories }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/60">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Views</p>
                    <p class="mt-1 font-serif text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.total_views }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/60">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Likes</p>
                    <p class="mt-1 font-serif text-2xl font-bold text-rose-500">{{ stats.total_likes }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/60">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Comments</p>
                    <p class="mt-1 font-serif text-2xl font-bold text-amber-500">{{ stats.total_comments }}</p>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="mt-8 border-b border-gray-200 dark:border-gray-800">
                <nav class="-mb-px flex space-x-6 overflow-x-auto scrollbar-none" aria-label="Tabs">
                    <button
                        type="button"
                        @click="switchTab('published')"
                        :class="[
                            filters.tab === 'published'
                                ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200',
                            'flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition'
                        ]"
                    >
                        <span>Published</span>
                        <span
                            :class="[
                                filters.tab === 'published'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                'rounded-full px-2 py-0.5 text-xs'
                            ]"
                        >
                            {{ counts.published }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('drafts')"
                        :class="[
                            filters.tab === 'drafts'
                                ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200',
                            'flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition'
                        ]"
                    >
                        <span>Drafts</span>
                        <span
                            :class="[
                                filters.tab === 'drafts'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                'rounded-full px-2 py-0.5 text-xs'
                            ]"
                        >
                            {{ counts.drafts }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('scheduled')"
                        :class="[
                            filters.tab === 'scheduled'
                                ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200',
                            'flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition'
                        ]"
                    >
                        <span>Scheduled</span>
                        <span
                            :class="[
                                filters.tab === 'scheduled'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                'rounded-full px-2 py-0.5 text-xs'
                            ]"
                        >
                            {{ counts.scheduled }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('archived')"
                        :class="[
                            filters.tab === 'archived'
                                ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200',
                            'flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition'
                        ]"
                    >
                        <span>Archived</span>
                        <span
                            :class="[
                                filters.tab === 'archived'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                'rounded-full px-2 py-0.5 text-xs'
                            ]"
                        >
                            {{ counts.archived }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Filters Bar & Bulk Actions -->
            <div class="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Search Box -->
                <div class="relative w-full sm:w-72">
                    <input
                        type="text"
                        v-model="search"
                        placeholder="Search stories..."
                        class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-2 pl-9 pr-3 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <!-- Sort Dropdown & Bulk Actions Bar -->
                <div class="flex items-center gap-3">
                    <!-- Bulk Actions Bar (Shown when items are selected) -->
                    <div v-if="selectedIds.length > 0" class="flex items-center gap-2 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-900 px-3 py-1.5 rounded-xl">
                        <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                            {{ selectedIds.length }} selected
                        </span>

                        <div class="h-4 w-px bg-indigo-200 dark:bg-indigo-800 mx-1"></div>

                        <button
                            v-if="filters.tab !== 'published'"
                            type="button"
                            @click="executeBulkAction('publish')"
                            class="text-xs font-medium text-emerald-700 dark:text-emerald-400 hover:underline"
                        >
                            Publish
                        </button>

                        <button
                            v-if="filters.tab === 'published'"
                            type="button"
                            @click="executeBulkAction('unpublish')"
                            class="text-xs font-medium text-amber-700 dark:text-amber-400 hover:underline"
                        >
                            Unpublish
                        </button>

                        <button
                            v-if="filters.tab !== 'archived'"
                            type="button"
                            @click="executeBulkAction('archive')"
                            class="text-xs font-medium text-gray-700 dark:text-gray-300 hover:underline"
                        >
                            Archive
                        </button>

                        <button
                            type="button"
                            @click="executeBulkAction('delete')"
                            class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline"
                        >
                            Delete
                        </button>
                    </div>

                    <!-- Sort Select -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">Sort by:</label>
                        <select
                            v-model="sort"
                            @change="applyFilters"
                            class="rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 pl-3 pr-8 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="latest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="views">Most Views</option>
                            <option value="likes">Most Likes</option>
                            <option value="comments">Most Comments</option>
                            <option value="title">Title (A-Z)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Posts List Table / Cards -->
            <div class="mt-6 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                <!-- Table Header with Select All -->
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-6 py-3 bg-gray-50/50 dark:bg-gray-800/40 text-xs font-semibold text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            :checked="isAllSelected"
                            @change="toggleSelectAll"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                        />
                        <span>Story Title & Details</span>
                    </div>
                    <div class="flex items-center gap-8">
                        <span class="hidden sm:inline">Stats</span>
                        <span>Actions</span>
                    </div>
                </div>

                <!-- Story Row Items -->
                <div v-if="posts.data.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div
                        v-for="post in posts.data"
                        :key="post.id"
                        class="flex items-center justify-between p-5 hover:bg-gray-50/60 dark:hover:bg-gray-850/60 transition gap-4"
                    >
                        <div class="flex items-start gap-3.5 min-w-0 flex-1">
                            <input
                                type="checkbox"
                                :value="post.id"
                                v-model="selectedIds"
                                class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                            />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="route('posts.edit', post.id)"
                                        class="font-serif text-base font-bold text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 truncate"
                                    >
                                        {{ post.title || 'Untitled Draft' }}
                                    </Link>
                                    <span
                                        v-if="post.category"
                                        class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300"
                                    >
                                        {{ post.category.name }}
                                    </span>
                                </div>

                                <p v-if="post.excerpt" class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-1">
                                    {{ post.excerpt }}
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-gray-400">
                                    <span v-if="post.status === 'scheduled'">
                                        Scheduled for: <span class="text-indigo-600 dark:text-indigo-400 font-medium">{{ new Date(post.published_at).toLocaleString() }}</span>
                                    </span>
                                    <span v-else-if="post.status === 'published' && post.published_at">
                                        Published {{ new Date(post.published_at).toLocaleDateString() }}
                                    </span>
                                    <span v-else>
                                        Updated {{ new Date(post.updated_at).toLocaleDateString() }}
                                    </span>
                                    <span>•</span>
                                    <span>{{ post.reading_time_minutes }} min read</span>
                                </div>
                            </div>
                        </div>

                        <!-- Stats & Actions -->
                        <div class="flex items-center gap-6 flex-shrink-0">
                            <!-- Stats counters -->
                            <div class="hidden sm:flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1" title="Views">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>{{ post.views_count }}</span>
                                </div>

                                <div class="flex items-center gap-1" title="Likes">
                                    <svg class="w-4 h-4 text-rose-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    <span>{{ post.likes_count }}</span>
                                </div>

                                <div class="flex items-center gap-1" title="Comments">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <span>{{ post.comments_count }}</span>
                                </div>
                            </div>

                            <!-- Edit and View Actions -->
                            <div class="flex items-center gap-2">
                                <Link
                                    v-if="post.status === 'published'"
                                    :href="route('posts.show', post.slug)"
                                    class="rounded-lg border border-gray-200 dark:border-gray-700 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                                >
                                    View
                                </Link>

                                <Link
                                    :href="route('posts.edit', post.id)"
                                    class="rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-900 px-3 py-1 text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 transition"
                                >
                                    Edit
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-bold text-gray-900 dark:text-white">
                        No {{ filters.tab }} stories
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{
                            filters.search
                                ? 'No stories match your current search query.'
                                : filters.tab === 'published'
                                ? 'You have not published any stories yet.'
                                : filters.tab === 'drafts'
                                ? 'Your draft drawer is empty. Start writing!'
                                : filters.tab === 'scheduled'
                                ? 'You have no scheduled stories in queue.'
                                : 'You have no archived stories.'
                        }}
                    </p>
                    <div class="mt-4">
                        <Link
                            :href="route('posts.create')"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-indigo-500 transition"
                        >
                            Write a Story
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="posts.links && posts.links.length > 3" class="mt-6 flex justify-center">
                <nav class="flex items-center gap-1">
                    <Link
                        v-for="(link, idx) in posts.links"
                        :key="idx"
                        :href="link.url || '#'"
                        :class="[
                            link.active
                                ? 'bg-indigo-600 text-white font-bold'
                                : link.url
                                ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
                                : 'text-gray-300 dark:text-gray-700 cursor-not-allowed',
                            'px-3 py-1.5 rounded-lg text-xs font-medium transition'
                        ]"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
