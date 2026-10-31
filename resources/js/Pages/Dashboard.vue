<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

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
    seriesList: {
        type: Array,
        default: () => [],
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

// Series Management State
const localSeriesList = ref(props.seriesList ? JSON.parse(JSON.stringify(props.seriesList)) : []);
watch(() => props.seriesList, (newVal) => {
    localSeriesList.value = newVal ? JSON.parse(JSON.stringify(newVal)) : [];
}, { deep: true });

// Create / Edit Series Modal
const isSeriesModalOpen = ref(false);
const editingSeries = ref(null);
const seriesForm = ref({
    title: '',
    description: '',
    cover_image_path: '',
    status: 'ongoing',
});
const isSavingSeries = ref(false);
const seriesFormError = ref('');

const openCreateSeriesModal = () => {
    editingSeries.value = null;
    seriesForm.value = {
        title: '',
        description: '',
        cover_image_path: '',
        status: 'ongoing',
    };
    seriesFormError.value = '';
    isSeriesModalOpen.value = true;
};

const openEditSeriesModal = (s) => {
    editingSeries.value = s;
    seriesForm.value = {
        title: s.title,
        description: s.description || '',
        cover_image_path: s.cover_image_path || '',
        status: s.status,
    };
    seriesFormError.value = '';
    isSeriesModalOpen.value = true;
};

const saveSeries = async () => {
    if (!seriesForm.value.title.trim()) {
        seriesFormError.value = 'Series title is required.';
        return;
    }

    isSavingSeries.value = true;
    seriesFormError.value = '';

    try {
        if (editingSeries.value) {
            await axios.put(route('series.update', editingSeries.value.id), seriesForm.value);
        } else {
            await axios.post(route('series.store'), seriesForm.value);
        }
        isSeriesModalOpen.value = false;
        router.reload({ only: ['seriesList', 'counts'] });
    } catch (err) {
        seriesFormError.value = err.response?.data?.message || 'Failed to save series.';
    } finally {
        isSavingSeries.value = false;
    }
};

const changeSeriesStatus = async (s, newStatus) => {
    try {
        await axios.put(route('series.update', s.id), {
            title: s.title,
            description: s.description,
            cover_image_path: s.cover_image_path,
            status: newStatus,
        });
        s.status = newStatus;
    } catch (err) {
        alert('Failed to update status.');
    }
};

const deleteSeries = async (s) => {
    if (!confirm(`Are you sure you want to delete "${s.title}"? Stories in this series will NOT be deleted; they will be detached as standalone posts.`)) {
        return;
    }

    try {
        await axios.delete(route('series.destroy', s.id));
        router.reload({ only: ['seriesList', 'counts'] });
    } catch (err) {
        alert('Failed to delete series.');
    }
};

// Reordering parts
const draggedPart = ref(null);
const draggedSeriesId = ref(null);

const onDragStart = (seriesId, partIndex) => {
    draggedSeriesId.value = seriesId;
    draggedPart.value = partIndex;
};

const onDrop = async (series, targetIndex) => {
    if (draggedSeriesId.value !== series.id || draggedPart.value === null) return;
    const fromIndex = draggedPart.value;
    if (fromIndex === targetIndex) return;

    const parts = series.posts;
    const [moved] = parts.splice(fromIndex, 1);
    parts.splice(targetIndex, 0, moved);

    draggedPart.value = null;
    draggedSeriesId.value = null;

    await savePartsOrder(series);
};

const movePart = async (series, fromIndex, direction) => {
    const toIndex = fromIndex + direction;
    if (toIndex < 0 || toIndex >= series.posts.length) return;

    const parts = series.posts;
    const [moved] = parts.splice(fromIndex, 1);
    parts.splice(toIndex, 0, moved);

    await savePartsOrder(series);
};

const isReordering = ref(false);
const savePartsOrder = async (series) => {
    isReordering.value = true;
    try {
        await axios.post(route('series.reorder', series.id), {
            ordered_ids: series.posts.map(p => p.id),
        });
        series.posts.forEach((p, idx) => {
            p.series_order = idx + 1;
        });
    } catch (err) {
        alert('Failed to reorder parts.');
        router.reload({ only: ['seriesList'] });
    } finally {
        isReordering.value = false;
    }
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

                    <button
                        type="button"
                        @click="switchTab('series')"
                        :class="[
                            filters.tab === 'series'
                                ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200',
                            'flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold transition'
                        ]"
                    >
                        <span>Series</span>
                        <span
                            :class="[
                                filters.tab === 'series'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                'rounded-full px-2 py-0.5 text-xs'
                            ]"
                        >
                            {{ counts.series || 0 }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Series Tab Content -->
            <div v-if="filters.tab === 'series'" class="mt-6 space-y-6">
                <!-- Series Tab Header Bar -->
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-serif text-xl font-bold text-gray-900 dark:text-white">Your Series</h2>
                        <p class="text-xs text-gray-500">Manage episodic sagas and reorder chapters</p>
                    </div>

                    <button
                        type="button"
                        @click="openCreateSeriesModal"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Series
                    </button>
                </div>

                <!-- Series List -->
                <div v-if="localSeriesList && localSeriesList.length > 0" class="space-y-6">
                    <div
                        v-for="s in localSeriesList"
                        :key="s.id"
                        class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <!-- Series Header Card -->
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-4 border-b border-gray-100 dark:border-gray-800">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-serif text-xl font-bold text-gray-900 dark:text-white">
                                        {{ s.title }}
                                    </h3>

                                    <!-- Status Dropdown Selector -->
                                    <select
                                        :value="s.status"
                                        @change="changeSeriesStatus(s, $event.target.value)"
                                        class="rounded-full py-0.5 pl-2.5 pr-7 text-[11px] font-semibold border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                                    >
                                        <option value="ongoing">Ongoing</option>
                                        <option value="completed">Completed</option>
                                        <option value="hiatus">Hiatus</option>
                                    </select>
                                </div>

                                <p v-if="s.description" class="mt-1 text-xs text-gray-600 dark:text-gray-300 max-w-2xl">
                                    {{ s.description }}
                                </p>

                                <div class="mt-2 flex items-center gap-4 text-xs text-gray-400">
                                    <span>{{ s.posts ? s.posts.length : 0 }} parts</span>
                                    <span>•</span>
                                    <span>{{ s.published_posts_count || 0 }} published</span>
                                </div>
                            </div>

                            <!-- Actions Row -->
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="route('posts.create', { series: s.slug })"
                                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Write Next Part
                                </Link>

                                <Link
                                    :href="route('series.show', s.slug)"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 transition"
                                >
                                    Public Page
                                </Link>

                                <button
                                    type="button"
                                    @click="openEditSeriesModal(s)"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 transition"
                                >
                                    Edit Details
                                </button>

                                <button
                                    type="button"
                                    @click="deleteSeries(s)"
                                    class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-400 transition"
                                    title="Delete series"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>

                        <!-- Parts List (Drag-and-Drop & Move Up/Down) -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Parts (Drag or use arrows to reorder)
                                </span>
                                <span v-if="isReordering" class="text-xs text-indigo-600 animate-pulse font-medium">
                                    Saving order...
                                </span>
                            </div>

                            <div v-if="s.posts && s.posts.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800 rounded-xl border border-gray-100 dark:border-gray-800 overflow-hidden bg-gray-50/50 dark:bg-gray-800/40">
                                <div
                                    v-for="(part, idx) in s.posts"
                                    :key="part.id"
                                    draggable="true"
                                    @dragstart="onDragStart(s.id, idx)"
                                    @dragover.prevent
                                    @drop="onDrop(s, idx)"
                                    class="flex items-center justify-between p-3.5 hover:bg-white dark:hover:bg-gray-800 transition cursor-grab active:cursor-grabbing gap-3"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <!-- Reorder Arrows & Drag Handle -->
                                        <div class="flex items-center gap-1 text-gray-400">
                                            <button
                                                type="button"
                                                @click="movePart(s, idx, -1)"
                                                :disabled="idx === 0"
                                                class="p-0.5 hover:text-indigo-600 disabled:opacity-30 disabled:hover:text-gray-400"
                                                title="Move up"
                                            >
                                                ▲
                                            </button>
                                            <button
                                                type="button"
                                                @click="movePart(s, idx, 1)"
                                                :disabled="idx === s.posts.length - 1"
                                                class="p-0.5 hover:text-indigo-600 disabled:opacity-30 disabled:hover:text-gray-400"
                                                title="Move down"
                                            >
                                                ▼
                                            </button>
                                            <span class="text-gray-300 dark:text-gray-600 select-none ml-1">⋮⋮</span>
                                        </div>

                                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 w-8">
                                            #{{ idx + 1 }}
                                        </span>

                                        <div class="truncate">
                                            <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                                <Link :href="route('posts.edit', part.id)" class="hover:underline">
                                                    {{ part.title }}
                                                </Link>
                                            </h4>
                                            <div class="flex items-center gap-2 text-[11px] text-gray-400 mt-0.5">
                                                <span :class="part.status === 'published' ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-amber-600 dark:text-amber-400 font-semibold'">
                                                    {{ part.status === 'published' ? 'Published' : 'Draft' }}
                                                </span>
                                                <span v-if="part.category">•</span>
                                                <span v-if="part.category">{{ part.category.name }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <Link
                                            :href="route('posts.edit', part.id)"
                                            class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                        >
                                            Edit Story
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-6 text-center text-xs text-gray-400">
                                No stories in this series yet. Click "Write Next Part" above to add the first chapter.
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No series created yet</h3>
                    <p class="mt-1 text-xs text-gray-500">Group your multi-part stories, tutorials, or sagas into a series.</p>
                    <div class="mt-6">
                        <button
                            type="button"
                            @click="openCreateSeriesModal"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-indigo-500 transition"
                        >
                            + Create Your First Series
                        </button>
                    </div>
                </div>
            </div>

            <!-- Posts Tab (Published, Drafts, Scheduled, Archived) -->
            <template v-else>
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
            </template>
        </div>

        <!-- Create / Edit Series Modal -->
        <div v-if="isSeriesModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white">
                        {{ editingSeries ? 'Edit Series Details' : 'Create New Series' }}
                    </h3>
                    <button type="button" @click="isSeriesModalOpen = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Series Title</label>
                    <input
                        type="text"
                        v-model="seriesForm.title"
                        placeholder="e.g. Distributed Systems in Practice"
                        class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Description (Optional)</label>
                    <textarea
                        rows="3"
                        v-model="seriesForm.description"
                        placeholder="A brief overview of what readers will learn or experience..."
                        class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Cover Image URL (Optional)</label>
                    <input
                        type="url"
                        v-model="seriesForm.cover_image_path"
                        placeholder="https://... or /storage/..."
                        class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Publication Status</label>
                    <select
                        v-model="seriesForm.status"
                        class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    >
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="hiatus">Hiatus</option>
                    </select>
                </div>

                <p v-if="seriesFormError" class="text-xs text-red-500">{{ seriesFormError }}</p>

                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <button
                        type="button"
                        @click="isSeriesModalOpen = false"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="saveSeries"
                        :disabled="isSavingSeries"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition"
                    >
                        {{ isSavingSeries ? 'Saving...' : (editingSeries ? 'Update Series' : 'Create Series') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
