<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
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
    title: '',
    category_id: '',
    excerpt: '',
    cover_image_path: '',
    content_html: '',
    tags: [],
    status: 'draft',
});

const submit = (status) => {
    form.status = status;
    form.post(route('posts.store'));
};
</script>

<template>
    <Head title="Write a New Story — Inkwell" />

    <AppLayout>
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-800">
                <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Draft a Story
                </h1>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="submit('draft')"
                        :disabled="form.processing"
                        class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        Save Draft
                    </button>
                    <PrimaryButton
                        type="button"
                        @click="submit('published')"
                        :disabled="form.processing"
                    >
                        Publish Now
                    </PrimaryButton>
                </div>
            </div>

            <form @submit.prevent="submit('published')" class="mt-8 space-y-6">
                <!-- Title -->
                <div>
                    <InputLabel for="title" value="Story Title" />
                    <TextInput
                        id="title"
                        type="text"
                        class="mt-1 block w-full text-lg font-serif"
                        v-model="form.title"
                        placeholder="An engaging, thoughtful title..."
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
                            placeholder="https://images.unsplash.com/..."
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
                        placeholder="A brief teaser to catch readers' interest in feeds..."
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 text-sm"
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.excerpt" />
                </div>

                <!-- Content HTML / Body -->
                <div>
                    <InputLabel for="content_html" value="Story Content (HTML / Text)" />
                    <textarea
                        id="content_html"
                        rows="12"
                        v-model="form.content_html"
                        placeholder="<p>Write your thoughtful story here...</p>"
                        class="mt-1 block w-full font-mono text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        required
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.content_html" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
