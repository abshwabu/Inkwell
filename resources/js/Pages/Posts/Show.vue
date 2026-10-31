<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PostContent from '@/Components/PostContent.vue';
import ReportModal from '@/Components/ReportModal.vue';
import axios from 'axios';

const props = defineProps({
    post: {
        type: Object,
        required: true,
    },
    toc: {
        type: Array,
        default: () => [],
    },
    relatedPosts: {
        type: Array,
        default: () => [],
    },
    previousPost: {
        type: Object,
        default: null,
    },
    nextPost: {
        type: Object,
        default: null,
    },
    authorStats: {
        type: Object,
        default: () => ({ followers_count: 0, is_following: false }),
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
    seriesContext: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

// Series Follow & State
const localIsFollowingSeries = ref(props.seriesContext?.is_following || false);
const localSeriesFollowersCount = ref(props.seriesContext?.followers_count || 0);
const isFollowingSeriesLoading = ref(false);

watch(() => props.seriesContext?.is_following, (val) => { localIsFollowingSeries.value = Boolean(val); });
watch(() => props.seriesContext?.followers_count, (val) => { localSeriesFollowersCount.value = val || 0; });

const toggleFollowSeries = async () => {
    if (!requireAuth()) return;
    if (isFollowingSeriesLoading.value || !props.seriesContext) return;

    isFollowingSeriesLoading.value = true;
    const prevFollowing = localIsFollowingSeries.value;
    const prevCount = localSeriesFollowersCount.value;

    localIsFollowingSeries.value = !prevFollowing;
    localSeriesFollowersCount.value += localIsFollowingSeries.value ? 1 : -1;

    try {
        const res = await axios.post(route('series.follow', props.seriesContext.slug));
        localIsFollowingSeries.value = res.data.is_following;
        localSeriesFollowersCount.value = res.data.followers_count;
    } catch (err) {
        localIsFollowingSeries.value = prevFollowing;
        localSeriesFollowersCount.value = prevCount;
    } finally {
        isFollowingSeriesLoading.value = false;
    }
};

// Start series from standalone post modal state
const showStartSeriesModal = ref(false);
const startSeriesTitle = ref(props.post.title || '');
const startSeriesDescription = ref('');
const startSeriesStatus = ref('ongoing');
const isStartingSeries = ref(false);
const startSeriesError = ref('');

const openStartSeriesModal = () => {
    startSeriesTitle.value = props.post.title || '';
    startSeriesDescription.value = '';
    startSeriesStatus.value = 'ongoing';
    startSeriesError.value = '';
    showStartSeriesModal.value = true;
};

const submitStartSeries = async () => {
    if (!startSeriesTitle.value.trim()) {
        startSeriesError.value = 'Series title is required.';
        return;
    }

    isStartingSeries.value = true;
    startSeriesError.value = '';

    try {
        const createRes = await axios.post(route('series.store'), {
            title: startSeriesTitle.value.trim(),
            description: startSeriesDescription.value.trim() || null,
            status: startSeriesStatus.value,
        }, {
            headers: { Accept: 'application/json' },
        });

        const newSeries = createRes.data.series;

        // Attach this post as Part 1
        await axios.put(route('posts.update', props.post.id), {
            title: props.post.title,
            content_html: props.post.content_html,
            status: props.post.status,
            series_id: newSeries.id,
            series_order: 1,
        });

        showStartSeriesModal.value = false;
        router.reload();
    } catch (err) {
        startSeriesError.value = err.response?.data?.message || 'Failed to start series.';
    } finally {
        isStartingSeries.value = false;
    }
};

// Optimistic UI state
const localIsLiked = ref(props.isLiked);
const localLikesCount = ref(props.post.likes_count || 0);
const localIsBookmarked = ref(props.isBookmarked);
const localIsFollowing = ref(props.authorStats.is_following);
const localFollowersCount = ref(props.authorStats.followers_count || 0);

watch(() => props.isLiked, (val) => { localIsLiked.value = val; });
watch(() => props.post.likes_count, (val) => { localLikesCount.value = val; });
watch(() => props.isBookmarked, (val) => { localIsBookmarked.value = val; });
watch(() => props.authorStats.is_following, (val) => { localIsFollowing.value = val; });
watch(() => props.authorStats.followers_count, (val) => { localFollowersCount.value = val; });

const isLiking = ref(false);
const isBookmarking = ref(false);
const isFollowing = ref(false);

const publishForm = useForm({});
const deleteForm = useForm({});

const commentForm = useForm({
    body: '',
    parent_id: null,
});

const replyToId = ref(null);
const replyingToAuthor = ref('');
const editingCommentId = ref(null);
const editBody = ref('');
const isSavingEdit = ref(false);

// Report Modal State
const reportModalOpen = ref(false);
const reportType = ref('post');
const reportId = ref(props.post.id);
const reportTitle = ref(props.post.title);

const openReportModal = (type, id, title) => {
    if (!requireAuth()) return;
    reportType.value = type;
    reportId.value = id;
    reportTitle.value = title || (type === 'post' ? props.post.title : 'Comment');
    reportModalOpen.value = true;
};

const readingProgress = ref(0);
const activeHeadingId = ref('');
const copySuccess = ref(false);

const canonicalCategorySlug = computed(() => props.post.category ? props.post.category.slug : 'story');
const currentUrl = computed(() => typeof window !== 'undefined' ? window.location.href : '');

// Scroll progress calculation
const handleScroll = () => {
    const total = document.documentElement.scrollHeight - window.innerHeight;
    readingProgress.value = total > 0 ? Math.min(100, Math.max(0, (window.scrollY / total) * 100)) : 0;

    // Scroll-spy active heading
    if (props.toc && props.toc.length > 0) {
        const headings = props.toc.map(item => document.getElementById(item.id)).filter(Boolean);
        const scrollPosition = window.scrollY + 120;
        for (let i = headings.length - 1; i >= 0; i--) {
            if (headings[i].offsetTop <= scrollPosition) {
                activeHeadingId.value = headings[i].id;
                return;
            }
        }
        activeHeadingId.value = headings[0]?.id || '';
    }
};

const scrollToHeading = (id) => {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
        activeHeadingId.value = id;
    }
};

const copyShareLink = async () => {
    try {
        await navigator.clipboard.writeText(window.location.href);
        copySuccess.value = true;
        setTimeout(() => {
            copySuccess.value = false;
        }, 3000);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = window.location.href;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copySuccess.value = true;
        setTimeout(() => {
            copySuccess.value = false;
        }, 3000);
    }
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const requireAuth = () => {
    if (!currentUser.value) {
        router.visit(route('login'));
        return false;
    }
    return true;
};

// Optimistic Like
const toggleLike = async () => {
    if (!requireAuth() || isLiking.value) return;

    const previousLiked = localIsLiked.value;
    const previousCount = localLikesCount.value;

    // Optimistic toggle
    localIsLiked.value = !previousLiked;
    localLikesCount.value += localIsLiked.value ? 1 : -1;
    if (localLikesCount.value < 0) localLikesCount.value = 0;

    isLiking.value = true;
    try {
        const res = await axios.post(route('posts.like', props.post.slug));
        if (res.data?.likes_count !== undefined) {
            localLikesCount.value = res.data.likes_count;
            localIsLiked.value = res.data.liked;
        }
    } catch (e) {
        // Rollback on failure
        localIsLiked.value = previousLiked;
        localLikesCount.value = previousCount;
    } finally {
        isLiking.value = false;
    }
};

// Optimistic Bookmark
const toggleBookmark = async () => {
    if (!requireAuth() || isBookmarking.value) return;

    const previousBookmarked = localIsBookmarked.value;
    localIsBookmarked.value = !previousBookmarked;

    isBookmarking.value = true;
    try {
        const res = await axios.post(route('posts.bookmark', props.post.slug));
        if (res.data?.bookmarked !== undefined) {
            localIsBookmarked.value = res.data.bookmarked;
        }
    } catch (e) {
        localIsBookmarked.value = previousBookmarked;
    } finally {
        isBookmarking.value = false;
    }
};

// Optimistic Follow
const toggleFollow = async () => {
    if (!requireAuth() || isFollowing.value) return;

    const previousFollowing = localIsFollowing.value;
    const previousCount = localFollowersCount.value;

    localIsFollowing.value = !previousFollowing;
    localFollowersCount.value += localIsFollowing.value ? 1 : -1;
    if (localFollowersCount.value < 0) localFollowersCount.value = 0;

    isFollowing.value = true;
    try {
        const res = await axios.post(route('users.follow', props.post.user.username));
        if (res.data?.is_following !== undefined) {
            localIsFollowing.value = res.data.is_following;
            localFollowersCount.value = res.data.followers_count;
        }
    } catch (e) {
        localIsFollowing.value = previousFollowing;
        localFollowersCount.value = previousCount;
    } finally {
        isFollowing.value = false;
    }
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

// Comment submission
const submitComment = () => {
    if (!requireAuth()) return;
    commentForm.post(route('comments.store', props.post.slug), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            replyToId.value = null;
            replyingToAuthor.value = '';
        },
    });
};

const setReplyTo = (commentId, authorName = '') => {
    if (!requireAuth()) return;
    replyToId.value = commentId;
    replyingToAuthor.value = authorName;
    commentForm.parent_id = commentId;
};

const cancelReply = () => {
    replyToId.value = null;
    replyingToAuthor.value = '';
    commentForm.parent_id = null;
};

// Inline comment editing
const startEdit = (comment) => {
    editingCommentId.value = comment.id;
    editBody.value = comment.body;
};

const cancelEdit = () => {
    editingCommentId.value = null;
    editBody.value = '';
};

const saveEdit = async (comment) => {
    if (!editBody.value.trim() || isSavingEdit.value) return;

    isSavingEdit.value = true;
    try {
        await axios.put(route('comments.update', comment.id), {
            body: editBody.value.trim(),
        });
        editingCommentId.value = null;
        editBody.value = '';
        router.reload({ only: ['post'] });
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to update comment. The 15-minute edit window may have expired.');
    } finally {
        isSavingEdit.value = false;
    }
};

// Delete comment
const deleteComment = (comment) => {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    router.delete(route('comments.destroy', comment.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head>
        <title>{{ post.seo_title || post.title }} — Inkwell</title>
        <meta name="description" :content="post.seo_description || post.excerpt || post.title" />
        <meta property="og:title" :content="post.title" />
        <meta property="og:description" :content="post.excerpt || post.title" />
        <meta property="og:type" content="article" />
        <meta v-if="post.cover_image_path" property="og:image" :content="post.cover_image_path" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="post.title" />
        <meta name="twitter:description" :content="post.excerpt || post.title" />
        <link rel="canonical" :href="route('posts.show.canonical', { category_slug: canonicalCategorySlug, post_slug: post.slug })" />
    </Head>

    <!-- Reading Progress Bar -->
    <div class="fixed top-0 left-0 right-0 h-1 bg-gray-200/50 dark:bg-gray-800/50 z-50 pointer-events-none">
        <div
            class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600 transition-[width] duration-75"
            :style="{ width: `${readingProgress}%` }"
        ></div>
    </div>

    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-12 lg:gap-12">
                <!-- Main Reading Column -->
                <main class="lg:col-span-8 lg:col-start-1">
                    <article>
                        <!-- Top Metadata / Category / Reading Time -->
                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <Link
                                v-if="post.category"
                                :href="route('categories.show', post.category.slug)"
                                class="rounded-full bg-indigo-50 px-3 py-1 font-semibold text-indigo-700 hover:bg-indigo-100 transition dark:bg-indigo-950/70 dark:text-indigo-300"
                            >
                                {{ post.category.name }}
                            </Link>
                            <span>{{ post.reading_time_minutes }} min read</span>
                            <span v-if="post.published_at">•</span>
                            <span v-if="post.published_at">{{ new Date(post.published_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) }}</span>
                            <span v-if="post.is_updated" class="text-xs italic text-gray-400 dark:text-gray-500">
                                (Updated {{ new Date(post.updated_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) }})
                            </span>
                            <span
                                v-if="post.status !== 'published'"
                                class="ml-auto rounded bg-amber-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                            >
                                {{ post.status }}
                            </span>
                        </div>

                        <!-- Story Title -->
                        <h1 class="mt-4 font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl sm:leading-tight">
                            {{ post.title }}
                        </h1>

                        <!-- Excerpt / Subheading -->
                        <p v-if="post.excerpt" class="mt-4 text-xl text-gray-600 dark:text-gray-300 leading-relaxed font-serif italic">
                            {{ post.excerpt }}
                        </p>

                        <!-- Author Header Row -->
                        <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-y border-gray-100 py-4 dark:border-gray-800">
                            <div class="flex items-center gap-3">
                                <Link :href="route('author.show', post.user.username)">
                                    <img
                                        :src="post.user.avatar_url"
                                        :alt="post.user.name"
                                        loading="lazy"
                                        decoding="async"
                                        width="44"
                                        height="44"
                                        class="h-11 w-11 rounded-full object-cover border border-gray-200 dark:border-gray-700"
                                    />
                                </Link>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="route('author.show', post.user.username)"
                                            class="font-semibold text-gray-900 hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400 transition text-sm"
                                        >
                                            {{ post.user.name }}
                                        </Link>
                                        <span class="text-xs text-gray-400">•</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ authorStats.followers_count }} followers</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @{{ post.user.username }}
                                    </p>
                                </div>

                                <!-- Follow Button for Author -->
                                <button
                                    v-if="!currentUser || currentUser.id !== post.user.id"
                                    type="button"
                                    @click="toggleFollow"
                                    :disabled="isFollowing"
                                    :class="[
                                        localIsFollowing
                                            ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200'
                                            : 'bg-indigo-600 text-white hover:bg-indigo-500 shadow-sm',
                                        'ml-2 rounded-full px-3 py-1 text-xs font-semibold transition disabled:opacity-50'
                                    ]"
                                >
                                    {{ localIsFollowing ? 'Following' : 'Follow' }}
                                </button>
                            </div>

                            <!-- Interaction Actions (Like, Bookmark, Edit, Report) -->
                            <div class="flex items-center gap-2">
                                <!-- Like Button -->
                                <button
                                    type="button"
                                    @click="toggleLike"
                                    :disabled="isLiking"
                                    :class="[
                                        localIsLiked
                                            ? 'bg-red-50 text-red-600 border-red-200 dark:bg-red-950/50 dark:text-red-400 dark:border-red-900'
                                            : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-sm transition disabled:opacity-50'
                                    ]"
                                    title="Like this story"
                                >
                                    <svg class="h-4 w-4" :fill="localIsLiked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    <span>{{ localLikesCount }}</span>
                                </button>

                                <!-- Bookmark Button -->
                                <button
                                    type="button"
                                    @click="toggleBookmark"
                                    :disabled="isBookmarking"
                                    :class="[
                                        localIsBookmarked
                                            ? 'bg-indigo-50 text-indigo-600 border-indigo-200 dark:bg-indigo-950/50 dark:text-indigo-400 dark:border-indigo-900'
                                            : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-sm transition disabled:opacity-50'
                                    ]"
                                    title="Bookmark story"
                                >
                                    <svg class="h-4 w-4" :fill="localIsBookmarked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                    </svg>
                                </button>

                                <!-- Report Story Button -->
                                <button
                                    type="button"
                                    @click="openReportModal('post', post.id, post.title)"
                                    class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-1.5 text-xs text-gray-500 shadow-sm hover:bg-gray-50 hover:text-rose-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-rose-400 transition"
                                    title="Report this story"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                    </svg>
                                </button>

                                <!-- Edit Controls -->
                                <template v-if="canEdit">
                                    <Link
                                        :href="route('posts.edit', post.id)"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        v-if="!post.series_id"
                                        type="button"
                                        @click="openStartSeriesModal"
                                        class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-300 transition"
                                    >
                                        Start series from post
                                    </button>
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
                                loading="lazy"
                                decoding="async"
                                width="1200"
                                height="630"
                                class="w-full max-h-[500px] object-cover"
                            />
                        </div>

                        <!-- Mobile Series Accordion Dropdown -->
                        <div v-if="seriesContext" class="lg:hidden mt-8 rounded-xl border border-indigo-100 bg-indigo-50/70 p-4 dark:border-indigo-950/60 dark:bg-indigo-950/30">
                            <details class="group">
                                <summary class="flex cursor-pointer items-center justify-between text-xs font-bold text-gray-900 dark:text-white">
                                    <span class="flex items-center gap-2 truncate pr-2">
                                        <span class="text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-wider text-[10px]">Series:</span>
                                        <span class="truncate">{{ seriesContext.title }}</span>
                                        <span v-if="seriesContext.current_part_number" class="text-gray-400 font-normal shrink-0">(Part {{ seriesContext.current_part_number }} of {{ seriesContext.total_published_parts }})</span>
                                    </span>
                                    <span class="transition group-open:rotate-180 text-gray-400">▾</span>
                                </summary>
                                <nav class="mt-3 space-y-1.5 border-t border-indigo-100 dark:border-indigo-900/60 pt-3 text-xs">
                                    <div
                                        v-for="part in seriesContext.parts"
                                        :key="part.id"
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <Link
                                            v-if="part.is_published || seriesContext.is_author"
                                            :href="route('posts.show', part.slug)"
                                            :class="[
                                                part.is_current
                                                    ? 'font-bold text-indigo-600 dark:text-indigo-400'
                                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
                                                'truncate block py-1'
                                            ]"
                                        >
                                            <span class="font-mono text-[11px] text-gray-400 mr-1">
                                                {{ part.display_number ? `${part.display_number}.` : '•' }}
                                            </span>
                                            {{ part.title }}
                                        </Link>
                                        <span v-if="!part.is_published" class="text-[10px] bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 rounded px-1.5 py-0.5 shrink-0">
                                            Draft
                                        </span>
                                    </div>
                                </nav>
                            </details>
                        </div>

                        <!-- Mobile Table of Contents Dropdown (When available) -->
                        <div v-if="toc && toc.length > 0" class="lg:hidden mt-8 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <details class="group">
                                <summary class="flex cursor-pointer items-center justify-between font-semibold text-sm text-gray-900 dark:text-white">
                                    <span>Table of Contents</span>
                                    <span class="transition group-open:rotate-180">▾</span>
                                </summary>
                                <nav class="mt-3 space-y-1.5 border-t border-gray-200 pt-3 dark:border-gray-800 text-xs">
                                    <a
                                        v-for="item in toc"
                                        :key="item.id"
                                        :href="`#${item.id}`"
                                        @click.prevent="scrollToHeading(item.id)"
                                        :class="[
                                            item.level === 3 ? 'pl-4' : 'font-medium',
                                            activeHeadingId === item.id ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-gray-600 dark:text-gray-400',
                                            'block py-1 hover:text-indigo-600'
                                        ]"
                                    >
                                        {{ item.text }}
                                    </a>
                                </nav>
                            </details>
                        </div>

                        <!-- Post Body Content -->
                        <PostContent :html="post.content_html" class="mt-10" />

                        <!-- Tags Chips -->
                        <div v-if="post.tags && post.tags.length > 0" class="mt-10 flex flex-wrap gap-2 border-t border-gray-100 pt-6 dark:border-gray-800">
                            <Link
                                v-for="tag in post.tags"
                                :key="tag.id"
                                :href="route('tags.show', tag.slug)"
                                class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                #{{ tag.name }}
                            </Link>
                        </div>

                        <!-- Social Share Bar -->
                        <div class="mt-8 rounded-2xl border border-gray-100 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Share this story:
                                </p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Copy Link Button -->
                                    <button
                                        type="button"
                                        @click="copyShareLink"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        title="Copy link to clipboard"
                                    >
                                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ copySuccess ? 'Copied!' : 'Copy Link' }}</span>
                                    </button>

                                    <!-- X / Twitter -->
                                    <a
                                        :href="`https://twitter.com/intent/tweet?text=${encodeURIComponent(post.title)}&url=${encodeURIComponent(currentUrl)}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        title="Share on X"
                                    >
                                        <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 24 24">
                                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                        </svg>
                                    </a>

                                    <!-- Facebook -->
                                    <a
                                        :href="`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(currentUrl)}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        title="Share on Facebook"
                                    >
                                        <svg class="h-3.5 w-3.5 fill-current text-blue-600" viewBox="0 0 24 24">
                                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                        </svg>
                                    </a>

                                    <!-- WhatsApp -->
                                    <a
                                        :href="`https://api.whatsapp.com/send?text=${encodeURIComponent(post.title + ' ' + currentUrl)}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        title="Share on WhatsApp"
                                    >
                                        <svg class="h-3.5 w-3.5 fill-current text-emerald-500" viewBox="0 0 24 24">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                                        </svg>
                                    </a>

                                    <!-- Telegram -->
                                    <a
                                        :href="`https://t.me/share/url?url=${encodeURIComponent(currentUrl)}&text=${encodeURIComponent(post.title)}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        title="Share on Telegram"
                                    >
                                        <svg class="h-3.5 w-3.5 fill-current text-sky-500" viewBox="0 0 24 24">
                                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                        </svg>
                                    </a>

                                    <!-- LinkedIn -->
                                    <a
                                        :href="`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(currentUrl)}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-gray-200 bg-white p-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        title="Share on LinkedIn"
                                    >
                                        <svg class="h-3.5 w-3.5 fill-current text-sky-700" viewBox="0 0 24 24">
                                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Author Card at bottom of story -->
                        <div class="mt-12 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-start gap-4">
                                <Link :href="route('author.show', post.user.username)">
                                    <img
                                        :src="post.user.avatar_url"
                                        :alt="post.user.name"
                                        class="h-16 w-16 rounded-full object-cover border border-gray-200 dark:border-gray-700"
                                    />
                                </Link>
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white">
                                                <Link :href="route('author.show', post.user.username)" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                                    {{ post.user.name }}
                                                </Link>
                                            </h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                @{{ post.user.username }} • {{ localFollowersCount }} followers
                                            </p>
                                        </div>

                                        <button
                                            v-if="!currentUser || currentUser.id !== post.user.id"
                                            type="button"
                                            @click="toggleFollow"
                                            :disabled="isFollowing"
                                            :class="[
                                                localIsFollowing
                                                    ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200'
                                                    : 'bg-indigo-600 text-white hover:bg-indigo-500 shadow-sm',
                                                'rounded-full px-4 py-1.5 text-xs font-semibold transition disabled:opacity-50'
                                            ]"
                                        >
                                            {{ localIsFollowing ? 'Following' : 'Follow' }}
                                        </button>
                                    </div>

                                    <p v-if="post.user.bio" class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                        {{ post.user.bio }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Series Navigation Block -->
                        <div v-if="seriesContext" class="mt-10 rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50/60 via-white to-indigo-50/30 dark:from-indigo-950/30 dark:via-gray-900 dark:to-indigo-950/20 p-6 shadow-sm dark:border-indigo-950/60">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-100 dark:border-indigo-900/60 pb-4">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Story Series</span>
                                    <h4 class="text-base font-bold text-gray-900 dark:text-white">
                                        <Link :href="route('series.show', seriesContext.slug)" class="hover:underline">
                                            {{ seriesContext.title }}
                                        </Link>
                                    </h4>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-indigo-100 dark:bg-indigo-900/60 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                                        {{ seriesContext.status_label }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="toggleFollowSeries"
                                        :disabled="isFollowingSeriesLoading"
                                        :class="[
                                            localIsFollowingSeries
                                                ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-500 shadow-sm',
                                            'rounded-full px-3 py-1 text-xs font-semibold transition disabled:opacity-50'
                                        ]"
                                    >
                                        {{ localIsFollowingSeries ? 'Following Series' : 'Follow Series' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Navigation Cards: Previous & Next -->
                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Previous Part Card -->
                                <div v-if="seriesContext.prev_part" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 dark:border-gray-800 dark:bg-gray-900 transition">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">← Previous Part</span>
                                    <h5 class="mt-1 text-sm font-bold text-gray-900 dark:text-white line-clamp-1">
                                        <Link :href="route('posts.show', seriesContext.prev_part.slug)" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                            Part {{ seriesContext.prev_part.display_number }}: {{ seriesContext.prev_part.title }}
                                        </Link>
                                    </h5>
                                </div>
                                <div v-else class="rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-4 flex items-center justify-center text-xs text-gray-400">
                                    First part of series
                                </div>

                                <!-- Next Part Card -->
                                <div v-if="seriesContext.next_part" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 dark:border-gray-800 dark:bg-gray-900 transition text-right">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Next Part →</span>
                                    <h5 class="mt-1 text-sm font-bold text-gray-900 dark:text-white line-clamp-1">
                                        <Link :href="route('posts.show', seriesContext.next_part.slug)" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                            Part {{ seriesContext.next_part.display_number }}: {{ seriesContext.next_part.title }}
                                        </Link>
                                    </h5>
                                </div>
                                <!-- If Last Published Part -->
                                <div v-else class="rounded-xl border border-dashed border-indigo-200 dark:border-indigo-900/60 p-4 flex flex-col items-center justify-center text-center">
                                    <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                        {{ seriesContext.status === 'completed' ? 'The End' : 'More coming soon' }}
                                    </span>
                                    <span class="text-[11px] text-gray-400 mt-0.5">
                                        {{ seriesContext.status === 'completed' ? 'This series is complete.' : 'Follow this series to get notified when the next part drops.' }}
                                    </span>
                                </div>
                            </div>

                            <!-- "Write the next part" button for series owner only -->
                            <div v-if="seriesContext.is_author" class="mt-4 pt-4 border-t border-indigo-100 dark:border-indigo-900/60 flex justify-end">
                                <Link
                                    :href="route('posts.create', { series: seriesContext.slug })"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 shadow-sm transition"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Write the next part
                                </Link>
                            </div>
                        </div>

                        <!-- Previous and Next Story Navigation (When not part of a series) -->
                        <div v-else-if="previousPost || nextPost" class="mt-10 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div
                                v-if="previousPost"
                                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-indigo-300 transition dark:border-gray-800 dark:bg-gray-900"
                            >
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">← Previous Story</span>
                                <h4 class="mt-1 font-serif text-base font-bold text-gray-900 dark:text-white line-clamp-2 hover:text-indigo-600 transition">
                                    <Link :href="route('posts.show.canonical', { category_slug: previousPost.category ? previousPost.category.slug : 'story', post_slug: previousPost.slug })">
                                        {{ previousPost.title }}
                                    </Link>
                                </h4>
                            </div>
                            <div v-else></div>

                            <div
                                v-if="nextPost"
                                class="rounded-xl border border-gray-200 bg-white p-5 text-right shadow-sm hover:border-indigo-300 transition dark:border-gray-800 dark:bg-gray-900"
                            >
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Next Story →</span>
                                <h4 class="mt-1 font-serif text-base font-bold text-gray-900 dark:text-white line-clamp-2 hover:text-indigo-600 transition">
                                    <Link :href="route('posts.show.canonical', { category_slug: nextPost.category ? nextPost.category.slug : 'story', post_slug: nextPost.slug })">
                                        {{ nextPost.title }}
                                    </Link>
                                </h4>
                            </div>
                        </div>

                        <!-- Related Stories -->
                        <section v-if="relatedPosts && relatedPosts.length > 0" class="mt-16 border-t border-gray-200 pt-10 dark:border-gray-800">
                            <h3 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                                Related Stories
                            </h3>
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                                <article
                                    v-for="related in relatedPosts"
                                    :key="related.id"
                                    class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 transition dark:border-gray-800 dark:bg-gray-900"
                                >
                                    <div>
                                        <div v-if="related.cover_image_path" class="h-32 w-full overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 mb-3">
                                            <img :src="related.cover_image_path" :alt="related.title" class="h-full w-full object-cover" />
                                        </div>
                                        <span v-if="related.category" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ related.category.name }}
                                        </span>
                                        <h4 class="mt-1 font-serif text-base font-bold text-gray-900 line-clamp-2 hover:text-indigo-600 transition dark:text-white dark:hover:text-indigo-400">
                                            <Link :href="route('posts.show.canonical', { category_slug: related.category ? related.category.slug : 'story', post_slug: related.slug })">
                                                {{ related.title }}
                                            </Link>
                                        </h4>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between text-[11px] text-gray-400">
                                        <span>{{ related.user?.name }}</span>
                                        <span>{{ related.reading_time_minutes }} min read</span>
                                    </div>
                                </article>
                            </div>
                        </section>

                        <!-- Comments Section -->
                        <section class="mt-16 border-t border-gray-200 pt-10 dark:border-gray-800">
                            <h3 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                                Discussion ({{ post.comments_count }})
                            </h3>

                            <!-- New Comment Box -->
                            <div class="mt-6">
                                <template v-if="currentUser">
                                    <form @submit.prevent="submitComment" class="space-y-3">
                                        <div v-if="replyToId" class="flex items-center justify-between rounded-lg bg-indigo-50 px-3 py-1.5 text-xs text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300">
                                            <span>Replying to <strong v-if="replyingToAuthor">@{{ replyingToAuthor }}</strong><span v-else>comment</span>...</span>
                                            <button type="button" @click="cancelReply" class="font-bold hover:underline">Cancel</button>
                                        </div>
                                        <textarea
                                            v-model="commentForm.body"
                                            rows="3"
                                            placeholder="What are your thoughts on this story? (Plain text with limited Markdown supported)"
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm"
                                            required
                                        ></textarea>
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-gray-400">
                                            <span>Supports limited markdown: <code>**bold**</code>, <code>*italic*</code>, <code>`code`</code>, <code>> quote</code>, <code>[link](url)</code></span>
                                            <div class="flex justify-end">
                                                <button
                                                    type="submit"
                                                    :disabled="commentForm.processing"
                                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 disabled:opacity-50 transition"
                                                >
                                                    {{ commentForm.processing ? 'Publishing...' : (replyToId ? 'Post Reply' : 'Publish Comment') }}
                                                </button>
                                            </div>
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

                            <!-- Comments List -->
                            <div class="mt-10 space-y-6">
                                <div
                                    v-for="comment in post.comments"
                                    :key="comment.id"
                                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/60"
                                >
                                    <!-- Comment Author & Info -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <template v-if="!comment.trashed && comment.user">
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
                                            </template>
                                            <template v-else>
                                                <div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-500">
                                                    ?
                                                </div>
                                                <span class="text-xs text-gray-400 italic">Deleted user or content</span>
                                            </template>
                                        </div>

                                        <!-- Top Action Buttons (Reply / Report) -->
                                        <div v-if="!comment.trashed && currentUser" class="flex items-center gap-3 text-xs">
                                            <button
                                                type="button"
                                                @click="setReplyTo(comment.id, comment.user?.name)"
                                                class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                            >
                                                Reply
                                            </button>
                                            <button
                                                type="button"
                                                @click="openReportModal('comment', comment.id, 'Comment by ' + (comment.user?.name || 'User'))"
                                                class="text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition"
                                                title="Report comment"
                                            >
                                                Report
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Inline Edit Form or Rendered Body -->
                                    <div v-if="editingCommentId === comment.id" class="mt-3 space-y-2">
                                        <textarea
                                            v-model="editBody"
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        ></textarea>
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                @click="cancelEdit"
                                                class="rounded-lg px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="button"
                                                @click="saveEdit(comment)"
                                                :disabled="isSavingEdit"
                                                class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                            >
                                                {{ isSavingEdit ? 'Saving...' : 'Save' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-else
                                        class="mt-3 text-sm text-gray-700 dark:text-gray-300 leading-relaxed break-words"
                                        v-html="comment.formatted_body"
                                    ></div>

                                    <!-- Bottom Actions for Comment Owner / Moderator -->
                                    <div
                                        v-if="!comment.trashed && currentUser && (comment.can_edit || currentUser.id === post.user_id || currentUser.role === 'admin' || currentUser.role === 'moderator')"
                                        class="mt-3 flex items-center gap-3 text-xs border-t border-gray-50 pt-2 dark:border-gray-800/60"
                                    >
                                        <button
                                            v-if="comment.can_edit"
                                            type="button"
                                            @click="startEdit(comment)"
                                            class="text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 font-medium"
                                        >
                                            Edit (15m window)
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteComment(comment)"
                                            class="text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 font-medium"
                                        >
                                            Delete
                                        </button>
                                    </div>

                                    <!-- Nested Threaded Replies (Max 2 Levels Deep) -->
                                    <div v-if="comment.replies && comment.replies.length > 0" class="mt-4 space-y-3 pl-6 border-l-2 border-indigo-100 dark:border-indigo-900">
                                        <div
                                            v-for="reply in comment.replies"
                                            :key="reply.id"
                                            class="text-xs rounded-lg bg-gray-50/70 p-3 dark:bg-gray-800/40"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <template v-if="!reply.trashed && reply.user">
                                                        <img
                                                            :src="reply.user.avatar_url"
                                                            :alt="reply.user.name"
                                                            class="h-6 w-6 rounded-full object-cover"
                                                        />
                                                        <Link :href="route('author.show', reply.user.username)" class="font-bold text-gray-900 hover:text-indigo-600 dark:text-white">
                                                            {{ reply.user.name }}
                                                        </Link>
                                                        <span class="text-gray-400">• {{ new Date(reply.created_at).toLocaleDateString() }}</span>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-gray-400 italic">Deleted user or content</span>
                                                    </template>
                                                </div>

                                                <!-- Reply / Report button for nested reply -->
                                                <div v-if="!reply.trashed && currentUser" class="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        @click="setReplyTo(reply.id, reply.user?.name)"
                                                        class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                                    >
                                                        Reply
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="openReportModal('comment', reply.id, 'Reply by ' + (reply.user?.name || 'User'))"
                                                        class="text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition"
                                                        title="Report reply"
                                                    >
                                                        Report
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Reply Edit Form or Rendered Body -->
                                            <div v-if="editingCommentId === reply.id" class="mt-2 space-y-2">
                                                <textarea
                                                    v-model="editBody"
                                                    rows="2"
                                                    class="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                ></textarea>
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        @click="cancelEdit"
                                                        class="rounded px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-200 dark:text-gray-300"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="saveEdit(reply)"
                                                        :disabled="isSavingEdit"
                                                        class="rounded bg-indigo-600 px-2 py-0.5 text-xs text-white hover:bg-indigo-500 disabled:opacity-50"
                                                    >
                                                        Save
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                v-else
                                                class="mt-2 text-gray-600 dark:text-gray-300 leading-relaxed break-words"
                                                v-html="reply.formatted_body"
                                            ></div>

                                            <!-- Reply Edit/Delete actions -->
                                            <div
                                                v-if="!reply.trashed && currentUser && (reply.can_edit || currentUser.id === post.user_id || currentUser.role === 'admin' || currentUser.role === 'moderator')"
                                                class="mt-2 flex items-center gap-3 text-[11px] pt-1"
                                            >
                                                <button
                                                    v-if="reply.can_edit"
                                                    type="button"
                                                    @click="startEdit(reply)"
                                                    class="text-gray-500 hover:text-indigo-600 dark:text-gray-400 font-medium"
                                                >
                                                    Edit (15m window)
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="deleteComment(reply)"
                                                    class="text-rose-500 hover:text-rose-700 dark:text-rose-400 font-medium"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!post.comments || post.comments.length === 0" class="rounded-xl border border-dashed border-gray-200 p-8 text-center text-xs text-gray-400 dark:border-gray-800">
                                    No comments yet. Be the first to share your thoughts!
                                </div>
                            </div>
                        </section>
                    </article>
                </main>

                <!-- Sticky Table of Contents & Series Sidebar (Desktop) -->
                <aside class="hidden lg:block lg:col-span-4">
                    <div class="sticky top-24 space-y-6">
                        <!-- Desktop Series Card in Sidebar -->
                        <div v-if="seriesContext" class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5 shadow-sm dark:border-indigo-950/60 dark:bg-indigo-950/20">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Story Series</span>
                                <span class="rounded-full bg-indigo-100 dark:bg-indigo-900/60 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300">
                                    {{ seriesContext.status_label }}
                                </span>
                            </div>

                            <h3 class="mt-2 text-sm font-bold text-gray-900 dark:text-white line-clamp-2">
                                <Link :href="route('series.show', seriesContext.slug)" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ seriesContext.title }}
                                </Link>
                            </h3>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="seriesContext.current_part_number">Part {{ seriesContext.current_part_number }} of {{ seriesContext.total_published_parts }}</span>
                                <span v-else>Draft part</span>
                            </p>

                            <!-- Ordered Parts List -->
                            <nav class="mt-3 space-y-1.5 max-h-52 overflow-y-auto pr-1 text-xs border-t border-indigo-100 dark:border-indigo-900/60 pt-3">
                                <div
                                    v-for="part in seriesContext.parts"
                                    :key="part.id"
                                    class="flex items-center justify-between gap-1.5"
                                >
                                    <Link
                                        v-if="part.is_published || seriesContext.is_author"
                                        :href="route('posts.show', part.slug)"
                                        :class="[
                                            part.is_current
                                                ? 'font-bold text-indigo-600 dark:text-indigo-400'
                                                : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
                                            'truncate block py-0.5'
                                        ]"
                                    >
                                        <span class="font-mono text-[11px] text-gray-400 mr-1">
                                            {{ part.display_number ? `${part.display_number}.` : '•' }}
                                        </span>
                                        {{ part.title }}
                                    </Link>
                                    <span v-if="!part.is_published" class="text-[10px] bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 rounded px-1.5 py-0.5 shrink-0">
                                        Draft
                                    </span>
                                </div>
                            </nav>
                        </div>

                        <div v-if="toc && toc.length > 0" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Table of Contents
                            </h3>
                            <nav class="mt-4 space-y-2 text-xs max-h-[calc(100vh-200px)] overflow-y-auto pr-1">
                                <a
                                    v-for="item in toc"
                                    :key="item.id"
                                    :href="`#${item.id}`"
                                    @click.prevent="scrollToHeading(item.id)"
                                    :class="[
                                        item.level === 3 ? 'pl-4' : 'font-medium',
                                        activeHeadingId === item.id
                                            ? 'text-indigo-600 dark:text-indigo-400 font-bold border-l-2 border-indigo-600 pl-2'
                                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
                                        'block py-1 transition'
                                    ]"
                                >
                                    {{ item.text }}
                                </a>
                            </nav>
                        </div>

                        <!-- Author Mini Widget in sidebar -->
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Written by
                            </h3>
                            <div class="mt-3 flex items-center gap-3">
                                <Link :href="route('author.show', post.user.username)">
                                    <img
                                        :src="post.user.avatar_url"
                                        :alt="post.user.name"
                                        class="h-10 w-10 rounded-full object-cover"
                                    />
                                </Link>
                                <div>
                                    <Link :href="route('author.show', post.user.username)" class="text-sm font-bold text-gray-900 hover:text-indigo-600 dark:text-white">
                                        {{ post.user.name }}
                                    </Link>
                                    <p class="text-xs text-gray-400">@{{ post.user.username }}</p>
                                </div>
                            </div>
                            <p v-if="post.user.bio" class="mt-3 text-xs text-gray-600 dark:text-gray-300 line-clamp-3 leading-relaxed">
                                {{ post.user.bio }}
                            </p>
                            <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500">
                                <span>{{ authorStats.followers_count }} Followers</span>
                                <Link :href="route('author.show', post.user.username)" class="text-indigo-600 hover:underline dark:text-indigo-400 font-medium">
                                    View profile →
                                </Link>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Report Modal for Post & Comments -->
        <ReportModal
            :show="reportModalOpen"
            :reportable-type="reportType"
            :reportable-id="reportId"
            :item-title="reportTitle"
            @close="reportModalOpen = false"
            @reported="reportModalOpen = false"
        />

        <!-- Start New Series From Post Modal -->
        <div v-if="showStartSeriesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white">Start a New Series</h3>
                    <button type="button" @click="showStartSeriesModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    This story will become <strong class="text-indigo-600 dark:text-indigo-400">Part 1</strong> of the new series.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Series Title</label>
                    <input
                        type="text"
                        v-model="startSeriesTitle"
                        placeholder="e.g. Building Modern Web Apps"
                        class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Description (Optional)</label>
                    <textarea
                        rows="3"
                        v-model="startSeriesDescription"
                        placeholder="What will this series cover?"
                        class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Status</label>
                    <select
                        v-model="startSeriesStatus"
                        class="mt-1 w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    >
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="hiatus">Hiatus</option>
                    </select>
                </div>

                <p v-if="startSeriesError" class="text-xs text-red-500">{{ startSeriesError }}</p>

                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <button
                        type="button"
                        @click="showStartSeriesModal = false"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitStartSeries"
                        :disabled="isStartingSeries"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition"
                    >
                        {{ isStartingSeries ? 'Creating...' : 'Create & Attach Story' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
