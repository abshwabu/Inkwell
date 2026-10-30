<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    distinctActions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const selectedAction = ref(props.filters.action || '');
const searchQuery = ref(props.filters.q || '');

const applyFilters = () => {
    router.get(route('admin.audit-logs.index'), {
        action: selectedAction.value || undefined,
        q: searchQuery.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilters = () => {
    selectedAction.value = '';
    searchQuery.value = '';
    applyFilters();
};

const expandedLogId = ref(null);
const toggleExpand = (id) => {
    expandedLogId.value = expandedLogId.value === id ? null : id;
};
</script>

<template>
    <Head title="Audit Logs" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Security & Audit Trail
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Immutable historical ledger recording every staff moderation and administrative intervention.
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search action or details..."
                        @keyup.enter="applyFilters"
                        class="w-full sm:w-72 rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <select
                        v-model="selectedAction"
                        @change="applyFilters"
                        class="rounded-xl border-gray-200 bg-gray-50 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="">All Actions</option>
                        <option v-for="action in distinctActions" :key="action" :value="action">
                            {{ action }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="applyFilters"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 transition"
                    >
                        Filter
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 transition"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3.5">Timestamp</th>
                                <th class="px-4 py-3.5">Actor</th>
                                <th class="px-4 py-3.5">Action</th>
                                <th class="px-4 py-3.5">Target</th>
                                <th class="px-4 py-3.5">IP Address</th>
                                <th class="px-5 py-3.5 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-xs font-mono">
                            <template v-for="log in logs.data" :key="log.id">
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                                    <td class="px-5 py-3.5 whitespace-nowrap text-gray-400">
                                        {{ new Date(log.created_at).toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap font-sans font-medium text-gray-900 dark:text-white">
                                        {{ log.user?.name || 'System' }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <span class="rounded bg-indigo-50 px-2 py-0.5 font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                            {{ log.action }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap font-sans text-gray-500 dark:text-gray-400">
                                        <span v-if="log.auditable_type">
                                            {{ log.auditable_type.split('\\').pop() }} #{{ log.auditable_id }}
                                        </span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-gray-400">
                                        {{ log.ip_address || '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-sans">
                                        <button
                                            v-if="log.details && Object.keys(log.details).length > 0"
                                            type="button"
                                            @click="toggleExpand(log.id)"
                                            class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            {{ expandedLogId === log.id ? 'Hide' : 'View' }}
                                        </button>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                </tr>

                                <!-- Expandable JSON Details Drawer -->
                                <tr v-if="expandedLogId === log.id" class="bg-gray-50 dark:bg-gray-800/50">
                                    <td colspan="6" class="p-4">
                                        <pre class="rounded-xl bg-gray-900 p-3 text-[11px] text-gray-200 overflow-x-auto">{{ JSON.stringify(log.details, null, 2) }}</pre>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!logs.data || logs.data.length === 0">
                                <td colspan="6" class="p-8 text-center text-gray-400 font-sans">
                                    No audit log entries recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.links && logs.links.length > 3" class="flex justify-center gap-1 border-t border-gray-100 p-4 dark:border-gray-800">
                    <Link
                        v-for="(link, i) in logs.links"
                        :key="i"
                        :href="link.url || '#'"
                        :class="[
                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200',
                            'px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
