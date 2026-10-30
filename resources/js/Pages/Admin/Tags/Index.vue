<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    tags: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const searchQuery = ref(props.filters.q || '');

const search = () => {
    router.get(route('admin.tags.index'), { q: searchQuery.value || undefined }, { preserveState: true, replace: true });
};

// Create / Edit Tag Modal
const editModalOpen = ref(false);
const editingTag = ref(null);
const tagForm = ref({ name: '', slug: '' });

const openCreateModal = () => {
    editingTag.value = null;
    tagForm.value = { name: '', slug: '' };
    editModalOpen.value = true;
};

const openEditModal = (tag) => {
    editingTag.value = tag;
    tagForm.value = { name: tag.name, slug: tag.slug };
    editModalOpen.value = true;
};

const saveTag = () => {
    if (editingTag.value) {
        router.put(route('admin.tags.update', editingTag.value.id), tagForm.value, {
            preserveScroll: true,
            onSuccess: () => { editModalOpen.value = false; },
        });
    } else {
        router.post(route('admin.tags.store'), tagForm.value, {
            preserveScroll: true,
            onSuccess: () => { editModalOpen.value = false; },
        });
    }
};

// Merge Tag Modal
const mergeModalOpen = ref(false);
const sourceTag = ref(null);
const targetTagId = ref('');

const openMergeModal = (tag) => {
    sourceTag.value = tag;
    targetTagId.value = '';
    mergeModalOpen.value = true;
};

const confirmMerge = () => {
    if (!sourceTag.value || !targetTagId.value) return;

    router.post(route('admin.tags.merge', sourceTag.value.id), {
        target_tag_id: targetTagId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { mergeModalOpen.value = false; },
    });
};

const deleteTag = (tag) => {
    if (confirm(`Delete tag "#${tag.name}"? It will be removed from all stories.`)) {
        router.delete(route('admin.tags.destroy', tag.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Manage Tags" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Tags & Keywords
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Organize content tags, correct typos, and merge redundant topics into canonical tags.
                    </p>
                </div>

                <button
                    type="button"
                    @click="openCreateModal"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 transition"
                >
                    + New Tag
                </button>
            </div>

            <!-- Search Bar -->
            <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search tag name or slug..."
                    @keyup.enter="search"
                    class="w-full sm:w-80 rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
                <button
                    type="button"
                    @click="search"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500"
                >
                    Search
                </button>
            </div>

            <!-- Tags Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50/75 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3.5">Tag</th>
                            <th class="px-4 py-3.5">Slug</th>
                            <th class="px-4 py-3.5">Stories Attached</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                        <tr v-for="tag in tags.data" :key="tag.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                            <td class="px-5 py-3.5 whitespace-nowrap font-semibold text-gray-900 dark:text-white">
                                #{{ tag.name }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap font-mono text-gray-400">
                                /tag/{{ tag.slug }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-600 dark:text-gray-300 font-semibold">
                                {{ tag.posts_count || 0 }}
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEditModal(tag)"
                                        class="rounded px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                                    >
                                        Edit
                                    </button>

                                    <!-- Merge (Admin Only) -->
                                    <button
                                        v-if="isAdmin"
                                        type="button"
                                        @click="openMergeModal(tag)"
                                        class="rounded px-2.5 py-1 text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/40"
                                        title="Merge this tag into another"
                                    >
                                        Merge
                                    </button>

                                    <!-- Delete (Admin Only) -->
                                    <button
                                        v-if="isAdmin"
                                        type="button"
                                        @click="deleteTag(tag)"
                                        class="rounded px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="tags.links && tags.links.length > 3" class="flex justify-center gap-1 border-t border-gray-100 p-4 dark:border-gray-800">
                    <Link
                        v-for="(link, i) in tags.links"
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

        <!-- Create / Edit Modal -->
        <div
            v-if="editModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="editModalOpen = false"
        >
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                    {{ editingTag ? 'Edit Tag' : 'Create Tag' }}
                </h3>

                <form @submit.prevent="saveTag" class="mt-4 space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            Tag Name
                        </label>
                        <input
                            v-model="tagForm.name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            Slug (Optional)
                        </label>
                        <input
                            v-model="tagForm.slug"
                            type="text"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white font-mono"
                        />
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            @click="editModalOpen = false"
                            class="rounded-xl px-4 py-2 font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                        >
                            Save Tag
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Merge Tag Modal -->
        <div
            v-if="mergeModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="mergeModalOpen = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <h3 class="text-base font-bold text-amber-600 dark:text-amber-400">
                    Merge Tag "#{{ sourceTag?.name }}"
                </h3>
                <p class="mt-2 text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                    Merging will transfer all <strong class="text-indigo-600">{{ sourceTag?.posts_count }} stories</strong> attached to "#{{ sourceTag?.name }}" into the destination tag, and then permanently delete "#{{ sourceTag?.name }}".
                </p>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                        Select Destination Tag:
                    </label>
                    <select
                        v-model="targetTagId"
                        required
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">Select canonical destination tag...</option>
                        <option
                            v-for="t in tags.data.filter(t => t.id !== sourceTag.id)"
                            :key="t.id"
                            :value="t.id"
                        >
                            #{{ t.name }} ({{ t.posts_count }} stories)
                        </option>
                    </select>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button
                        type="button"
                        @click="mergeModalOpen = false"
                        class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="confirmMerge"
                        class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-500 transition"
                    >
                        Confirm Merge
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
