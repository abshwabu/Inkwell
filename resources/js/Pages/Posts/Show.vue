<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PostContent from '@/Components/PostContent.vue';
import { ref } from 'vue';

const props = defineProps({
    post: {
        type: Object,
        required: true,
    },
    isLiked: {
        type: Boolean,
        default: false,
    },
    isBookmarked: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
    canPublish: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const likeForm = useForm({});
const bookmarkForm = useForm({});
const publishForm = useForm({});
const deleteForm = useForm({});

const commentForm = useForm({
    body: '',
    parent_id: null,
});

const replyToId = ref(null);

const submitComment = () => {
    commentForm.post(route('comments.store', props.post.slug), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            replyToId.value = null;
        },
    });
};

const toggleLike = () => {
    likeForm.post(route('posts.like', props.post.slug), {
        preserveScroll: true,
    });
};

const toggleBookmark = () => {
    bookmarkForm.post(route('posts.bookmark', props.post.slug), {
        preserveScroll: true,
    });
};

const togglePublish = () => {
    publishForm.post(route('posts.publish', props.post.slug), {
        preserveScroll: true,
    });
};

const deletePost = () => {
    if (confirm('Are you sure you want to delete this story?')) {
        deleteForm.delete(route('posts.destroy', props.post.slug));
    }
};
</script>

