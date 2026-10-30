<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    following: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({
            following_count: 0,
            followers_count: 0,
        }),
    },
});

const togglingUsername = ref(null);

const unfollow = async (author) => {
    togglingUsername.value = author.username;
    try {
        await axios.post(route('users.follow', author.username));
        router.reload({ only: ['following', 'stats'] });
    } catch (e) {
        router.post(route('users.follow', author.username), {}, { preserveScroll: true });
    } finally {
        togglingUsername.value = null;
    }
};
</script>

<template>
    <Head title="Following" />

    <AppLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 border-b border-gray-200 pb-6 dark:border-gray-800">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Following
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Writers and thinkers whose stories you follow in your personal feed.
                        </p>
                    </div>

                    <!-- Follow Stats -->
                    <div class="flex items-center gap-6 rounded-xl bg-white px-4 py-2.5 shadow-xs border border-gray-200 dark:bg-gray-900 dark:border-gray-800">
                        <div class="text-center">
                            <span class="block text-lg font-bold text-gray-900 dark:text-white">
                                {{ stats.following_count }}
                            </span>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-gray-400">
                                Following
                            </span>
                        </div>
                        <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>
                        <div class="text-center">
                            <span class="block text-lg font-bold text-gray-900 dark:text-white">
                                {{ stats.followers_count }}
                            </span>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-gray-400">
                                Followers
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Following List -->
            <div v-if="following.data && following.data.length > 0" class="space-y-4">
                <div
                    v-for="author in following.data"
                    :key="author.id"
                    class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition hover:border-gray-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700"
                >
                    <div class="flex items-center gap-4 min-w-0">
                        <Link :href="route('author.show', author.username)" class="shrink-0">
                            <img
                                :src="author.avatar_url"
                                :alt="author.name"
                                class="h-13 w-13 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-800"
                            />
                        </Link>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('author.show', author.username)"
                                    class="truncate font-semibold text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                >
                                    {{ author.name }}
                                </Link>
                                <span class="text-xs text-gray-400">@{{ author.username }}</span>
                            </div>
                            <p v-if="author.bio" class="mt-1 text-xs text-gray-500 line-clamp-1 dark:text-gray-400">
                                {{ author.bio }}
                            </p>
                            <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
                                <span>{{ author.followers_count || 0 }} followers</span>
                                <span>•</span>
                                <span>{{ author.posts_count || 0 }} published stories</span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="unfollow(author)"
                        :disabled="togglingUsername === author.username"
                        class="shrink-0 rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-rose-900 dark:hover:bg-rose-950 dark:hover:text-rose-400 disabled:opacity-50"
                    >
                        {{ togglingUsername === author.username ? 'Updating...' : 'Unfollow' }}
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-2xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800 bg-white/50 dark:bg-gray-900/50"
            >
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400 mb-4">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">You're not following any authors yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    Follow writers to see their latest articles appear in your "Following" feed.
                </p>
                <div class="mt-6">
                    <Link
                        :href="route('home')"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400 transition"
                    >
                        Discover Writers
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="following.links && following.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link
                    v-for="(link, i) in following.links"
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
