<script setup>
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    series: {
        type: Object,
        required: true,
    },
    parts: {
        type: Array,
        default: () => [],
    },
    firstPart: {
        type: Object,
        default: null,
    },
    continuePart: {
        type: Object,
        default: null,
    },
    isFollowing: {
        type: Boolean,
        default: false,
    },
    isAuthor: {
        type: Boolean,
        default: false,
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const currentUser = page.props.auth?.user;

const localIsFollowing = ref(props.isFollowing);
const localFollowersCount = ref(props.series.followers_count || 0);
const isFollowingLoading = ref(false);

const toggleFollow = async () => {
    if (!currentUser) {
        window.location.href = route('login');
        return;
    }

    if (isFollowingLoading.value) return;

    isFollowingLoading.value = true;
    const prev = localIsFollowing.value;
    const prevCount = localFollowersCount.value;

    localIsFollowing.value = !prev;
    localFollowersCount.value += localIsFollowing.value ? 1 : -1;

    try {
        const res = await axios.post(route('series.follow', props.series.slug));
        localIsFollowing.value = res.data.is_following;
        localFollowersCount.value = res.data.followers_count;
    } catch (err) {
        localIsFollowing.value = prev;
        localFollowersCount.value = prevCount;
    } finally {
        isFollowingLoading.value = false;
    }
};
</script>

<template>
    <Head :title="seo.title || `${series.title} — Inkwell Series`">
        <meta name="description" :content="seo.description || series.description || 'A story series on Inkwell.'" />
        <link v-if="seo.canonical" rel="canonical" :href="seo.canonical" />
        <meta property="og:title" :content="seo.title" />
        <meta property="og:description" :content="seo.description" />
        <meta property="og:image" :content="seo.image" />
        <meta name="twitter:card" content="summary_large_image" />
        <component is="script" type="application/ld+json" v-if="seo.json_ld">
            {{ JSON.stringify(seo.json_ld) }}
        </component>
    </Head>

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <!-- Series Header Hero Card -->
            <div class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <!-- Cover Banner if present -->
                <div v-if="series.cover_image_path" class="h-64 sm:h-80 w-full overflow-hidden bg-gray-100 dark:bg-gray-800 relative">
                    <img :src="series.cover_image_path" :alt="series.title" class="h-full w-full object-cover" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                </div>

                <div class="p-6 sm:p-10">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                Story Series
                            </span>
                            <span
                                :class="[
                                    series.status === 'completed'
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : (series.status === 'hiatus'
                                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300'
                                            : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'),
                                    'rounded-full px-3 py-1 text-xs font-semibold'
                                ]"
                            >
                                {{ series.status_label }}
                            </span>
                        </div>

                        <!-- RSS Feed Icon Link -->
                        <a
                            :href="route('feed.series', series.slug)"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 text-xs text-amber-600 hover:text-amber-700 dark:text-amber-400 font-medium"
                            title="RSS Feed for this series"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6.18 15.64a2.18 2.18 0 0 1 2.18 2.18C8.36 19 7.38 20 6.18 20C5 20 4 19 4 17.82a2.18 2.18 0 0 1 2.18-2.18M4 4.44A15.56 15.56 0 0 1 19.56 20h-2.83A12.73 12.73 0 0 0 4 7.27V4.44m0 5.66a9.9 9.9 0 0 1 9.9 9.9h-2.83A7.07 7.07 0 0 0 4 12.93V10.1z"/>
                            </svg>
                            <span>RSS Feed</span>
                        </a>
                    </div>

                    <!-- Title -->
                    <h1 class="mt-4 font-serif text-3xl sm:text-5xl font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                        {{ series.title }}
                    </h1>

                    <!-- Description -->
                    <p v-if="series.description" class="mt-4 text-base sm:text-lg text-gray-600 dark:text-gray-300 leading-relaxed max-w-3xl">
                        {{ series.description }}
                    </p>

                    <!-- Author Info & Series Stats -->
                    <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                        <Link
                            :href="route('author.show', series.author.username)"
                            class="flex items-center gap-3.5 group"
                        >
                            <img
                                :src="series.author.avatar_url"
                                :alt="series.author.name"
                                class="h-12 w-12 rounded-full object-cover border border-gray-200 dark:border-gray-700"
                            />
                            <div>
                                <h4 class="font-serif text-base font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 transition">
                                    {{ series.author.name }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    @{{ series.author.username }}
                                </p>
                            </div>
                        </Link>

                        <!-- Stats Row -->
                        <div class="flex items-center gap-6 text-xs text-gray-500 dark:text-gray-400 font-medium">
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white text-sm">{{ series.published_posts_count }}</span>
                                <span class="ml-1">Parts</span>
                            </div>
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white text-sm">{{ series.total_reading_time }}</span>
                                <span class="ml-1">Min read total</span>
                            </div>
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white text-sm">{{ localFollowersCount }}</span>
                                <span class="ml-1">Followers</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-8 flex flex-wrap items-center gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                        <!-- Continue Reading Button (If user has progress) -->
                        <Link
                            v-if="continuePart"
                            :href="route('posts.show', continuePart.slug)"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 shadow-sm transition"
                        >
                            <span>Continue: Part {{ continuePart.display_number }}</span>
                            <span>→</span>
                        </Link>

                        <!-- Start Reading Button (From Part 1) -->
                        <Link
                            v-else-if="firstPart"
                            :href="route('posts.show', firstPart.slug)"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 shadow-sm transition"
                        >
                            <span>Start Reading (Part 1)</span>
                            <span>→</span>
                        </Link>

                        <!-- Follow / Unfollow Series -->
                        <button
                            type="button"
                            @click="toggleFollow"
                            :disabled="isFollowingLoading"
                            :class="[
                                localIsFollowing
                                    ? 'border border-gray-300 bg-white text-gray-700 hover:bg-red-50 hover:text-red-600 hover:border-red-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-red-950 dark:hover:text-red-400'
                                    : 'border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-300',
                                'inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-semibold transition disabled:opacity-50'
                            ]"
                        >
                            <span>{{ localIsFollowing ? 'Following Series' : 'Follow Series' }}</span>
                        </button>

                        <!-- Write Next Part (Owner only) -->
                        <Link
                            v-if="isAuthor"
                            :href="route('posts.create', { series: series.slug })"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 transition"
                        >
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Write Next Part</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Full Ordered Parts List -->
            <section class="mt-12">
                <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-800">
                    <h2 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Series Content ({{ parts.length }} {{ parts.length === 1 ? 'part' : 'parts' }})
                    </h2>
                </div>

                <div v-if="parts.length > 0" class="mt-8 space-y-4">
                    <article
                        v-for="part in parts"
                        :key="part.id"
                        class="group rounded-2xl border border-gray-200/80 bg-white p-6 shadow-xs hover:border-indigo-300 hover:shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-900 transition flex flex-col sm:flex-row sm:items-center justify-between gap-6"
                    >
                        <div class="flex-1">
                            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider text-[11px]">
                                    {{ part.display_number ? `Part ${part.display_number}` : 'Unpublished' }}
                                </span>

                                <span v-if="!part.is_published" class="rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 px-2 py-0.5 text-[10px] font-semibold">
                                    Draft
                                </span>

                                <span v-if="part.category" class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300">
                                    {{ part.category.name }}
                                </span>

                                <span>•</span>
                                <span>{{ part.reading_time_minutes || 1 }} min read</span>

                                <span v-if="part.published_at">•</span>
                                <span v-if="part.published_at">{{ part.published_at }}</span>
                            </div>

                            <h3 class="mt-2 font-serif text-xl font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">
                                <Link v-if="part.is_published || isAuthor" :href="route('posts.show', part.slug)">
                                    {{ part.title }}
                                </Link>
                                <span v-else>{{ part.title }}</span>
                            </h3>

                            <p v-if="part.excerpt" class="mt-1.5 text-xs text-gray-600 dark:text-gray-300 line-clamp-2 leading-relaxed">
                                {{ part.excerpt }}
                            </p>
                        </div>

                        <div class="shrink-0 flex items-center gap-3">
                            <Link
                                v-if="part.is_published || isAuthor"
                                :href="route('posts.show', part.slug)"
                                class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-indigo-950/60 dark:hover:text-indigo-300 transition"
                            >
                                Read Part →
                            </Link>

                            <Link
                                v-if="isAuthor"
                                :href="route('posts.edit', part.id)"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 transition"
                            >
                                Edit
                            </Link>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center text-xs text-gray-400">
                    No stories published in this series yet.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