<template>
    <Head :title="`${post.title} — Inkwell`" />

    <AppLayout>
        <article class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Category and Reading Time -->
            <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <Link
                    v-if="post.category"
                    :href="route('categories.show', post.category.slug)"
                    class="rounded-full bg-indigo-50 px-3 py-1 font-semibold text-indigo-700 hover:bg-indigo-100 transition dark:bg-indigo-950/70 dark:text-indigo-300"
                >
                    {{ post.category.name }}
                </Link>
                <span>{{ post.reading_time_minutes }} min read</span>
                <span v-if="post.published_at">•</span>
                <span v-if="post.published_at">{{ new Date(post.published_at).toLocaleDateString() }}</span>
                <span
                    v-if="post.status !== 'published'"
                    class="ml-auto rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wider text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                >
                    {{ post.status }}
                </span>
            </div>

            <!-- Title -->
            <h1 class="mt-4 font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl sm:leading-tight">
                {{ post.title }}
            </h1>

            <p v-if="post.excerpt" class="mt-4 text-xl text-gray-600 dark:text-gray-300 leading-relaxed font-serif italic">
                {{ post.excerpt }}
            </p>

            <!-- Author Bio Bar -->
            <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-y border-gray-100 py-4 dark:border-gray-800">
                <Link :href="route('author.show', post.user.username)" class="flex items-center gap-3 group">
                    <img
                        :src="post.user.avatar_url"
                        :alt="post.user.name"
                        class="h-11 w-11 rounded-full object-cover border border-gray-200 dark:border-gray-700"
                    />
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400 transition text-sm">
                            {{ post.user.name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @{{ post.user.username }}
                        </p>
                    </div>
                </Link>

                <!-- Action Controls (Like, Bookmark, Edit, Publish) -->
                <div class="flex items-center gap-2">
                    <!-- Like Button -->
                    <button
                        type="button"
                        @click="page.props.auth?.user ? toggleLike() : $inertia.visit(route('login'))"
                        :class="[
                            isLiked
                                ? 'bg-red-50 text-red-600 border-red-200 dark:bg-red-950/50 dark:text-red-400 dark:border-red-900'
                                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                            'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-sm transition'
                        ]"
                    >
                        <svg class="h-4 w-4" :fill="isLiked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span>{{ post.likes_count }}</span>
                    </button>

                    <!-- Bookmark Button -->
                    <button
                        type="button"
                        @click="page.props.auth?.user ? toggleBookmark() : $inertia.visit(route('login'))"
                        :class="[
                            isBookmarked
                                ? 'bg-indigo-50 text-indigo-600 border-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-400 dark:border-indigo-900'
                                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                            'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-sm transition'
                        ]"
                        title="Bookmark story"
                    >
                        <svg class="h-4 w-4" :fill="isBookmarked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                        </svg>
                    </button>

                    <!-- Edit / Publish / Delete dropdown or actions -->
                    <template v-if="canEdit">
                        <Link
                            :href="route('posts.edit', post.slug)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            Edit
                        </Link>
                    </template>

                    <template v-if="canPublish">
                        <button
                            type="button"
                            @click="togglePublish"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            {{ post.status === 'published' ? 'Unpublish' : 'Publish' }}
                        </button>
                    </template>

                    <template v-if="canDelete">
                        <button
                            type="button"
                            @click="deletePost"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400"
                        >
                            Delete
                        </button>
                    </template>
                </div>
            </div>

            <!-- Cover Image -->
            <div v-if="post.cover_image_path" class="mt-8 overflow-hidden rounded-2xl shadow-sm">
                <img
                    :src="post.cover_image_path"
                    :alt="post.title"
                    class="w-full max-h-[480px] object-cover"
                />
            </div>

            <!-- Post Body -->
            <PostContent :html="post.content_html" class="mt-10" />

            <!-- Tags -->
            <div v-if="post.tags && post.tags.length > 0" class="mt-10 flex flex-wrap gap-2 border-t border-gray-100 pt-6 dark:border-gray-800">
                <Link
                    v-for="tag in post.tags"
                    :key="tag.id"
                    :href="route('tags.show', tag.slug)"
                    class="rounded-lg bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    #{{ tag.name }}
                </Link>
            </div>

            <!-- Comments Section -->
            <section class="mt-16 border-t border-gray-200 pt-10 dark:border-gray-800">
                <h3 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Discussion ({{ post.comments_count }})
                </h3>

                <!-- New Comment Box -->
                <div class="mt-6">
                    <template v-if="page.props.auth?.user">
                        <form @submit.prevent="submitComment" class="space-y-3">
                            <textarea
                                v-model="commentForm.body"
                                rows="3"
                                placeholder="What are your thoughts on this story?"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm"
                                required
                            ></textarea>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="commentForm.processing"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:bg-indigo-500"
                                >
                                    Publish Comment
                                </button>
                            </div>
                        </form>
                    </template>
                    <div v-else class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-center dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Join the discussion on Inkwell.
                        </p>
                        <Link
                            :href="route('login')"
                            class="mt-3 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500"
                        >
                            Sign in to Comment
                        </Link>
                    </div>
                </div>

                <!-- Existing Comments -->
                <div class="mt-10 space-y-6">
                    <div
                        v-for="comment in post.comments"
                        :key="comment.id"
                        class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/60"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                :src="comment.user.avatar_url"
                                :alt="comment.user.name"
                                class="h-8 w-8 rounded-full object-cover"
                            />
                            <div>
                                <Link :href="route('author.show', comment.user.username)" class="text-xs font-bold text-gray-900 hover:text-indigo-600 dark:text-white">
                                    {{ comment.user.name }}
                                </Link>
                                <p class="text-[10px] text-gray-400">
                                    {{ new Date(comment.created_at).toLocaleDateString() }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            {{ comment.body }}
                        </p>

                        <!-- Nested Replies -->
                        <div v-if="comment.replies && comment.replies.length > 0" class="mt-4 space-y-3 pl-6 border-l-2 border-indigo-100 dark:border-indigo-900">
                            <div v-for="reply in comment.replies" :key="reply.id" class="text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-gray-900 dark:text-white">{{ reply.user.name }}</span>
                                    <span class="text-gray-400">• {{ new Date(reply.created_at).toLocaleDateString() }}</span>
                                </div>
                                <p class="mt-1 text-gray-600 dark:text-gray-300">{{ reply.body }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </article>
    </AppLayout>
</template>
