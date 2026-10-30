<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tag: {
        type: Object,
        required: true,
    },
    posts: {
        type: Object,
        required: true,
    },
});

const allPosts = ref([...props.posts.data]);
const nextPageUrl = ref(props.posts.next_page_url);
const isLoadingMore = ref(false);

watch(
    () => props.posts,
    (newPosts) => {
        allPosts.value = [...newPosts.data];
        nextPageUrl.value = newPosts.next_page_url;
    }
);

const loadMore = () => {
    if (!nextPageUrl.value || isLoadingMore.value) return;

    isLoadingMore.value = true;
    router.get(
        nextPageUrl.value,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['posts'],
            onSuccess: (page) => {
                const incoming = page.props.posts;
                allPosts.value.push(...incoming.data);
                nextPageUrl.value = incoming.next_page_url;
                isLoadingMore.value = false;
            },
            onError: () => {
                isLoadingMore.value = false;
            },
        }
    );
};
</script>

<template>
    <Head :title="`#${tag.name} Stories — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="border-b border-gray-200 pb-8 dark:border-gray-800">
                <span class="rounded-lg bg-indigo-50 px-3 py-1 font-mono text-sm font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                    Topic Tag
                </span>
                <h1 class="mt-3 font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                    #{{ tag.name }}
                </h1>
            </div>

            <div class="mt-8 space-y-6">
                <article
                    v-for="post in allPosts"
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
                                <template v-if="post.category">
                                    <span>•</span>
                                    <Link :href="route('categories.show', post.category.slug)" class="text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ post.category.name }}
                                    </Link>
                                </template>
                            </div>

                            <h3 class="mt-2 font-serif text-xl font-bold tracking-tight text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400 transition">
                                <Link :href="route('posts.show.canonical', { category_slug: post.category ? post.category.slug : 'story', post_slug: post.slug })">
                                    {{ post.title }}
                                </Link>
                            </h3>

                            <p class="mt-2 text-sm text-gray-600 line-clamp-2 dark:text-gray-300 leading-relaxed">
                                {{ post.excerpt }}
                            </p>

                            <div class="mt-4 flex items-center gap-4 text-xs text-gray-400">
                                <span>{{ post.views_count }} views</span>
                                <span>•</span>
                                <span>{{ post.likes_count }} likes</span>
                                <span>•</span>
                                <span>{{ post.comments_count }} comments</span>
                            </div>
                        </div>

                        <div v-if="post.cover_image_path" class="w-full sm:w-44 h-32 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                            <img :src="post.cover_image_path" :alt="post.title" class="h-full w-full object-cover" />
                        </div>
                    </div>
                </article>

                <div v-if="allPosts.length === 0" class="rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800">
                    <p class="text-sm text-gray-500">No published stories with this tag yet.</p>
                </div>
            </div>

            <!-- Load More Button -->
            <div v-if="nextPageUrl" class="mt-10 flex justify-center">
                <button
                    type="button"
                    @click="loadMore"
                    :disabled="isLoadingMore"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-6 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-750 transition"
                >
                    <svg
                        v-if="isLoadingMore"
                        class="h-4 w-4 animate-spin text-indigo-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>{{ isLoadingMore ? 'Loading more stories...' : 'Load more stories' }}</span>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
