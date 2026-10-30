<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    notifications: {
        type: Object,
        required: true,
    },
    unreadCount: {
        type: Number,
        default: 0,
    },
});

const isMarkingAll = ref(false);
const markingId = ref(null);

const markAsRead = async (notification) => {
    if (notification.read_at) return;
    markingId.value = notification.id;
    try {
        await axios.post(route('notifications.read', notification.id));
        notification.read_at = new Date().toISOString();
        router.reload({ only: ['unreadCount'] });
    } catch (e) {
        router.post(route('notifications.read', notification.id), {}, { preserveScroll: true });
    } finally {
        markingId.value = null;
    }
};

const markAllAsRead = async () => {
    isMarkingAll.value = true;
    try {
        await axios.post(route('notifications.read-all'));
        router.reload();
    } catch (e) {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    } finally {
        isMarkingAll.value = false;
    }
};

const getTargetUrl = (notification) => {
    const data = notification.data || {};
    if (data.type === 'follower' && data.username) {
        return route('author.show', data.username);
    }
    if (data.post_slug) {
        const cat = data.category_slug || 'story';
        return route('posts.show.canonical', { category_slug: cat, post_slug: data.post_slug });
    }
    return '#';
};

const timeAgo = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffInSecs = Math.floor((now - date) / 1000);

    if (diffInSecs < 60) return 'just now';
    if (diffInSecs < 3600) return `${Math.floor(diffInSecs / 60)}m ago`;
    if (diffInSecs < 86400) return `${Math.floor(diffInSecs / 3600)}h ago`;
    if (diffInSecs < 604800) return `${Math.floor(diffInSecs / 86400)}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Notifications" />

    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Notifications
                    </h1>
                    <span
                        v-if="unreadCount > 0"
                        class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-400"
                    >
                        {{ unreadCount }} unread
                    </span>
                </div>

                <button
                    v-if="unreadCount > 0"
                    type="button"
                    @click="markAllAsRead"
                    :disabled="isMarkingAll"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/40 transition disabled:opacity-50"
                >
                    {{ isMarkingAll ? 'Marking...' : 'Mark all as read' }}
                </button>
            </div>

            <!-- Notifications List -->
            <div v-if="notifications.data && notifications.data.length > 0" class="space-y-3">
                <div
                    v-for="item in notifications.data"
                    :key="item.id"
                    class="group relative flex items-start gap-4 rounded-2xl border p-4 transition"
                    :class="[
                        !item.read_at
                            ? 'border-indigo-100 bg-indigo-50/30 dark:border-indigo-900/40 dark:bg-indigo-950/20'
                            : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900'
                    ]"
                >
                    <!-- Type Icon Indicator -->
                    <div class="relative shrink-0 mt-0.5">
                        <img
                            v-if="item.data?.user_avatar"
                            :src="item.data.user_avatar"
                            :alt="item.data?.user_name || 'User'"
                            class="h-10 w-10 rounded-full object-cover ring-2 ring-white dark:ring-gray-900"
                        />
                        <div
                            v-else
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                        </div>

                        <!-- Mini icon badge on avatar -->
                        <span
                            class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full text-[10px] text-white shadow-xs"
                            :class="{
                                'bg-rose-500': item.data?.type === 'like',
                                'bg-indigo-500': item.data?.type === 'comment',
                                'bg-purple-500': item.data?.type === 'comment_reply',
                                'bg-emerald-500': item.data?.type === 'follower',
                            }"
                        >
                            <!-- Heart for like -->
                            <svg v-if="item.data?.type === 'like'" class="h-3 w-3 fill-current" viewBox="0 0 24 24">
                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                            </svg>
                            <!-- Comment bubble -->
                            <svg v-else-if="item.data?.type === 'comment'" class="h-3 w-3 fill-current" viewBox="0 0 24 24">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                            </svg>
                            <!-- Reply arrow -->
                            <svg v-else-if="item.data?.type === 'comment_reply'" class="h-3 w-3 fill-none stroke-current" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 0 1 5 5v2m-15-7l4-4m-4 4l4 4"/>
                            </svg>
                            <!-- User plus -->
                            <svg v-else-if="item.data?.type === 'follower'" class="h-3 w-3 fill-current" viewBox="0 0 24 24">
                                <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <span v-else>•</span>
                        </span>
                    </div>

                    <!-- Notification Body -->
                    <div class="flex-1 min-w-0">
                        <Link
                            :href="getTargetUrl(item)"
                            @click="markAsRead(item)"
                            class="block"
                        >
                            <p class="text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">
                                {{ item.data?.message || item.data?.title || 'Notification' }}
                            </p>
                        </Link>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ timeAgo(item.created_at) }}
                        </p>
                    </div>

                    <!-- Unread dot or Mark read button -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            v-if="!item.read_at"
                            type="button"
                            @click="markAsRead(item)"
                            :disabled="markingId === item.id"
                            class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-gray-800 dark:hover:text-indigo-400 transition"
                            title="Mark as read"
                        >
                            <span class="block h-2.5 w-2.5 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-2xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800 bg-white/50 dark:bg-gray-900/50"
            >
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500 mb-4">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">All caught up!</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    You have no new notifications. When someone interacts with your stories, replies to your comments, or follows you, you'll see it here.
                </p>
            </div>

            <!-- Pagination -->
            <div v-if="notifications.links && notifications.links.length > 3" class="mt-8 flex justify-center gap-1">
                <Link
                    v-for="(link, i) in notifications.links"
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
