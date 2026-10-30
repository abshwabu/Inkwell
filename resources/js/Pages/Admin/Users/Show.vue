<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    subject: {
        type: Object,
        required: true,
    },
    recentPosts: {
        type: Array,
        default: () => [],
    },
    recentComments: {
        type: Array,
        default: () => [],
    },
    reportsAgainst: {
        type: Array,
        default: () => [],
    },
    auditHistory: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head :title="`User: ${subject.name}`" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Back & Header -->
            <div>
                <Link :href="route('admin.users.index')" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                    ← Back to Users
                </Link>
            </div>

            <!-- Profile Summary Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <img :src="subject.avatar_url" class="h-16 w-16 rounded-full object-cover ring-2 ring-indigo-50 dark:ring-indigo-950" />
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="font-serif text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ subject.name }}
                                </h1>
                                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                    {{ subject.role }}
                                </span>
                                <span v-if="subject.is_banned" class="rounded bg-rose-100 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-rose-700 dark:bg-rose-950 dark:text-rose-400">
                                    Suspended
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">@{{ subject.username }} • {{ subject.email }} • Member since {{ new Date(subject.created_at).toLocaleDateString() }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 rounded-xl bg-gray-50 p-3 text-center dark:bg-gray-800/60">
                        <div>
                            <span class="block text-lg font-bold text-gray-900 dark:text-white">{{ subject.posts_count || 0 }}</span>
                            <span class="text-[10px] uppercase text-gray-400">Stories</span>
                        </div>
                        <div class="h-6 w-px bg-gray-200 dark:bg-gray-700"></div>
                        <div>
                            <span class="block text-lg font-bold text-gray-900 dark:text-white">{{ subject.comments_count || 0 }}</span>
                            <span class="text-[10px] uppercase text-gray-400">Comments</span>
                        </div>
                        <div class="h-6 w-px bg-gray-200 dark:bg-gray-700"></div>
                        <div>
                            <span class="block text-lg font-bold text-gray-900 dark:text-white">{{ subject.followers_count || 0 }}</span>
                            <span class="text-[10px] uppercase text-gray-400">Followers</span>
                        </div>
                    </div>
                </div>

                <div v-if="subject.is_banned && subject.ban_reason" class="mt-4 rounded-xl bg-rose-50 p-3 text-xs text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
                    <strong>Suspension Reason:</strong> {{ subject.ban_reason }}
                </div>
            </div>

            <!-- Two Column Activity Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Recent Stories -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white mb-4">
                        Recent Stories ({{ recentPosts.length }})
                    </h3>
                    <div v-if="recentPosts.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="post in recentPosts" :key="post.id" class="py-3 flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ post.title }}</p>
                                <p class="text-[10px] text-gray-400">{{ post.status }} • {{ new Date(post.created_at).toLocaleDateString() }}</p>
                            </div>
                            <span class="text-xs text-gray-500">{{ post.views_count }} views</span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400 py-4 text-center">No stories created yet.</p>
                </div>

                <!-- Recent Comments -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white mb-4">
                        Recent Comments ({{ recentComments.length }})
                    </h3>
                    <div v-if="recentComments.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="comment in recentComments" :key="comment.id" class="py-3">
                            <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">"{{ comment.body }}"</p>
                            <p class="mt-1 text-[10px] text-gray-400">On "{{ comment.post?.title || 'Story' }}" • {{ new Date(comment.created_at).toLocaleDateString() }}</p>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400 py-4 text-center">No comments written yet.</p>
                </div>
            </div>

            <!-- Reports Filed Against User / Content -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white mb-4">
                    Flags & Reports Filed Against This User ({{ reportsAgainst.length }})
                </h3>
                <div v-if="reportsAgainst.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="report in reportsAgainst" :key="report.id" class="py-3 flex items-start justify-between">
                        <div>
                            <span class="rounded bg-rose-50 px-2 py-0.5 text-[10px] font-bold uppercase text-rose-700 dark:bg-rose-950 dark:text-rose-400">
                                {{ report.reason }}
                            </span>
                            <span class="ml-2 text-xs text-gray-500">Reported on {{ new Date(report.created_at).toLocaleDateString() }}</span>
                            <p v-if="report.notes" class="mt-1 text-xs text-gray-600 dark:text-gray-400 italic">"{{ report.notes }}"</p>
                        </div>
                        <span class="text-xs font-semibold uppercase text-gray-400">{{ report.status }}</span>
                    </div>
                </div>
                <p v-else class="text-xs text-gray-400 py-4 text-center">No complaints or flags on record.</p>
            </div>

            <!-- Audit Trail -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white mb-4">
                    Audit Log History
                </h3>
                <div v-if="auditHistory.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                    <div v-for="log in auditHistory" :key="log.id" class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ log.action }}</span>
                            <span class="ml-2 text-gray-400">by {{ log.user?.name || 'System' }}</span>
                        </div>
                        <span class="text-gray-400">{{ new Date(log.created_at).toLocaleString() }}</span>
                    </div>
                </div>
                <p v-else class="text-xs text-gray-400 py-4 text-center">No administrative actions logged.</p>
            </div>
        </div>
    </AdminLayout>
</template>
