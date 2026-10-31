<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import RichEditor from '@/Components/Editor/RichEditor.vue';
import PostSettingsDrawer from '@/Components/Editor/PostSettingsDrawer.vue';
import ScheduleModal from '@/Components/Editor/ScheduleModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useConfirmModal } from '@/Composables/useConfirmModal';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
    userSeries: {
        type: Array,
        default: () => [],
    },
    initialSeries: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    id: null,
    title: '',
    slug: '',
    category_id: '',
    series_id: props.initialSeries?.id || null,
    series_order: props.initialSeries?.next_order || null,
    excerpt: '',
    cover_image_path: '',
    seo_title: '',
    seo_description: '',
    content_html: '',
    content_json: null,
    tags: [],
    status: 'draft',
    published_at: null,
});

const isDirty = ref(false);
const isAutosaving = ref(false);
const isSubmitting = ref(false);
const lastSavedText = ref('');
const isSettingsOpen = ref(false);
const isScheduleModalOpen = ref(false);

let autosaveTimer = null;
let removeInertiaListener = null;

const markDirty = () => {
    isDirty.value = true;
};

const onEditorChange = ({ json, html }) => {
    form.content_html = html;
    form.content_json = json;
    markDirty();
};

// Word count and reading time computations
const wordCount = computed(() => {
    const text = (form.content_html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (!text) return 0;
    return text.split(/\s+/).filter(Boolean).length;
});

const readingTimeMinutes = computed(() => {
    return Math.max(1, Math.ceil(wordCount.value / 225));
});

const canPublish = computed(() => {
    return form.title.trim().length > 0 && wordCount.value >= 100;
});

// Perform autosave every 20 seconds
const performAutosave = async () => {
    if (!isDirty.value || isAutosaving.value || isSubmitting.value) return;
    if (!form.title.trim() && !form.content_html.trim()) return;

    isAutosaving.value = true;

    try {
        const response = await axios.post(route('posts.autosave'), {
            id: form.id,
            title: form.title,
            slug: form.slug,
            category_id: form.category_id || null,
            excerpt: form.excerpt,
            cover_image_path: form.cover_image_path,
            seo_title: form.seo_title,
            seo_description: form.seo_description,
            content_html: form.content_html,
            content_json: form.content_json,
            tags: form.tags,
        });

        if (response.data?.id) {
            form.id = response.data.id;
        }

        const date = new Date(response.data.saved_at || Date.now());
        lastSavedText.value = `Draft saved at ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
        isDirty.value = false;
    } catch (err) {
        console.error('Autosave error:', err);
    } finally {
        isAutosaving.value = false;
    }
};

const onBeforeUnload = (event) => {
    if (isDirty.value && !isSubmitting.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

const { modalState, confirm, alert, handleConfirm, handleClose } = useConfirmModal();

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);

    removeInertiaListener = router.on('before', (event) => {
        if (isDirty.value && !isSubmitting.value) {
            event.preventDefault();
            confirm({
                title: 'Unsaved Changes',
                message: 'You have unsaved changes in your draft. Are you sure you want to leave?',
                confirmText: 'Leave Page',
                variant: 'warning',
                onConfirm: () => {
                    isDirty.value = false;
                    router.visit(event.detail.visit.url);
                },
            });
        }
    });

    autosaveTimer = setInterval(() => {
        performAutosave();
    }, 20000);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    if (removeInertiaListener) removeInertiaListener();
    if (autosaveTimer) clearInterval(autosaveTimer);
});

// Submit actions
const submitDraft = () => {
    isSubmitting.value = true;
    isDirty.value = false;
    form.status = 'draft';
    form.published_at = null;
    form.post(route('posts.store'), {
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const submitPublish = () => {
    if (!form.title.trim()) {
        alert({
            title: 'Title Required',
            message: 'Please enter a title before publishing your story.',
            variant: 'warning',
        });
        return;
    }
    if (wordCount.value < 100) {
        alert({
            title: 'Word Count Minimum',
            message: `At least 100 words are required to publish. (Currently: ${wordCount.value} words)`,
            variant: 'warning',
        });
        return;
    }

    isSubmitting.value = true;
    isDirty.value = false;
    form.status = 'published';
    form.published_at = null;
    form.post(route('posts.store'), {
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const openSchedule = () => {
    if (!form.title.trim()) {
        alert({
            title: 'Title Required',
            message: 'Please enter a title before scheduling your story.',
            variant: 'warning',
        });
        return;
    }
    if (wordCount.value < 100) {
        alert({
            title: 'Word Count Minimum',
            message: `At least 100 words are required to schedule. (Currently: ${wordCount.value} words)`,
            variant: 'warning',
        });
        return;
    }
    isScheduleModalOpen.value = true;
};

const onConfirmSchedule = (scheduledDateTime) => {
    isScheduleModalOpen.value = false;
    isSubmitting.value = true;
    isDirty.value = false;
    form.status = 'scheduled';
    form.published_at = scheduledDateTime;
    form.post(route('posts.store'), {
        onError: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Write Story — Inkwell" />

    <div class="min-h-screen bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex flex-col">
        <!-- TOP WRITER BAR (Distraction-Free) -->
        <header class="sticky top-0 z-30 border-b border-gray-100 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95 backdrop-blur px-4 sm:px-8 py-3 flex items-center justify-between">
            <!-- Left: Back link and status -->
            <div class="flex items-center gap-4">
                <Link
                    :href="route('dashboard')"
                    class="rounded-lg p-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    title="Back to Dashboard"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>

                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                        Draft
                    </span>
                    <span v-if="isAutosaving" class="inline-flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-indigo-600 animate-ping"></span>
                        Saving...
                    </span>
                    <span v-else-if="lastSavedText" class="text-xs text-gray-400">
                        {{ lastSavedText }}
                    </span>
                    <span v-else-if="isDirty" class="text-xs text-amber-600 dark:text-amber-400">
                        Unsaved
                    </span>
                </div>
            </div>

            <!-- Center: Live Stats -->
            <div class="hidden md:flex items-center gap-3 text-xs text-gray-400">
                <span>{{ wordCount }} words</span>
                <span>•</span>
                <span>{{ readingTimeMinutes }} min read</span>
                <span v-if="wordCount < 100" class="text-amber-500 text-[11px]">
                    ({{ 100 - wordCount }} words to publish)
                </span>
            </div>

            <!-- Right: Actions & Settings -->
            <div class="flex items-center gap-2.5">
                <DarkModeToggle />

                <button
                    type="button"
                    @click="submitDraft"
                    :disabled="form.processing || isSubmitting"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition"
                >
                    Save Draft
                </button>

                <button
                    type="button"
                    @click="openSchedule"
                    :disabled="form.processing || isSubmitting"
                    class="hidden sm:inline-flex rounded-xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50 dark:bg-indigo-950/40 px-3.5 py-1.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 disabled:opacity-50 transition"
                >
                    Schedule...
                </button>

                <button
                    type="button"
                    @click="submitPublish"
                    :disabled="form.processing || isSubmitting || !canPublish"
                    :title="!canPublish ? 'Title and at least 100 words required' : 'Publish story now'"
                    class="rounded-xl bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    Publish
                </button>

                <button
                    type="button"
                    @click="isSettingsOpen = !isSettingsOpen"
                    class="rounded-xl border border-gray-300 dark:border-gray-700 p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    title="Story Settings Drawer"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- MAIN WRITING CANVAS -->
        <main class="flex-1 mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8 py-10">
            <!-- Title Input (Borderless, clean, prominent) -->
            <input
                type="text"
                v-model="form.title"
                @input="markDirty"
                placeholder="Title..."
                class="w-full font-serif text-3xl sm:text-5xl font-bold tracking-tight text-gray-900 dark:text-white placeholder:text-gray-300 dark:placeholder:text-gray-700 border-none bg-transparent p-0 focus:ring-0 focus:outline-none mb-6"
                autofocus
            />

            <!-- Rich Text Editor (TipTap) -->
            <RichEditor
                :html="form.content_html"
                :json="form.content_json"
                @change="onEditorChange"
                placeholder="Tell your story... Type / for blocks"
            />
        </main>

        <!-- RIGHT SETTINGS DRAWER -->
        <PostSettingsDrawer
            :isOpen="isSettingsOpen"
            :form="form"
            :categories="categories"
            :availableTags="availableTags"
            :userSeries="userSeries"
            :isEdit="false"
            @close="isSettingsOpen = false"
            @markDirty="markDirty"
        />

        <!-- SCHEDULE MODAL -->
        <ScheduleModal
            :isOpen="isScheduleModalOpen"
            :initialDate="form.published_at"
            @close="isScheduleModalOpen = false"
            @confirm="onConfirmSchedule"
        />

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
    </div>
</template>
