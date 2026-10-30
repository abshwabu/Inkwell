<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    chartData: {
        type: Object,
        required: true,
    },
});

const getMaxValue = (arr) => Math.max(...arr, 5);

const generateSvgPath = (data, height = 80, width = 400) => {
    if (!data || data.length === 0) return '';
    const max = getMaxValue(data);
    const stepX = width / (data.length - 1);

    const points = data.map((val, idx) => {
        const x = idx * stepX;
        const y = height - (val / max) * (height - 10) - 5;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    });

    return `M ${points.join(' L ')}`;
};

const generateSvgArea = (data, height = 80, width = 400) => {
    if (!data || data.length === 0) return '';
    const linePath = generateSvgPath(data, height, width);
    return `${linePath} L ${width},${height} L 0,${height} Z`;
};
</script>

<template>
    <Head title="Admin Overview" />

    <AdminLayout>
        <div class="space-y-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Admin Overview
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Platform metrics, user activity, and moderation health over the last 30 days.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="stats.pending_reports > 0"
                        :href="route('admin.reports.index')"
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 transition"
                    >
                        <span>{{ stats.pending_reports }} Pending Reports</span>
                    </Link>
                </div>
            </div>

            <!-- Stats Metric Cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <!-- Total Users -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Users</span>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_users }}</p>
                    <span class="text-[11px] text-indigo-600 dark:text-indigo-400">+{{ stats.signups_30d }} past 30d</span>
                </div>

                <!-- Total Stories -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Stories</span>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_posts }}</p>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ stats.published_posts }} published</span>
                </div>

                <!-- Total Views -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Views</span>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_views }}</p>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">All-time reads</span>
                </div>

                <!-- 30d Signups -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Signups (30d)</span>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.signups_30d }}</p>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400">New accounts</span>
                </div>

                <!-- Published Stories -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Published</span>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.published_posts }}</p>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Live articles</span>
                </div>

                <!-- Pending Reports -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Reports Queue</span>
                    <p class="mt-2 text-2xl font-bold" :class="stats.pending_reports > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white'">
                        {{ stats.pending_reports }}
                    </p>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Requires review</span>
                </div>
            </div>

            <!-- 30-Day Activity Charts -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Signups Chart -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Daily Signups (30d)</h3>
                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ chartData.signups.reduce((a, b) => a + b, 0) }} total
                        </span>
                    </div>
                    <div class="h-28 w-full overflow-hidden">
                        <svg viewBox="0 0 400 80" class="h-full w-full overflow-visible">
                            <path
                                :d="generateSvgArea(chartData.signups)"
                                class="fill-indigo-50 dark:fill-indigo-950/40"
                            />
                            <path
                                :d="generateSvgPath(chartData.signups)"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="2.5"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                    <div class="mt-2 flex justify-between text-[10px] text-gray-400">
                        <span>{{ chartData.labels[0] }}</span>
                        <span>{{ chartData.labels[chartData.labels.length - 1] }}</span>
                    </div>
                </div>

                <!-- Posts Published Chart -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Stories Created (30d)</h3>
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ chartData.posts.reduce((a, b) => a + b, 0) }} total
                        </span>
                    </div>
                    <div class="h-28 w-full overflow-hidden">
                        <svg viewBox="0 0 400 80" class="h-full w-full overflow-visible">
                            <path
                                :d="generateSvgArea(chartData.posts)"
                                class="fill-emerald-50 dark:fill-emerald-950/40"
                            />
                            <path
                                :d="generateSvgPath(chartData.posts)"
                                fill="none"
                                stroke="#10b981"
                                stroke-width="2.5"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                    <div class="mt-2 flex justify-between text-[10px] text-gray-400">
                        <span>{{ chartData.labels[0] }}</span>
                        <span>{{ chartData.labels[chartData.labels.length - 1] }}</span>
                    </div>
                </div>

                <!-- Story Views Chart -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Story Views (30d)</h3>
                        <span class="text-xs font-semibold text-sky-600 dark:text-sky-400">
                            {{ chartData.views.reduce((a, b) => a + b, 0) }} views
                        </span>
                    </div>
                    <div class="h-28 w-full overflow-hidden">
                        <svg viewBox="0 0 400 80" class="h-full w-full overflow-visible">
                            <path
                                :d="generateSvgArea(chartData.views)"
                                class="fill-sky-50 dark:fill-sky-950/40"
                            />
                            <path
                                :d="generateSvgPath(chartData.views)"
                                fill="none"
                                stroke="#0ea5e9"
                                stroke-width="2.5"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                    <div class="mt-2 flex justify-between text-[10px] text-gray-400">
                        <span>{{ chartData.labels[0] }}</span>
                        <span>{{ chartData.labels[chartData.labels.length - 1] }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Action Links -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">Quick Navigation</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <Link
                        :href="route('admin.reports.index')"
                        class="flex flex-col p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-gray-800 dark:hover:bg-gray-800 transition"
                    >
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Moderation Queue</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Review flagged comments and stories</span>
                    </Link>
                    <Link
                        :href="route('admin.posts.index')"
                        class="flex flex-col p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-gray-800 dark:hover:bg-gray-800 transition"
                    >
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Manage Stories</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Feature, unpublish, or delete</span>
                    </Link>
                    <Link
                        :href="route('admin.users.index')"
                        class="flex flex-col p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-gray-800 dark:hover:bg-gray-800 transition"
                    >
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">User Accounts</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Inspect activity and ban spam accounts</span>
                    </Link>
                    <Link
                        :href="route('admin.categories.index')"
                        class="flex flex-col p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-gray-800 dark:hover:bg-gray-800 transition"
                    >
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Categories & Tags</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Reorder topics and merge duplicate tags</span>
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
