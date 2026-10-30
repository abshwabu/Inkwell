<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    posts: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const searchQuery = ref(props.filters.q || '');
const selectedStatus = ref(props.filters.status || '');
const selectedCategory = ref(props.filters.category || '');

const applyFilters = () => {
    router.get(route('admin.posts.index'), {
        q: searchQuery.value || undefined,
        status: selectedStatus.value || undefined,
        category: selectedCategory.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedStatus.value = '';
    selectedCategory.value = '';
    applyFilters();
};

const toggleFeature = (post) => {
    router.post(route('admin.posts.feature', post.id), {}, { preserveScroll: true });
};

const unpublishPost = (post) => {
    if (confirm(`Unpublish "${post.title}"? It will be returned to draft.`)) {
        router.post(route('admin.posts.unpublish', post.id), {}, { preserveScroll: true });
    }
};

const deletePost = (post) => {
    if (confirm(`Move "${post.title}" to trash?`)) {
        router.delete(route('admin.posts.destroy', post.id), { preserveScroll: true });
    }
};

const restorePost = (post) => {
    router.post(route('admin.posts.restore', post.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Manage Stories" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Stories & Content
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Search, feature editor picks, unpublish, soft-delete, and restore articles.
                    </p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search title, slug, or author..."
                        @keyup.enter="applyFilters"
                        class="w-full sm:w-72 rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">All Statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="archived">Archived</option>
                        <option value="trashed">Trash (Deleted)</option>
                    </select>

                    <select
                        v-model="selectedCategory"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">All Topics</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="applyFilters"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 transition"
                    >
                        Apply Filters
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 transition"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Posts Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3.5">Story</th>
                                <th class="px-4 py-3.5">Author</th>
                                <th class="px-4 py-3.5">Topic</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-4 py-3.5">Stats</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                            <tr v-for="post in posts.data" :key="post.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                                <td class="px-5 py-4 max-w-xs">
                                    <div class="flex items-start gap-2">
                                        <button
                                            type="button"
                                            @click="toggleFeature(post)"
                                            class="mt-0.5 text-amber-400 hover:text-amber-500"
                                            :title="post.is_featured ? 'Remove from Editor\'s Picks' : 'Make Editor\'s Pick'"
                                        >
                                            <svg class="h-4 w-4" :fill="post.is_featured ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                            </svg>
                                        </button>
                                        <div>
                                            <a
                                                :href="route('posts.show.canonical', { category_slug: post.category ? post.category.slug : 'story', post_slug: post.slug })"
                                                target="_blank"
                                                class="font-semibold text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400 line-clamp-1"
                                            >
                                                {{ post.title }}
                                            </a>
                                            <p class="text-[10px] text-gray-400 line-clamp-1">/{{ post.slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <img :src="post.user.avatar_url" class="h-6 w-6 rounded-full object-cover" />
                                        <span>{{ post.user.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span v-if="post.category" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                        {{ post.category.name }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span
                                        v-if="post.deleted_at"
                                        class="rounded bg-rose-50 px-2 py-0.5 font-bold uppercase tracking-wider text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 text-[10px]"
                                    >
                                        Trash
                                    </span>
                                    <span
                                        v-else
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300': post.status === 'published',
                                            'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300': post.status === 'draft',
                                            'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300': post.status === 'scheduled',
                                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': post.status === 'archived',
                                        }"
                                        class="rounded px-2 py-0.5 font-bold uppercase tracking-wider text-[10px]"
                                    >
                                        {{ post.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {{ post.views_count }} views • {{ post.likes_count }} likes
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            v-if="post.status === 'published' && !post.deleted_at"
                                            type="button"
                                            @click="unpublishPost(post)"
                                            class="rounded px-2 py-1 text-xs text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40"
                                            title="Unpublish"
                                        >
                                            Unpublish
                                        </button>

                                        <button
                                            v-if="!post.deleted_at"
                                            type="button"
                                            @click="deletePost(post)"
                                            class="rounded px-2 py-1 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            title="Delete story"
                                        >
                                            Trash
                                        </button>

                                        <button
                                            v-else
                                            type="button"
                                            @click="restorePost(post)"
                                            class="rounded px-2 py-1 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                                            title="Restore story"
                                        >
                                            Restore
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!posts.data || posts.data.length === 0">
                                <td colspan="6" class="p-8 text-center text-gray-400">
                                    No stories match your search criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="posts.links && posts.links.length > 3" class="flex justify-center gap-1 border-t border-gray-100 p-4 dark:border-gray-800">
                    <Link
                        v-for="(link, i) in posts.links"
                        :key="i"
                        :href="link.url || '#'"
                        :class="[
                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                            'px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
