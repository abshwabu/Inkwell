<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    archiveYears: {
        type: Object,
        required: true,
    },
    recentStories: {
        type: Array,
        default: () => [],
    },
    totalPublished: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <Head title="The Archives — Inkwell" />

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="border-b border-gray-200 pb-8 dark:border-gray-800">
                <span class="rounded-lg bg-indigo-50 px-3 py-1 font-mono text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                    Chronology
                </span>
                <h1 class="mt-3 font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                    The Inkwell Archives
                </h1>
                <p class="mt-3 text-base text-gray-600 dark:text-gray-300 max-w-2xl leading-relaxed">
                    Explore {{ totalPublished }} published essays and stories organized chronologically by year and month.
                </p>
            </div>

            <!-- Archives Grid by Year -->
            <div class="mt-10 space-y-10">
                <section v-for="(months, year) in archiveYears" :key="year" class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
                        <h2 class="font-serif text-2xl font-bold text-gray-900 dark:text-white">
                            <Link :href="route('archive.show', { year })" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ year }}
                            </Link>
                        </h2>
                        <Link
                            :href="route('archive.show', { year })"
                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                            View entire year →
                        </Link>
                    </div>

                    <!-- Months list -->
                    <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        <Link
                            v-for="item in months"
                            :key="item.month"
                            :href="route('archive.show', { year: item.year, month: item.month_formatted })"
                            class="group rounded-xl border border-gray-150 p-4 hover:border-indigo-500 hover:bg-indigo-50/40 transition dark:border-gray-800 dark:hover:bg-indigo-950/30"
                        >
                            <p class="font-serif font-bold text-gray-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400 text-base">
                                {{ item.month_name }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ item.count }} {{ item.count === 1 ? 'story' : 'stories' }}
                            </p>
                        </Link>
                    </div>
                </section>

                <div v-if="Object.keys(archiveYears).length === 0" class="rounded-2xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800">
                    <p class="text-sm text-gray-500">No published archives available yet.</p>
                </div>
            </div>

            <!-- Recent Stories in Archives -->
            <section v-if="recentStories && recentStories.length > 0" class="mt-16 border-t border-gray-200 pt-10 dark:border-gray-800">
                <h2 class="font-serif text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Recently Published
                </h2>
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <article
                        v-for="story in recentStories"
                        :key="story.id"
                        class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 transition dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div>
                            <div v-if="story.cover_image_path" class="h-32 w-full overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 mb-3">
                                <img :src="story.cover_image_path" :alt="story.title" class="h-full w-full object-cover" />
                            </div>
                            <span v-if="story.category" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ story.category.name }}
                            </span>
                            <h3 class="mt-1 font-serif text-base font-bold text-gray-900 line-clamp-2 hover:text-indigo-600 transition dark:text-white dark:hover:text-indigo-400">
                                <Link :href="route('posts.show.canonical', { category_slug: story.category ? story.category.slug : 'story', post_slug: story.slug })">
                                    {{ story.title }}
                                </Link>
                            </h3>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-[11px] text-gray-400">
                            <span>{{ story.user?.name }}</span>
                            <span>{{ story.reading_time_minutes }} min read</span>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
