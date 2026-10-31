<script setup>
import { ref, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useConfirmModal } from '@/Composables/useConfirmModal';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

// Create / Edit modal state
const editModalOpen = ref(false);
const editingCategory = ref(null);
const categoryForm = ref({
    name: '',
    slug: '',
    description: '',
    color: '#6366f1',
});

const openCreateModal = () => {
    editingCategory.value = null;
    categoryForm.value = { name: '', slug: '', description: '', color: '#6366f1' };
    editModalOpen.value = true;
};

const openEditModal = (cat) => {
    editingCategory.value = cat;
    categoryForm.value = {
        name: cat.name,
        slug: cat.slug,
        description: cat.description || '',
        color: cat.color || '#6366f1',
    };
    editModalOpen.value = true;
};

const saveCategory = () => {
    if (editingCategory.value) {
        router.put(route('admin.categories.update', editingCategory.value.id), categoryForm.value, {
            preserveScroll: true,
            onSuccess: () => { editModalOpen.value = false; },
        });
    } else {
        router.post(route('admin.categories.store'), categoryForm.value, {
            preserveScroll: true,
            onSuccess: () => { editModalOpen.value = false; },
        });
    }
};

// Reorder Up / Down
const moveCategory = (index, direction) => {
    const list = [...props.categories];
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= list.length) return;

    const temp = list[index];
    list[index] = list[targetIndex];
    list[targetIndex] = temp;

    const orderedIds = list.map(c => c.id);
    router.post(route('admin.categories.reorder'), { order: orderedIds }, { preserveScroll: true });
};

// Delete with Reassignment Modal
const deleteModalOpen = ref(false);
const categoryToDelete = ref(null);
const reassignTargetId = ref('');

const { modalState, alert, handleConfirm, handleClose } = useConfirmModal();

const openDeleteModal = (cat) => {
    categoryToDelete.value = cat;
    reassignTargetId.value = '';
    deleteModalOpen.value = true;
};

const confirmDelete = () => {
    if (!categoryToDelete.value) return;

    if (categoryToDelete.value.posts_count > 0 && !reassignTargetId.value) {
        alert({
            title: 'Replacement Topic Required',
            message: 'You must select a replacement topic to reassign existing stories to before deleting.',
            variant: 'warning',
        });
        return;
    }

    router.delete(route('admin.categories.destroy', categoryToDelete.value.id), {
        data: { reassign_to_id: reassignTargetId.value || undefined },
        preserveScroll: true,
        onSuccess: () => { deleteModalOpen.value = false; },
    });
};
</script>

<template>
    <Head title="Manage Topics" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Topic Categories
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Create topics, reorder front-page presentation, and manage safe reassignment.
                    </p>
                </div>

                <button
                    type="button"
                    @click="openCreateModal"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 transition"
                >
                    + New Topic
                </button>
            </div>

            <!-- Categories List Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50/75 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3.5 w-16 text-center">Order</th>
                            <th class="px-4 py-3.5">Topic</th>
                            <th class="px-4 py-3.5">Slug</th>
                            <th class="px-4 py-3.5">Stories</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                        <tr v-for="(cat, idx) in categories" :key="cat.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <div v-if="isAdmin" class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        :disabled="idx === 0"
                                        @click="moveCategory(idx, -1)"
                                        class="p-1 rounded text-gray-400 hover:text-indigo-600 disabled:opacity-30"
                                        title="Move up"
                                    >
                                        ▲
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="idx === categories.length - 1"
                                        @click="moveCategory(idx, 1)"
                                        class="p-1 rounded text-gray-400 hover:text-indigo-600 disabled:opacity-30"
                                        title="Move down"
                                    >
                                        ▼
                                    </button>
                                </div>
                                <span v-else class="text-gray-400 font-mono">{{ idx + 1 }}</span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <span class="h-3.5 w-3.5 rounded-full" :style="{ backgroundColor: cat.color || '#6366f1' }"></span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ cat.name }}</span>
                                </div>
                                <p v-if="cat.description" class="text-[11px] text-gray-400 mt-0.5 truncate max-w-sm">{{ cat.description }}</p>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap font-mono text-gray-500 dark:text-gray-400">
                                /{{ cat.slug }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-gray-600 dark:text-gray-300 font-semibold">
                                {{ cat.posts_count }}
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEditModal(cat)"
                                        class="rounded px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="isAdmin"
                                        type="button"
                                        @click="openDeleteModal(cat)"
                                        class="rounded px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create / Edit Category Modal -->
        <div
            v-if="editModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="editModalOpen = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                    {{ editingCategory ? 'Edit Topic Category' : 'Create New Topic Category' }}
                </h3>

                <form @submit.prevent="saveCategory" class="mt-4 space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            Topic Name
                        </label>
                        <input
                            v-model="categoryForm.name"
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
                            v-model="categoryForm.slug"
                            type="text"
                            placeholder="auto-generated-from-name"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white font-mono"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            Accent Color
                        </label>
                        <div class="flex items-center gap-3">
                            <input
                                v-model="categoryForm.color"
                                type="color"
                                class="h-8 w-12 cursor-pointer rounded border border-gray-200 bg-transparent p-0"
                            />
                            <input
                                v-model="categoryForm.color"
                                type="text"
                                class="w-28 rounded-xl border border-gray-200 bg-gray-50 p-2 text-xs font-mono text-gray-900 focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            Description
                        </label>
                        <textarea
                            v-model="categoryForm.description"
                            rows="2"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        ></textarea>
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
                            Save Topic
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Category with Reassignment Modal -->
        <div
            v-if="deleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="deleteModalOpen = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <h3 class="text-base font-bold text-rose-600 dark:text-rose-400">
                    Delete Topic Category: "{{ categoryToDelete?.name }}"
                </h3>

                <div v-if="categoryToDelete?.posts_count > 0" class="mt-3 space-y-3 text-xs">
                    <p class="text-gray-700 dark:text-gray-300">
                        This category currently contains <strong class="text-rose-600">{{ categoryToDelete.posts_count }} stories</strong>. Inkwell requires reassigning these stories before deletion to ensure links and feeds remain intact.
                    </p>

                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            Reassign Stories to:
                        </label>
                        <select
                            v-model="reassignTargetId"
                            required
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">Select a replacement topic...</option>
                            <option
                                v-for="c in categories.filter(c => c.id !== categoryToDelete.id)"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <p v-else class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    This category has no stories and can be safely deleted.
                </p>

                <div class="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button
                        type="button"
                        @click="deleteModalOpen = false"
                        class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete"
                        class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 transition"
                    >
                        Confirm Delete & Reassign
                    </button>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="modalState.show"
            :title="modalState.title"
            :message="modalState.message"
            :confirm-text="modalState.confirmText"
            :cancel-text="modalState.cancelText"
            :variant="modalState.variant"
            :type="modalState.type"
            @confirm="handleConfirm"
            @close="handleClose"
        />
    </AdminLayout>
</template>
