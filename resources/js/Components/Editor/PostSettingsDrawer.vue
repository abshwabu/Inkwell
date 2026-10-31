<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    form: {
        type: Object,
        required: true,
    },
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
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'markDirty', 'unpublish', 'archive', 'delete']);

const fileInputRef = ref(null);
const isUploadingCover = ref(false);
const coverUploadError = ref('');

// Tag input state
const tagInput = ref('');
const isTagDropdownOpen = ref(false);

// Series management state
const userSeriesList = ref([...props.userSeries]);
const isSeriesEnabled = ref(Boolean(props.form.series_id));
const isCustomOrderOpen = ref(false);
const showCreateSeriesModal = ref(false);
const isCreatingSeries = ref(false);
const createSeriesError = ref('');

const newSeriesForm = ref({
    title: '',
    description: '',
    cover_image_path: '',
    status: 'ongoing',
});

const toggleSeries = () => {
    if (isSeriesEnabled.value) {
        if (!props.form.series_id && userSeriesList.value.length > 0) {
            selectSeries(userSeriesList.value[0].id);
        } else if (userSeriesList.value.length === 0) {
            showCreateSeriesModal.value = true;
        }
    } else {
        props.form.series_id = null;
        props.form.series_order = null;
        emit('markDirty');
    }
};

const selectSeries = (seriesId) => {
    props.form.series_id = seriesId ? Number(seriesId) : null;
    if (props.form.series_id) {
        const found = userSeriesList.value.find((s) => s.id === props.form.series_id);
        if (found && !props.form.series_order) {
            props.form.series_order = (found.posts_count || 0) + 1;
        }
    } else {
        props.form.series_order = null;
    }
    emit('markDirty');
};

const selectedSeriesObject = computed(() => {
    if (!props.form.series_id) return null;
    return userSeriesList.value.find((s) => s.id === Number(props.form.series_id)) || null;
});

const submitCreateSeries = async () => {
    if (!newSeriesForm.value.title.trim()) {
        createSeriesError.value = 'Title is required for the series.';
        return;
    }

    isCreatingSeries.value = true;
    createSeriesError.value = '';

    try {
        const response = await axios.post(route('series.store'), newSeriesForm.value, {
            headers: { Accept: 'application/json' },
        });

        const createdSeries = response.data.series;
        userSeriesList.value.unshift(createdSeries);
        props.form.series_id = createdSeries.id;
        props.form.series_order = 1;
        isSeriesEnabled.value = true;
        showCreateSeriesModal.value = false;
        newSeriesForm.value = { title: '', description: '', cover_image_path: '', status: 'ongoing' };
        emit('markDirty');
    } catch (err) {
        createSeriesError.value = err.response?.data?.message || 'Failed to create series.';
    } finally {
        isCreatingSeries.value = false;
    }
};

const filteredTags = computed(() => {
    const q = tagInput.value.toLowerCase().trim();
    if (!q) return [];
    const currentTags = Array.isArray(props.form.tags) ? props.form.tags : [];
    return props.availableTags.filter(
        (t) =>
            t.name.toLowerCase().includes(q) &&
            !currentTags.some((ct) => (typeof ct === 'object' ? ct.name : ct).toLowerCase() === t.name.toLowerCase())
    );
});

// Add tag (existing or new)
const addTag = (tagName) => {
    const name = (tagName || tagInput.value).trim();
    if (!name) return;

    if (!Array.isArray(props.form.tags)) {
        props.form.tags = [];
    }

    if (props.form.tags.length >= 5) {
        tagInput.value = '';
        isTagDropdownOpen.value = false;
        return;
    }

    // Prevent duplicates
    const alreadyExists = props.form.tags.some(
        (t) => (typeof t === 'object' ? t.name : t).toLowerCase() === name.toLowerCase()
    );

    if (!alreadyExists) {
        props.form.tags.push(name);
        emit('markDirty');
    }

    tagInput.value = '';
    isTagDropdownOpen.value = false;
};

