<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

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
    tags: props.post.tags ? props.post.tags.map((t) => t.id) : [],
});

const submit = () => {
    form.put(route('posts.update', props.post.slug));
};
</script>

<template>
    <Head :title="`Edit: ${post.title} — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-800">
                <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Edit Story
                </h1>
                <PrimaryButton
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                >
                    Save Changes
                </PrimaryButton>
            </div>

            <form @submit.prevent="submit" class="mt-8 space-y-6">
                <!-- Title -->
                <div>
                    <InputLabel for="title" value="Story Title" />
                    <TextInput
                        id="title"
                        type="text"
                        class="mt-1 block w-full text-lg font-serif"
                        v-model="form.title"
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
                        class="mt-1 block w-full font-mono text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        required
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.content_html" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
