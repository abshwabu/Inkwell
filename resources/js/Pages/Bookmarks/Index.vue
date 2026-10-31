<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    posts: {
        type: Object,
        required: true,
    },
    continueReading: {
        type: Array,
        default: () => [],
    },
});

const removingId = ref(null);

const removeBookmark = async (post) => {
    removingId.value = post.id;
    try {
        await axios.post(route('posts.bookmark', post.slug));
        router.reload({ only: ['posts'] });
    } catch (e) {
        // Fallback with full Inertia visit if axios fails
        router.post(route('posts.bookmark', post.slug), {}, { preserveScroll: true });
    } finally {
        removingId.value = null;
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Reading List" />

    <AppLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 border-b border-gray-200 pb-6 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Reading List
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ posts.total || 0 }} {{ (posts.total === 1) ? 'story' : 'stories' }} saved for later
                        </p>
                    </div>
                    <Link
                        :href="route('home')"
                        class="text-xs font-semibold uppercase tracking-wider text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                    >
                        Explore Stories &rarr;
                    </Link>
                </div>
            </div>

            <!-- Continue Reading Row -->
            <div v-if="continueReading && continueReading.length > 0" class="mb-10 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-5 dark:border-indigo-950/60 dark:bg-indigo-950/20">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Continue Series Reading
                    </h3>
                    <Link :href="route('series.index')" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        Explore series →
                    </Link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div
                        v-for="item in continueReading"
                        :key="item.series_id"
                        class="rounded-xl border border-white/60 bg-white p-3.5 shadow-xs dark:border-gray-800 dark:bg-gray-900 flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <Link :href="route('series.show', item.series_slug)" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline truncate">
                                    {{ item.series_title }}
                                </Link>
                                <span class="text-[10px] text-gray-400 shrink-0 font-medium">
                                    {{ item.read_parts }}/{{ item.total_parts }} parts
                                </span>
                            </div>

                            <h4 class="mt-1 text-xs font-bold text-gray-900 dark:text-white line-clamp-1">
                                <Link :href="route('posts.show', item.next_part.slug)" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Part {{ item.next_part.display_number }}: {{ item.next_part.title }}
                                </Link>
                            </h4>
                        </div>

                        <div class="mt-3">
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-indigo-600 h-1.5 rounded-full" :style="{ width: `${item.progress_percent}%` }"></div>
                            </div>
                            <div class="mt-2 flex justify-between items-center">
                                <span class="text-[10px] text-gray-400">
                                    {{ item.has_unread_next ? 'Up next' : 'Completed' }}
                                </span>
                                <Link
                                    :href="route('posts.show', item.next_part.slug)"
                                    class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    {{ item.has_unread_next ? 'Resume →' : 'Revisit →' }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Posts List -->
            <div v-if="posts.data && posts.data.length > 0" class="space-y-6">
                <article
                    v-for="post in posts.data"
                    :key="post.id"
                    class="group relative rounded-2xl border border-gray-200 bg-white p-6 shadow-xs transition hover:border-gray-300 hover:shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700"
                >
                    <div class="flex flex-col-reverse gap-6 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex-1 min-w-0">
                            <!-- Author & Meta -->
                            <div class="flex items-center gap-2.5 text-xs text-gray-500 dark:text-gray-400">
                                <Link
                                    :href="route('author.show', post.user.username)"
                                    class="flex items-center gap-1.5 font-medium text-gray-900 hover:underline dark:text-white"
                                >
                                    <img
                                        :src="post.user.avatar_url"
                                        :alt="post.user.name"
                                        class="h-5 w-5 rounded-full object-cover"
                                    />
                                    <span>{{ post.user.name }}</span>
                                </Link>
                                <span>•</span>
                                <span>{{ formatDate(post.published_at) }}</span>
                                <span>•</span>
                                <span>{{ post.reading_time_minutes || 1 }} min read</span>
                                <span v-if="post.category" class="rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    {{ post.category.name }}
                                </span>
                                <Link
                                    v-if="post.series"
                                    :href="route('series.show', post.series.slug)"
                                    class="rounded-full bg-indigo-50 px-2 py-0.5 font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 transition"
                                >
                                    Series · Part {{ post.series_order || 1 }}
                                </Link>
                            </div>

                            <!-- Title -->
                            <h2 class="mt-2.5 font-serif text-xl font-bold tracking-tight text-gray-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                                <Link :href="route('posts.show.canonical', { category_slug: post.category ? post.category.slug : 'story', post_slug: post.slug })">
                                    {{ post.title }}
                                </Link>
                            </h2>

                            <!-- Excerpt -->
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                                {{ post.excerpt }}
                            </p>

                            <!-- Actions -->
                            <div class="mt-4 flex items-center justify-between pt-2">
                                <div class="flex items-center gap-4 text-xs text-gray-400">
                                    <span>{{ post.likes_count || 0 }} likes</span>
                                    <span>•</span>
                                    <span>{{ post.comments_count || 0 }} comments</span>
                                </div>

                                <button
                                    type="button"
                                    @click="removeBookmark(post)"
                                    :disabled="removingId === post.id"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40 transition disabled:opacity-50"
                                    title="Remove from bookmarks"
                                >
                                    <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24">
                                        <path d="M5 4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v18l-7-4-7 4V4z"/>
                                    </svg>
                                    <span>Remove</span>
                                </button>
                            </div>
                        </div>

                        <!-- Cover Thumbnail -->
                        <div v-if="post.cover_image_path" class="w-full sm:w-40 sm:h-28 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                            <img
                                :src="post.cover_image_path"
                                :alt="post.title"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                        </div>
                    </div>
                </article>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-2xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800 bg-white/50 dark:bg-gray-900/50"
            >
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400 mb-4">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Your reading list is empty</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    Click the bookmark icon on any story to save it here for convenient reading later.
                </p>
                <div class="mt-6">
                    <Link
                        :href="route('home')"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400 transition"
                    >
                        Browse Stories
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="posts.links && posts.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link
                    v-for="(link, i) in posts.links"
                    :key="i"
                    :href="link.url || '#'"
                    :class="[
                        link.active
                            ? 'bg-indigo-600 text-white dark:bg-indigo-500'
                            : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
                        'px-3.5 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 font-medium'
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
