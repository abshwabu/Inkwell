<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    query: {
        type: String,
        default: '',
    },
    category: {
        type: String,
        default: '',
    },
    tag: {
        type: String,
        default: '',
    },
    posts: {
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

const searchTerm = ref(props.query);

const handleSearch = () => {
    router.get(route('search'), {
        q: searchTerm.value,
        category: props.category,
        tag: props.tag,
    }, { preserveState: true });
};
</script>

<template>
    <Head :title="`Search Stories — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                Explore & Search
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Discover essays, authors, and insights across all published topics.
            </p>

            <!-- Search Bar -->
            <form @submit.prevent="handleSearch" class="mt-6">
                <div class="relative">
                    <input
                        v-model="searchTerm"
                        type="search"
                        placeholder="Search by keywords, author names, or concepts..."
                        class="w-full rounded-2xl border-gray-300 py-3.5 pl-5 pr-28 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                    <button
                        type="submit"
                        class="absolute right-2 top-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 dark:bg-indigo-500"
                    >
                        Search
                    </button>
                </div>
            </form>

            <!-- Results Summary -->
            <div class="mt-8 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800 text-sm text-gray-500">
                <p>
                    Found <span class="font-bold text-gray-900 dark:text-white">{{ posts.total }}</span> results
                    <span v-if="query"> for "{{ query }}"</span>
                </p>
            </div>

            <!-- Posts List -->
            <div class="mt-6 space-y-6">
                <article
                    v-for="post in posts.data"
                    :key="post.id"
                    class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm hover:border-indigo-300 transition dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex flex-col sm:flex-row gap-6 justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <Link :href="route('author.show', post.user.username)" class="font-semibold text-gray-800 dark:text-gray-200 hover:text-indigo-600">
                                    {{ post.user.name }}
                                </Link>
                                <span>•</span>
                                <span>{{ post.reading_time_minutes }} min read</span>
                                <span>•</span>
                                <span>{{ new Date(post.published_at).toLocaleDateString() }}</span>
                            </div>

                            <h3 class="mt-2 font-serif text-xl font-bold tracking-tight text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400 transition">
                                <Link :href="route('posts.show', post.slug)">
                                    {{ post.title }}
                                </Link>
                            </h3>

                            <p class="mt-2 text-sm text-gray-600 line-clamp-2 dark:text-gray-300 leading-relaxed">
                                {{ post.excerpt }}
                            </p>
                        </div>

                        <div v-if="post.cover_image_path" class="w-full sm:w-44 h-32 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                            <img :src="post.cover_image_path" :alt="post.title" class="h-full w-full object-cover" />
                        </div>
                    </div>
                </article>

                <div v-if="posts.data.length === 0" class="rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800">
                    <p class="text-sm text-gray-500">No stories match your search query.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="posts.links && posts.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link
                    v-for="(link, i) in posts.links"
                    :key="i"
                    :href="link.url || '#'"
                    :class="[
                        link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                        'px-3.5 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 font-medium'
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
