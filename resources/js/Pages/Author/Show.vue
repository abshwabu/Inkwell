<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    author: {
        type: Object,
        required: true,
    },
    posts: {
        type: Object,
        required: true,
    },
    isFollowing: {
        type: Boolean,
        default: false,
    },
    isSelf: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const followForm = useForm({});

const toggleFollow = () => {
    followForm.post(route('users.follow', props.author.username), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${author.name} (@${author.username}) — Inkwell`" />

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <!-- Author Header Profile Card -->
            <div class="rounded-2xl border border-gray-200/80 bg-white p-6 sm:p-8 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <img
                            :src="author.avatar_url"
                            :alt="author.name"
                            class="h-24 w-24 rounded-full object-cover border-2 border-indigo-200 dark:border-indigo-800 shadow-sm"
                        />
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                                    {{ author.name }}
                                </h1>
                                <span
                                    v-if="author.role === 'admin'"
                                    class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                >
                                    Admin
                                </span>
                                <span
                                    v-else-if="author.role === 'moderator'"
                                    class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                >
                                    Moderator
                                </span>
                            </div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                @{{ author.username }}
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                Member since {{ author.created_at }}
                            </p>
                        </div>
                    </div>

                    <!-- Follow / Profile Action Button -->
                    <div>
                        <Link
                            v-if="isSelf"
                            :href="route('profile.edit')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Edit Profile
                        </Link>
                        <template v-else>
                            <button
                                v-if="page.props.auth?.user"
                                type="button"
                                @click="toggleFollow"
                                :disabled="followForm.processing"
                                :class="[
                                    isFollowing
                                        ? 'border border-gray-300 bg-white text-gray-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-red-950 dark:hover:text-red-400'
                                        : 'bg-indigo-600 text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400',
                                    'inline-flex items-center gap-1.5 rounded-xl px-5 py-2 text-sm font-semibold shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600'
                                ]"
                            >
                                <span>{{ isFollowing ? 'Following' : 'Follow' }}</span>
                            </button>
                            <Link
                                v-else
                                :href="route('login')"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                            >
                                Follow
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- Bio -->
                <p v-if="author.bio" class="mt-6 text-sm text-gray-700 dark:text-gray-300 leading-relaxed max-w-2xl">
                    {{ author.bio }}
                </p>

                <!-- Stats Bar -->
                <div class="mt-6 flex items-center gap-6 border-t border-gray-100 pt-5 text-sm dark:border-gray-800">
                    <div>
                        <span class="font-bold text-gray-900 dark:text-white">{{ author.followers_count }}</span>
                        <span class="ml-1 text-gray-500 dark:text-gray-400">Followers</span>
                    </div>
                    <div>
                        <span class="font-bold text-gray-900 dark:text-white">{{ author.following_count }}</span>
                        <span class="ml-1 text-gray-500 dark:text-gray-400">Following</span>
                    </div>
                    <div>
                        <span class="font-bold text-gray-900 dark:text-white">{{ author.published_posts_count }}</span>
                        <span class="ml-1 text-gray-500 dark:text-gray-400">Stories Published</span>
                    </div>
                </div>
            </div>

            <!-- Published Posts Section -->
            <section class="mt-10">
                <div class="flex items-center justify-between pb-6">
                    <h2 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Published Stories
                    </h2>
                </div>

                <div v-if="posts.data.length > 0" class="space-y-6">
                    <article
                        v-for="post in posts.data"
                        :key="post.id"
                        class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm transition duration-200 hover:border-indigo-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-900"
                    >
                        <div class="flex flex-col sm:flex-row gap-6 justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                    <span
                                        v-if="post.category"
                                        class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                    >
                                        {{ post.category.name }}
                                    </span>
                                    <span>{{ post.reading_time_minutes }} min read</span>
                                    <span>•</span>
                                    <span>{{ new Date(post.published_at).toLocaleDateString() }}</span>
                                </div>

                                <h3 class="mt-3 font-serif text-xl font-bold tracking-tight text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                    <Link :href="route('posts.show', post.slug)">
                                        {{ post.title }}
                                    </Link>
                                </h3>

                                <p class="mt-2 text-sm text-gray-600 line-clamp-2 dark:text-gray-300 leading-relaxed">
                                    {{ post.excerpt }}
                                </p>

                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <span
                                        v-for="tag in post.tags"
                                        :key="tag.id"
                                        class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        #{{ tag.name }}
                                    </span>
                                </div>

                                <div class="mt-4 flex items-center gap-4 text-xs text-gray-400">
                                    <span>{{ post.views_count }} views</span>
                                    <span>•</span>
                                    <span>{{ post.likes_count }} likes</span>
                                    <span>•</span>
                                    <span>{{ post.comments_count }} comments</span>
                                </div>
                            </div>

                            <div v-if="post.cover_image_path" class="w-full sm:w-44 h-32 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                <img
                                    :src="post.cover_image_path"
                                    :alt="post.title"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-2xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ author.name }} has not published any stories yet.
                    </p>
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
            </section>
        </div>
    </AppLayout>
</template>