const removeTag = (index) => {
    props.form.tags.splice(index, 1);
    emit('markDirty');
};

// Cover Image upload
const handleCoverFileSelect = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    isUploadingCover.value = true;
    coverUploadError.value = '';

    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post(route('uploads.images'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        props.form.cover_image_path = response.data.url;
        emit('markDirty');
    } catch (err) {
        coverUploadError.value = err.response?.data?.message || 'Failed to upload cover image.';
    } finally {
        isUploadingCover.value = false;
    }
};

const removeCover = () => {
    props.form.cover_image_path = '';
    emit('markDirty');
};

// Auto-suggest excerpt from content
const autoSuggestExcerpt = () => {
    const raw = (props.form.content_html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (!raw) return;

    props.form.excerpt = raw.slice(0, 160).trim();
    emit('markDirty');
};

// Slug formatting
const onSlugInput = () => {
    if (props.form.slug) {
        props.form.slug = props.form.slug
            .toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
    emit('markDirty');
};

const regenerateSlug = () => {
    if (props.form.title) {
        props.form.slug = props.form.title
            .toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        emit('markDirty');
    }
};

// SEO preview computations
const previewTitle = computed(() => {
    return props.form.seo_title || props.form.title || 'Untitled Story — Inkwell';
});

const previewDescription = computed(() => {
    return props.form.seo_description || props.form.excerpt || 'Read this story on Inkwell, an open publishing platform.';
});
</script>

<template>
    <div>
        <!-- Backdrop -->
        <div
            v-if="isOpen"
            @click="emit('close')"
            class="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm transition-opacity"
        ></div>

        <!-- Right Side Drawer -->
        <aside
            :class="[
                isOpen ? 'translate-x-0' : 'translate-x-full',
                'fixed top-0 right-0 z-50 h-full w-full max-w-md bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800 shadow-2xl transition-transform duration-300 ease-in-out flex flex-col',
            ]"
        >
            <!-- Drawer Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    Story Settings
                </h2>
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg p-1.5 text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Drawer Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Cover Image -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Cover Image
                    </label>

                    <div v-if="form.cover_image_path" class="mt-2 relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 group">
                        <img :src="form.cover_image_path" alt="Cover Preview" class="w-full h-40 object-cover" />
                        <button
                            type="button"
                            @click="removeCover"
                            class="absolute top-2 right-2 rounded-lg bg-red-600/90 text-white p-1 text-xs shadow hover:bg-red-700 transition"
                            title="Remove cover image"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>

                    <div v-else class="mt-2">
                        <div
                            @click="fileInputRef && fileInputRef.click()"
                            class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl p-5 hover:border-indigo-500 dark:hover:border-indigo-400 cursor-pointer transition text-center"
                        >
                            <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">Click to upload cover</span>
                            <span class="text-[11px] text-gray-400 mt-0.5">JPEG, PNG, WebP up to 5 MB</span>
                        </div>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleCoverFileSelect"
                        />
                    </div>

                    <p v-if="isUploadingCover" class="mt-2 text-xs text-indigo-600 animate-pulse">Uploading cover image...</p>
                    <p v-if="coverUploadError" class="mt-2 text-xs text-red-600">{{ coverUploadError }}</p>

                    <div class="mt-3">
                        <input
                            type="url"
                            v-model="form.cover_image_path"
                            @input="emit('markDirty')"
                            placeholder="Or paste image URL..."
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400"
                        />
                    </div>
                </div>

                <!-- Excerpt with Auto-Suggest -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Excerpt / Summary
                        </label>
                        <button
                            type="button"
                            @click="autoSuggestExcerpt"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 underline"
                        >
                            Auto-suggest (160 chars)
                        </button>
                    </div>

                    <textarea
                        rows="3"
                        v-model="form.excerpt"
                        @input="emit('markDirty')"
                        maxlength="300"
                        placeholder="A brief teaser to catch readers' attention in feeds..."
                        class="mt-2 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                    <div class="mt-1 flex justify-between text-[11px] text-gray-400">
                        <span>Used in story cards and social shares</span>
                        <span>{{ form.excerpt ? form.excerpt.length : 0 }} / 160</span>
                    </div>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Category
                    </label>
                    <select
                        v-model="form.category_id"
                        @change="emit('markDirty')"
                        class="mt-2 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">No category selected</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <!-- Story Series Section -->
                <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Story Series</span>
                            <p class="text-[11px] text-gray-400">Group this story as part of a multi-part saga</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="isSeriesEnabled"
                                @change="toggleSeries"
                                class="sr-only peer"
                            />
                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    <!-- Series Selection & Position Controls -->
                    <div v-if="isSeriesEnabled" class="mt-3 space-y-3 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-xl border border-gray-200 dark:border-gray-700">
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-[11px] font-semibold text-gray-700 dark:text-gray-300">Select Series</label>
                                <button
                                    type="button"
                                    @click="showCreateSeriesModal = true"
                                    class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    + New Series
                                </button>
                            </div>

                            <select
                                :value="form.series_id"
                                @change="selectSeries($event.target.value)"
                                class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">-- Choose a Series --</option>
                                <option v-for="s in userSeriesList" :key="s.id" :value="s.id">
                                    {{ s.title }} ({{ s.posts_count || 0 }} {{ (s.posts_count || 0) === 1 ? 'part' : 'parts' }})
                                </option>
                            </select>
                        </div>

                        <!-- Part Number & Move Option -->
                        <div v-if="form.series_id" class="pt-2 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-300 font-medium">
                                Position: <span class="text-indigo-600 dark:text-indigo-400 font-bold">Part {{ form.series_order || 1 }}</span>
                            </span>

                            <button
                                type="button"
                                @click="isCustomOrderOpen = !isCustomOrderOpen"
                                class="text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 font-semibold underline"
                            >
                                {{ isCustomOrderOpen ? 'Done' : 'Move' }}
                            </button>
                        </div>

                        <div v-if="isCustomOrderOpen && form.series_id" class="mt-2">
                            <label class="block text-[11px] text-gray-500 dark:text-gray-400">Choose Part Number</label>
                            <input
                                type="number"
                                min="1"
                                v-model.number="form.series_order"
                                @input="emit('markDirty')"
                                class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        <!-- Inline Create New Series Form Modal/Block -->
                        <div v-if="showCreateSeriesModal" class="mt-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-indigo-200 dark:border-indigo-900 shadow-sm space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-900 dark:text-white">Create New Series</span>
                                <button type="button" @click="showCreateSeriesModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                            </div>

                            <input
                                type="text"
                                v-model="newSeriesForm.title"
                                placeholder="Series title (e.g. Masterclass in Vue 3)..."
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
                            />

                            <textarea
                                v-model="newSeriesForm.description"
                                rows="2"
                                placeholder="Description (optional)..."
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
                            ></textarea>

                            <div class="flex gap-2">
                                <select
                                    v-model="newSeriesForm.status"
                                    class="w-1/2 text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
                                >
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="hiatus">Hiatus</option>
                                </select>

                                <button
                                    type="button"
                                    @click="submitCreateSeries"
                                    :disabled="isCreatingSeries"
                                    class="w-1/2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold py-1.5 transition disabled:opacity-50"
                                >
                                    {{ isCreatingSeries ? 'Creating...' : 'Create & Select' }}
                                </button>
                            </div>

                            <p v-if="createSeriesError" class="text-red-500 text-[11px]">{{ createSeriesError }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tags Input with Autocomplete & Create-New (Up to 5) -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Tags
                        </label>
                        <span class="text-[11px] font-semibold text-gray-400">
                            {{ form.tags ? form.tags.length : 0 }}/5 tags
                        </span>
                    </div>

                    <!-- Selected Tags Badges -->
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="(tag, idx) in form.tags"
                            :key="idx"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800"
                        >
                            #{{ typeof tag === 'object' ? tag.name : tag }}
                            <button
                                type="button"
                                @click="removeTag(idx)"
                                class="text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-200"
                            >
                                &times;
                            </button>
                        </span>
                    </div>

                    <!-- Tag Input -->
                    <div class="relative mt-2" v-if="!form.tags || form.tags.length < 5">
                        <input
                            type="text"
                            v-model="tagInput"
                            @keydown.enter.prevent="addTag()"
                            @keydown.,.prevent="addTag()"
                            @focus="isTagDropdownOpen = true"
                            placeholder="Type a tag & press Enter (max 5)..."
                            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                        />

                        <!-- Autocomplete Dropdown -->
                        <div
                            v-if="isTagDropdownOpen && filteredTags.length > 0"
                            class="absolute top-full left-0 mt-1 max-h-40 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white dark:bg-gray-800 shadow-lg z-20 py-1"
                        >
                            <button
                                v-for="tag in filteredTags"
                                :key="tag.id"
                                type="button"
                                @click="addTag(tag.name)"
                                class="w-full px-3 py-1.5 text-left text-xs text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 flex items-center justify-between"
                            >
                                <span>#{{ tag.name }}</span>
                                <span class="text-[10px] text-gray-400">select</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Custom Slug -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Custom Slug
                        </label>
                        <button
                            type="button"
                            @click="regenerateSlug"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 underline"
                        >
                            Reset from title
                        </button>
                    </div>

                    <input
                        type="text"
                        v-model="form.slug"
                        @input="onSlugInput"
                        placeholder="story-url-slug"
                        class="mt-2 w-full text-xs font-mono rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p class="mt-1 text-[11px] text-gray-400 truncate">
                        Preview: /posts/<span class="text-indigo-600 dark:text-indigo-400 font-mono">{{ form.slug || 'story-slug' }}</span>
                    </p>
                </div>

                <!-- SEO Title & Description -->
                <div class="border-t border-gray-100 dark:border-gray-800 pt-5 space-y-4">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Search Engine Optimization (SEO)
                    </label>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">
                            Meta Title
                        </label>
                        <input
                            type="text"
                            v-model="form.seo_title"
                            @input="emit('markDirty')"
                            :placeholder="form.title || 'Story Title'"
                            class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-300">
                            Meta Description
                        </label>
                        <textarea
                            rows="2"
                            v-model="form.seo_description"
                            @input="emit('markDirty')"
                            :placeholder="form.excerpt || 'Brief description for search engines...'"
                            class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <!-- Search Engine Result Snippet Preview -->
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3 bg-gray-50 dark:bg-gray-800/60">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Google Search Preview</span>
                        <div class="mt-1 text-xs">
                            <p class="text-indigo-700 dark:text-indigo-400 font-medium truncate">{{ previewTitle }}</p>
                            <p class="text-[11px] text-emerald-700 dark:text-emerald-500 truncate">https://inkwell.app/posts/{{ form.slug || 'story' }}</p>
                            <p class="text-[11px] text-gray-600 dark:text-gray-300 line-clamp-2 mt-0.5">{{ previewDescription }}</p>
                        </div>
                    </div>
                </div>

                <!-- Post Actions (When editing) -->
                <div v-if="isEdit" class="border-t border-gray-100 dark:border-gray-800 pt-5 space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                        Story Management
                    </label>

                    <button
                        v-if="form.status === 'published'"
                        type="button"
                        @click="emit('unpublish')"
                        class="w-full rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300 transition"
                    >
                        Unpublish Story (Revert to Draft)
                    </button>

                    <button
                        v-if="form.status !== 'archived'"
                        type="button"
                        @click="emit('archive')"
                        class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 transition"
                    >
                        Archive Story
                    </button>

                    <button
                        type="button"
                        @click="emit('delete')"
                        class="w-full rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300 transition"
                    >
                        Delete Story
                    </button>
                </div>
            </div>
        </aside>
    </div>
</template>
