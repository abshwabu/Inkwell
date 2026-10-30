<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
    posts: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`${category.name} Stories — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <!-- Category Header -->
            <div class="border-b border-gray-200 pb-8 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <span
                        class="h-4 w-4 rounded-full"
                        :style="{ backgroundColor: category.color || '#4F46E5' }"
                    ></span>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                        {{ category.name }}
                    </h1>
                </div>
                <p v-if="category.description" class="mt-3 text-base text-gray-600 dark:text-gray-300 max-w-2xl">
                    {{ category.description }}
                </p>
            </div>

            <!-- Posts List -->
            <div class="mt-8 space-y-6">
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

                <div v-if="posts.data.length === 0" class="rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800">
                    <p class="text-sm text-gray-500">No published stories in this category yet.</p>
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
