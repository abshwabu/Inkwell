<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichEditor from '@/Components/Editor/RichEditor.vue';

const props = defineProps({
    post: {
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
});

const form = useForm({
    title: props.post.title,
    category_id: props.post.category_id || '',
    excerpt: props.post.excerpt || '',
    cover_image_path: props.post.cover_image_path || '',
    content_html: props.post.content_html || '',
    content_json: props.post.content_json || null,
    tags: props.post.tags ? props.post.tags.map((t) => t.id) : [],
    status: props.post.status,
});

const isDirty = ref(false);
const isAutosaving = ref(false);
const isSubmitting = ref(false);
const lastSavedText = ref('');
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

const performAutosave = async () => {
    if (!isDirty.value || isAutosaving.value || isSubmitting.value) return;

    isAutosaving.value = true;

    try {
        const response = await axios.post(route('posts.autosave'), {
            id: props.post.id,
            title: form.title,
            category_id: form.category_id || null,
            excerpt: form.excerpt,
            cover_image_path: form.cover_image_path,
            content_html: form.content_html,
            content_json: form.content_json,
            tags: form.tags,
        });

        const date = new Date(response.data.saved_at || Date.now());
        lastSavedText.value = `Draft saved at ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
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

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);

    removeInertiaListener = router.on('before', (event) => {
        if (isDirty.value && !isSubmitting.value) {
            if (!confirm('You have unsaved changes in your story. Are you sure you want to leave?')) {
                event.preventDefault();
            }
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

const submit = (status = null) => {
    isSubmitting.value = true;
    isDirty.value = false;
    if (status) {
        form.status = status;
    }
    form.put(route('posts.update', props.post.slug), {
        onError: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Edit: ${post.title} — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Edit Story
                    </h1>
                    <div class="mt-1 flex items-center gap-2 text-xs">
                        <span v-if="isAutosaving" class="inline-flex items-center gap-1.5 text-indigo-600 dark:text-indigo-400 font-medium">
                            <span class="inline-block h-2 w-2 rounded-full bg-indigo-600 animate-ping"></span>
                            Saving draft...
                        </span>
                        <span v-else-if="lastSavedText" class="text-gray-500 dark:text-gray-400">
                            {{ lastSavedText }}
                        </span>
                        <span v-else-if="isDirty" class="text-amber-600 dark:text-amber-400">
                            Unsaved changes (autosaves every 20s)
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="submit('draft')"
                        :disabled="form.processing || isSubmitting"
                        class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 disabled:opacity-50 transition"
                    >
                        Save Draft
                    </button>
                    <PrimaryButton
                        type="button"
                        @click="submit('published')"
                        :disabled="form.processing || isSubmitting"
                    >
                        Publish Changes
                    </PrimaryButton>
                </div>
            </div>

            <form @submit.prevent="submit()" class="mt-8 space-y-6">
                <!-- Title -->
                <div>
                    <InputLabel for="title" value="Story Title" />
                    <TextInput
                        id="title"
                        type="text"
                        class="mt-1 block w-full text-xl font-serif"
                        v-model="form.title"
                        @input="markDirty"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.title" />
                </div>

                <!-- Category and Cover -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel for="category" value="Category" />
                        <select
                            id="category"
                            v-model="form.category_id"
                            @change="markDirty"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 text-sm"
                        >
                            <option value="">Select a category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.category_id" />
                    </div>

                    <div>
                        <InputLabel for="cover_image_path" value="Cover Image URL (Optional)" />
                        <TextInput
                            id="cover_image_path"
                            type="url"
                            class="mt-1 block w-full"
                            v-model="form.cover_image_path"
                            @input="markDirty"
                        />
                        <InputError class="mt-2" :message="form.errors.cover_image_path" />
                    </div>
                </div>

                <!-- Excerpt -->
                <div>
                    <InputLabel for="excerpt" value="Short Summary / Excerpt" />
                    <textarea
                        id="excerpt"
                        rows="2"
                        v-model="form.excerpt"
                        @input="markDirty"
                        placeholder="A brief teaser to catch readers' interest in feeds..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 text-sm"
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.excerpt" />
                </div>

                <!-- Rich Text Editor (TipTap) -->
                <div>
                    <InputLabel value="Story Content" class="mb-1" />
                    <RichEditor
                        :html="form.content_html"
                        :json="form.content_json"
                        @change="onEditorChange"
                        placeholder="Write your story... Format with toolbar or type / for quick blocks"
                    />
                    <InputError class="mt-2" :message="form.errors.content_html" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
