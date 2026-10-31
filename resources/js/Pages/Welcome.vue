<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    posts: {
        type: Object,
        required: true,
    },
    tab: {
        type: String,
        default: 'trending',
    },
    heroPost: {
        type: Object,
        default: null,
    },
    sidebarData: {
        type: Object,
        default: () => ({ categories: [], tags: [], top_writers: [] }),
    },
    categories: {
        type: Array,
        default: () => [],
    },
    popularTags: {
        type: Array,
        default: () => [],
    },
    guestPreferences: {
        type: Object,
        default: () => ({}),
    },
    selectedCategory: {
        type: String,
        default: null,
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

const dismissedPostIds = ref(new Set());
const undoToastPost = ref(null);

// Available tabs
const tabs = computed(() => {
    if (currentUser.value) {
        return [
            { id: 'for-you', name: 'For you' },
            { id: 'following', name: 'Following' },
            { id: 'latest', name: 'Latest' },
            { id: 'trending', name: 'Trending' },
        ];
    }
    return [
        { id: 'trending', name: 'Trending' },
        { id: 'latest', name: 'Latest' },
        { id: 'for-you', name: 'For you (Guest)' },
    ];
});

const switchTab = (tabId) => {
    router.get(route('home'), {
        tab: tabId,
        category: props.selectedCategory || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const filterByCategory = (categorySlug) => {
    const newSlug = props.selectedCategory === categorySlug ? undefined : categorySlug;
    router.get(route('home'), {
        tab: props.tab,
        category: newSlug,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// "Not interested" dismissal
const dismissPost = async (post) => {
    dismissedPostIds.value.add(post.id);
    undoToastPost.value = post;

    try {
        await router.post(route('posts.dismiss', post.id), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    } catch (e) {
        // Handled silently
    }

    setTimeout(() => {
        if (undoToastPost.value?.id === post.id) {
            undoToastPost.value = null;
        }
    }, 6000);
};

const undoDismiss = async (post) => {
    dismissedPostIds.value.delete(post.id);
    undoToastPost.value = null;

    await router.post(route('posts.undismiss', post.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const toggleLike = (postSlug) => {
    if (!currentUser.value) {
        router.visit(route('login'));
        return;
    }
    router.post(route('posts.like', postSlug), {}, { preserveScroll: true });
};

const toggleBookmark = (postSlug) => {
    if (!currentUser.value) {
        router.visit(route('login'));
        return;
    }
    router.post(route('posts.bookmark', postSlug), {}, { preserveScroll: true });
};

const toggleFollow = (username) => {
    if (!currentUser.value) {
        router.visit(route('login'));
        return;
    }
    router.post(route('users.follow', username), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Inkwell — Where Good Ideas Take Shape" />

    <AppLayout>
        <!-- Undo Toast notification -->
        <div
            v-if="undoToastPost"
            class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-2xl bg-gray-900 px-4 py-3 text-xs text-white shadow-xl dark:bg-gray-800 border border-gray-700"
        >
            <span>Story hidden. We'll show less like this.</span>
            <button
                type="button"
                @click="undoDismiss(undoToastPost)"
                class="font-bold text-indigo-400 hover:text-indigo-300 underline"
            >
                Undo
            </button>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Hero Featured Post / Editor's Pick Section -->
            <section
                v-if="heroPost && !selectedCategory && tab === (currentUser ? 'for-you' : 'trending')"
                class="mb-12 overflow-hidden rounded-3xl border border-gray-200/90 bg-gradient-to-br from-indigo-50/60 via-white to-gray-50/40 p-6 shadow-sm dark:border-gray-800 dark:from-indigo-950/20 dark:via-gray-900 dark:to-gray-950 lg:p-8"
            >
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-7">
                        <div class="flex items-center gap-2">
                            <span
                                v-if="heroPost.is_featured"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 font-mono text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:bg-amber-950/80 dark:text-amber-300"
                            >
                                ★ Editor’s Pick
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 font-mono text-[11px] font-bold uppercase tracking-wider text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300"
                            >
                                Featured Story
                            </span>

                            <Link
                                v-if="heroPost.category"
                                :href="route('categories.show', heroPost.category.slug)"
                                class="text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-indigo-600"
                            >
                                in {{ heroPost.category.name }}
                            </Link>
                        </div>

                        <h2 class="mt-4 font-serif text-2xl sm:text-4xl font-bold tracking-tight text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400 transition leading-tight">
                            <Link :href="route('posts.show.canonical', { category_slug: heroPost.category ? heroPost.category.slug : 'story', post_slug: heroPost.slug })">
                                {{ heroPost.title }}
                            </Link>
                        </h2>

                        <p class="mt-3 text-sm sm:text-base text-gray-600 dark:text-gray-300 line-clamp-3 leading-relaxed">
                            {{ heroPost.excerpt }}
                        </p>

                        <div class="mt-6 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <Link :href="route('author.show', heroPost.user.username)">
                                    <img
                                        :src="heroPost.user.avatar_url"
                                        :alt="heroPost.user.name"
                                        class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700"
                                    />
                                </Link>
                                <div>
                                    <Link :href="route('author.show', heroPost.user.username)" class="text-sm font-semibold text-gray-900 dark:text-white hover:text-indigo-600">
                                        {{ heroPost.user.name }}
                                    </Link>
                                    <div class="flex items-center gap-2 text-xs text-gray-400">
                                        <span>{{ heroPost.reading_time_minutes }} min read</span>
                                        <span>•</span>
                                        <span>{{ new Date(heroPost.published_at).toLocaleDateString() }}</span>
                                    </div>
                                </div>
                            </div>

                            <Link
                                :href="route('posts.show.canonical', { category_slug: heroPost.category ? heroPost.category.slug : 'story', post_slug: heroPost.slug })"
                                class="rounded-xl bg-gray-900 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 transition"
                            >
                                Read story →
                            </Link>
                        </div>
                    </div>

                    <div v-if="heroPost.cover_image_path" class="lg:col-span-5 h-64 sm:h-80 overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800">
                        <Link :href="route('posts.show.canonical', { category_slug: heroPost.category ? heroPost.category.slug : 'story', post_slug: heroPost.slug })">
                            <img :src="heroPost.cover_image_path" :alt="heroPost.title" class="h-full w-full object-cover hover:scale-105 transition duration-500" />
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Category Filter Pills Bar -->
            <div class="mb-8 flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                <button
                    type="button"
                    @click="filterByCategory(null)"
                    :class="[
                        !selectedCategory
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                            : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300 dark:bg-gray-900 dark:border-gray-800 dark:text-gray-300',
                        'rounded-full px-4 py-1.5 text-xs font-semibold transition whitespace-nowrap shadow-xs'
                    ]"
                >
                    All Topics
                </button>

                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    type="button"
                    @click="filterByCategory(cat.slug)"
                    :class="[
                        selectedCategory === cat.slug
                            ? 'bg-indigo-600 text-white font-semibold'
                            : 'bg-white text-gray-700 border border-gray-200 hover:border-indigo-400 dark:bg-gray-900 dark:border-gray-800 dark:text-gray-300',
                        'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs transition whitespace-nowrap shadow-xs'
                    ]"
                >
                    <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: cat.color || '#4F46E5' }"></span>
                    <span>{{ cat.name }}</span>
                </button>
            </div>

            <!-- Main Layout Grid: Feed + Sidebar -->
            <div class="lg:grid lg:grid-cols-12 lg:gap-10">
                <!-- Feed Column -->
                <main class="lg:col-span-8">
                    <!-- Feed Tabs Header -->
                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-2 mb-6">
                        <nav class="flex space-x-6 text-sm font-semibold">
                            <button
                                v-for="t in tabs"
                                :key="t.id"
                                type="button"
                                @click="switchTab(t.id)"
                                :class="[
                                    tab === t.id
                                        ? 'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200',
                                    'pb-3 transition'
                                ]"
                            >
                                {{ t.name }}
                            </button>
                        </nav>

                        <!-- Customize feed link for logged in -->
                        <Link
                            v-if="currentUser"
                            :href="route('settings.preferences')"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 hidden sm:block"
                        >
                            ⚙ Customize Feed
                        </Link>
                    </div>

                    <!-- Post Cards List -->
                    <div class="space-y-6">
                        <template v-for="post in posts.data" :key="post.id">
                            <article
                                v-if="!dismissedPostIds.has(post.id)"
                                class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm transition hover:border-indigo-200 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700"
                            >
                                <div class="flex flex-col sm:flex-row gap-6 justify-between">
                                    <div class="flex-1 flex flex-col justify-between">
                                        <div>
                                            <!-- Author bar & Category chip -->
                                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center gap-2">
                                                    <Link :href="route('author.show', post.user.username)" class="flex items-center gap-1.5 font-semibold text-gray-800 dark:text-gray-200 hover:text-indigo-600">
                                                        <img :src="post.user.avatar_url" :alt="post.user.name" loading="lazy" decoding="async" width="20" height="20" class="h-5 w-5 rounded-full object-cover" />
                                                        <span>{{ post.user.name }}</span>
                                                    </Link>
                                                    <span v-if="post.category">•</span>
                                                    <Link
                                                        v-if="post.category"
                                                        :href="route('categories.show', post.category.slug)"
                                                        class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                                    >
                                                        {{ post.category.name }}
                                                    </Link>
                                                </div>

                                                <!-- Not Interested Action Dropdown / Button -->
                                                <button
                                                    type="button"
                                                    @click="dismissPost(post)"
                                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xs flex items-center gap-1 transition"
                                                    title="Not interested in this story"
                                                >
                                                    <span>✕</span>
                                                    <span class="hidden sm:inline">Not interested</span>
                                                </button>
                                            </div>

                                            <!-- Title -->
                                            <h3 class="mt-2.5 font-serif text-xl sm:text-2xl font-bold tracking-tight text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400 transition">
                                                <Link :href="route('posts.show.canonical', { category_slug: post.category ? post.category.slug : 'story', post_slug: post.slug })">
                                                    {{ post.title }}
                                                </Link>
                                            </h3>

                                            <!-- Excerpt -->
                                            <p class="mt-2 text-sm text-gray-600 line-clamp-2 dark:text-gray-300 leading-relaxed font-sans">
                                                {{ post.excerpt }}
                                            </p>
                                        </div>

                                        <!-- Card Footer: Stats & Actions -->
                                        <div class="mt-5 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800/80 text-xs text-gray-400">
                                            <div class="flex items-center gap-3">
                                                <span>{{ post.reading_time_minutes }} min read</span>
                                                <span>•</span>
                                                <span>{{ new Date(post.published_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) }}</span>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <!-- Like Button -->
                                                <button
                                                    type="button"
                                                    @click="toggleLike(post.slug)"
                                                    class="flex items-center gap-1 hover:text-red-500 transition"
                                                    title="Like"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                                    </svg>
                                                    <span>{{ post.likes_count }}</span>
                                                </button>

                                                <!-- Bookmark Button -->
                                                <button
                                                    type="button"
                                                    @click="toggleBookmark(post.slug)"
                                                    class="hover:text-indigo-600 transition"
                                                    title="Bookmark"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cover thumbnail -->
                                    <div v-if="post.cover_image_path" class="w-full sm:w-44 h-36 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                        <Link :href="route('posts.show.canonical', { category_slug: post.category ? post.category.slug : 'story', post_slug: post.slug })">
                                            <img :src="post.cover_image_path" :alt="post.title" loading="lazy" decoding="async" width="176" height="144" class="h-full w-full object-cover hover:scale-105 transition duration-300" />
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        </template>

                        <!-- Empty State -->
                        <div v-if="posts.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No stories in this feed yet.</p>
                            <p class="mt-1 text-xs text-gray-500">Explore other topics or check the Trending tab.</p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="posts.links && posts.links.length > 3" class="mt-10 flex justify-center gap-1">
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
                </main>

                <!-- Sidebar (Desktop) -->
                <aside class="mt-12 lg:mt-0 lg:col-span-4 space-y-8">
                    <!-- Top Writers Section -->
                    <div v-if="sidebarData.top_writers && sidebarData.top_writers.length > 0" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Writers to Follow
                        </h3>
                        <div class="mt-4 space-y-4">
                            <div
                                v-for="writer in sidebarData.top_writers"
                                :key="writer.id"
                                class="flex items-center justify-between"
                            >
                                <div class="flex items-center gap-3">
                                    <Link :href="route('author.show', writer.username)">
                                        <img :src="writer.avatar_url" :alt="writer.name" class="h-9 w-9 rounded-full object-cover" />
                                    </Link>
                                    <div>
                                        <Link :href="route('author.show', writer.username)" class="text-xs font-bold text-gray-900 hover:text-indigo-600 dark:text-white">
                                            {{ writer.name }}
                                        </Link>
                                        <p class="text-[11px] text-gray-400">@{{ writer.username }} • {{ writer.followers_count }} followers</p>
                                    </div>
                                </div>

                                <button
                                    v-if="!currentUser || currentUser.id !== writer.id"
                                    type="button"
                                    @click="toggleFollow(writer.username)"
                                    class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    Follow
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Topics Section -->
                    <div v-if="sidebarData.categories && sidebarData.categories.length > 0" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Popular Topics
                        </h3>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                v-for="cat in sidebarData.categories"
                                :key="cat.id"
                                :href="route('categories.show', cat.slug)"
                                class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-indigo-400 hover:text-indigo-600 transition dark:border-gray-800 dark:bg-gray-800/80 dark:text-gray-300"
                            >
                                {{ cat.name }} ({{ cat.posts_count }})
                            </Link>
                        </div>
                    </div>

                    <!-- Trending Tags Section -->
                    <div v-if="popularTags && popularTags.length > 0" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Trending Tags
                        </h3>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            <Link
                                v-for="t in popularTags"
                                :key="t.id"
                                :href="route('tags.show', t.slug)"
                                class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 transition dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                #{{ t.name }}
                            </Link>
                        </div>
                    </div>

                    <!-- Guest Call to Action -->
                    <div v-if="!currentUser" class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5 dark:border-indigo-900/40 dark:bg-indigo-950/30">
                        <h4 class="font-serif text-base font-bold text-gray-900 dark:text-white">
                            Write on Inkwell
                        </h4>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                            Share your insights, essays, and stories with curious readers worldwide.
                        </p>
                        <Link
                            :href="route('register')"
                            class="mt-4 inline-block rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                        >
                            Create an Account
                        </Link>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
