<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
    posts: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const featuredArticles = [
    {
        id: 1,
        title: 'Crafting Words That Linger: The Philosophy Behind Modern Web Publishing',
        excerpt: 'In an era overwhelmed by short-form feeds and algorithmic noise, thoughtful writing and focused reading spaces matter more than ever.',
        author: {
            name: 'Sarah Chen',
            avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=120&auto=format&fit=crop&q=80',
        },
        category: 'Writing & Craft',
        readingTime: '5 min read',
        date: 'Oct 28, 2026',
    },
    {
        id: 2,
        title: 'Why SQLite with WAL Mode is the Secret Superpower for Modern Web Apps',
        excerpt: 'Explore how combining Write-Ahead Logging with modern single-file database architecture delivers blisteringly fast reads and resilient local state.',
        author: {
            name: 'Alex Rivera',
            avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
        },
        category: 'Technology',
        readingTime: '7 min read',
        date: 'Oct 29, 2026',
    },
    {
        id: 3,
        title: 'Minimalist Typography: Designing for Calm Reading Experiences',
        excerpt: 'How intentional whitespace, fluid font scales, and respectful dark modes cultivate digital environments where longform reading feels natural.',
        author: {
            name: 'Elena Rostova',
            avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
        },
        category: 'Design & UX',
        readingTime: '4 min read',
        date: 'Oct 30, 2026',
    },
];
</script>

<template>
    <Head title="Inkwell — Where Good Ideas Take Shape" />

    <AppLayout>
        <!-- Hero Section -->
        <section class="border-b border-gray-200/80 bg-gradient-to-b from-indigo-50/50 via-white to-transparent py-16 dark:border-gray-800 dark:from-indigo-950/20 dark:via-gray-950 dark:to-transparent sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-white px-3 py-1 text-xs font-semibold text-indigo-700 shadow-sm dark:border-indigo-900 dark:bg-gray-900 dark:text-indigo-300">
                        <span class="flex h-2 w-2 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                        Public & Open Publishing Platform
                    </div>
                    <h1 class="mt-6 font-serif text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl sm:leading-tight">
                        Stories, ideas, and expertise worth reading.
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-gray-600 dark:text-gray-300 sm:text-xl">
                        Inkwell is a welcoming platform for curious minds. Anyone can read freely without logging in, and anyone can register to publish their thoughts to the world.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <Link
                            :href="$page.props.auth?.user ? route('dashboard') : route('register')"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        >
                            <span>{{ $page.props.auth?.user ? 'Go to Dashboard' : 'Start Reading & Writing' }}</span>
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                            </svg>
                        </Link>
                        <a
                            href="#featured"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-6 py-3 text-base font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Explore Articles
                        </a>
                        <Link
                            :href="route('archive.index')"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-6 py-3 text-base font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            The Archives
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Topic Pills -->
        <section v-if="categories && categories.length > 0" class="border-b border-gray-200/80 bg-white/70 py-4 dark:border-gray-800 dark:bg-gray-900/60">
            <div class="mx-auto flex max-w-7xl items-center gap-3 overflow-x-auto px-4 sm:px-6 lg:px-8">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 shrink-0">
                    Topics:
                </span>
                <div class="flex items-center gap-2">
                    <Link
                        v-for="cat in categories"
                        :key="cat.id"
                        :href="route('categories.show', cat.slug)"
                        class="rounded-full border border-gray-200 bg-white px-3.5 py-1 text-xs font-medium text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-indigo-500 dark:hover:text-indigo-400 whitespace-nowrap shadow-xs"
                    >
                        {{ cat.name }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- Main Articles Grid -->
        <section id="featured" class="py-12 sm:py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between pb-8">
                    <div>
                        <h2 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                            Latest Stories
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Discover freshly penned insights from our writers across the globe.
                        </p>
                    </div>
                    <Link
                        :href="route('search')"
                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                    >
                        Search all stories →
                    </Link>
                </div>

                <!-- When dynamic posts are available from DB -->
                <div v-if="posts && posts.data && posts.data.length > 0" class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <article
                        v-for="post in posts.data"
                        :key="post.id"
                        class="group flex flex-col justify-between rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-900"
                    >
                        <div>
                            <div v-if="post.cover_image_path" class="mb-4 h-44 w-full overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                <img :src="post.cover_image_path" :alt="post.title" class="h-full w-full object-cover group-hover:scale-105 transition duration-300" />
                            </div>

                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <Link
                                    v-if="post.category"
                                    :href="route('categories.show', post.category.slug)"
                                    class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-medium text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 hover:underline"
                                >
                                    {{ post.category.name }}
                                </Link>
                                <span v-else>Story</span>
                                <span>{{ post.reading_time_minutes }} min read</span>
                            </div>

                            <h3 class="mt-4 font-serif text-xl font-bold tracking-tight text-gray-900 transition group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                                <Link :href="route('posts.show.canonical', { category_slug: post.category ? post.category.slug : 'story', post_slug: post.slug })">
                                    {{ post.title }}
                                </Link>
                            </h3>

                            <p class="mt-3 text-sm leading-relaxed text-gray-600 line-clamp-3 dark:text-gray-300">
                                {{ post.excerpt }}
                            </p>
                        </div>

                        <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800">
                            <div class="flex items-center gap-3">
                                <img
                                    :src="post.user.avatar_url"
                                    :alt="post.user.name"
                                    class="h-9 w-9 rounded-full object-cover"
                                />
                                <div class="text-xs">
                                    <Link :href="route('author.show', post.user.username)" class="font-semibold text-gray-900 dark:text-white hover:text-indigo-600">
                                        {{ post.user.name }}
                                    </Link>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        {{ new Date(post.published_at).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-400">
                                <span>{{ post.views_count }} views</span>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Static fallback if DB has no posts yet -->
                <div v-else class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <article
                        v-for="article in featuredArticles"
                        :key="article.id"
                        class="group flex flex-col justify-between rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-900"
                    >
                        <div>
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-medium text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300">
                                    {{ article.category }}
                                </span>
                                <span>{{ article.readingTime }}</span>
                            </div>

                            <h3 class="mt-4 font-serif text-xl font-bold tracking-tight text-gray-900 transition group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                                {{ article.title }}
                            </h3>

                            <p class="mt-3 text-sm leading-relaxed text-gray-600 line-clamp-3 dark:text-gray-300">
                                {{ article.excerpt }}
                            </p>
                        </div>

                        <div class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                            <img
                                :src="article.author.avatar"
                                :alt="article.author.name"
                                class="h-9 w-9 rounded-full object-cover"
                            />
                            <div class="text-xs">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ article.author.name }}</p>
                                <p class="text-gray-500 dark:text-gray-400">{{ article.date }}</p>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Pagination links on home if available -->
                <div v-if="posts && posts.links && posts.links.length > 3" class="mt-12 flex justify-center gap-1">
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
        </section>

        <!-- Prose / Typography Showcase Section -->
        <section class="border-t border-gray-200/80 bg-white py-12 dark:border-gray-800 dark:bg-gray-900/40">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="prose prose-indigo max-w-none dark:prose-invert">
                    <h3 class="font-serif">About Inkwell</h3>
                    <p>
                        Inkwell is built from the ground up to honor the craft of publishing. Readers enjoy clean typography, responsive design, and seamless light/dark modes with zero paywalls. Writers enjoy a distraction-free canvas and a supportive community.
                    </p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
