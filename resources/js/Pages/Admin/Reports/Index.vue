<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useConfirmModal } from '@/Composables/useConfirmModal';

const props = defineProps({
    reports: {
        type: Object,
        required: true,
    },
    currentStatus: {
        type: String,
        default: 'pending',
    },
    counts: {
        type: Object,
        default: () => ({ pending: 0, reviewed: 0, dismissed: 0 }),
    },
});

const actionModalOpen = ref(false);
const actionType = ref('warn'); // 'warn' or 'ban'
const activeReport = ref(null);
const actionReason = ref('');

const openActionModal = (report, type) => {
    activeReport.value = report;
    actionType.value = type;
    actionReason.value = type === 'ban' ? `Account suspended due to policy violation in ${report.reason}.` : `Please adhere to our community guidelines regarding ${report.reason}.`;
    actionModalOpen.value = true;
};

const submitAction = () => {
    if (!activeReport.value || !actionReason.value.trim()) return;

    if (actionType.value === 'warn') {
        router.post(route('admin.reports.warn', activeReport.value.id), {
            warning_reason: actionReason.value.trim(),
        }, {
            preserveScroll: true,
            onSuccess: () => { actionModalOpen.value = false; },
        });
    } else if (actionType.value === 'ban') {
        router.post(route('admin.reports.ban', activeReport.value.id), {
            ban_reason: actionReason.value.trim(),
        }, {
            preserveScroll: true,
            onSuccess: () => { actionModalOpen.value = false; },
        });
    }
};

const { modalState, confirm, handleConfirm, handleClose } = useConfirmModal();

const dismissReport = (report) => {
    router.post(route('admin.reports.dismiss', report.id), {}, { preserveScroll: true });
};

const hideContent = (report) => {
    confirm({
        title: 'Hide Content',
        message: 'Are you sure you want to hide this reported content from public view?',
        confirmText: 'Hide Content',
        variant: 'warning',
        onConfirm: () => {
            router.post(route('admin.reports.hide', report.id), {}, { preserveScroll: true });
        },
    });
};

const switchStatus = (status) => {
    router.get(route('admin.reports.index'), { status }, { preserveState: true });
};
</script>

<template>
    <Head title="Moderation Queue" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <h1 class="font-serif text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Moderation Queue
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Review reported comments and stories, take disciplinary action, or dismiss flags.
                    </p>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-800">
                <button
                    type="button"
                    @click="switchStatus('pending')"
                    :class="[
                        currentStatus === 'pending'
                            ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                        'border-b-2 px-4 py-2.5 text-xs transition flex items-center gap-2'
                    ]"
                >
                    <span>Pending</span>
                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-400">
                        {{ counts.pending }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchStatus('reviewed')"
                    :class="[
                        currentStatus === 'reviewed'
                            ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                        'border-b-2 px-4 py-2.5 text-xs transition flex items-center gap-2'
                    ]"
                >
                    <span>Reviewed (Actioned)</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {{ counts.reviewed }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchStatus('dismissed')"
                    :class="[
                        currentStatus === 'dismissed'
                            ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                        'border-b-2 px-4 py-2.5 text-xs transition flex items-center gap-2'
                    ]"
                >
                    <span>Dismissed</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {{ counts.dismissed }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="switchStatus('all')"
                    :class="[
                        currentStatus === 'all'
                            ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                        'border-b-2 px-4 py-2.5 text-xs transition'
                    ]"
                >
                    All Reports
                </button>
            </div>

            <!-- Reports List -->
            <div v-if="reports.data && reports.data.length > 0" class="space-y-4">
                <div
                    v-for="report in reports.data"
                    :key="report.id"
                    class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <!-- Flag Meta -->
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-rose-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-rose-700 dark:bg-rose-950/60 dark:text-rose-400">
                                    {{ report.reason }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    Flagged by <strong class="text-gray-700 dark:text-gray-300">{{ report.user?.name || 'Anonymous' }}</strong>
                                    on {{ new Date(report.created_at).toLocaleDateString() }}
                                </span>
                            </div>
                            <p v-if="report.notes" class="mt-2 text-xs italic text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 p-2.5 rounded-lg border border-gray-100 dark:border-gray-700">
                                Reporter notes: "{{ report.notes }}"
                            </p>
                        </div>

                        <!-- Status badge -->
                        <span
                            :class="{
                                'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300': report.status === 'pending',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300': report.status === 'reviewed',
                                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300': report.status === 'dismissed',
                            }"
                            class="self-start rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                        >
                            {{ report.status }}
                        </span>
                    </div>

                    <!-- Reported Content Preview -->
                    <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50/75 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                            <span>Target: <strong class="capitalize text-gray-900 dark:text-white">{{ report.reportable_type?.includes('Post') ? 'Story' : 'Comment' }}</strong></span>
                            <span v-if="report.reportable?.user">
                                Author: <strong class="text-gray-900 dark:text-white">{{ report.reportable.user.name }} (@{{ report.reportable.user.username }})</strong>
                                <span v-if="report.reportable.user.is_banned" class="ml-1 text-rose-600 font-bold">(BANNED)</span>
                            </span>
                        </div>

                        <!-- If Comment -->
                        <div v-if="report.reportable_type?.includes('Comment')">
                            <p class="text-xs text-gray-800 dark:text-gray-200 line-clamp-3 italic">
                                "{{ report.reportable?.body || '[Deleted content]' }}"
                            </p>
                            <p v-if="report.reportable?.post" class="mt-2 text-[10px] text-gray-400">
                                On story: "{{ report.reportable.post.title }}"
                            </p>
                        </div>

                        <!-- If Post -->
                        <div v-else>
                            <h4 class="font-serif font-bold text-gray-900 dark:text-white text-sm">
                                {{ report.reportable?.title || '[Deleted story]' }}
                            </h4>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300 line-clamp-2">
                                {{ report.reportable?.excerpt }}
                            </p>
                        </div>
                    </div>

                    <!-- Moderator Actions -->
                    <div v-if="report.status === 'pending'" class="mt-4 flex flex-wrap items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            @click="dismissReport(report)"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 transition"
                        >
                            Dismiss Flag
                        </button>

                        <button
                            type="button"
                            @click="hideContent(report)"
                            class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600 transition shadow-xs"
                        >
                            Hide Content
                        </button>

                        <button
                            type="button"
                            @click="openActionModal(report, 'warn')"
                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 transition shadow-xs"
                        >
                            Warn Author
                        </button>

                        <button
                            type="button"
                            @click="openActionModal(report, 'ban')"
                            class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500 transition shadow-xs"
                        >
                            Ban Author
                        </button>
                    </div>

                    <div v-else class="mt-4 text-[11px] text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-800">
                        Resolved by {{ report.resolver?.name || 'Moderator' }} on {{ new Date(report.resolved_at).toLocaleDateString() }}
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-2xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-800 bg-white/50 dark:bg-gray-900/50"
            >
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 mb-3">
                    ✓
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">All clear!</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    No reports currently match the selected status filter.
                </p>
            </div>

            <!-- Pagination -->
            <div v-if="reports.links && reports.links.length > 3" class="flex justify-center gap-1 mt-6">
                <Link
                    v-for="(link, i) in reports.links"
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

        <!-- Warn / Ban Modal -->
        <div
            v-if="actionModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @click.self="actionModalOpen = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ actionType === 'ban' ? 'Ban User Account' : 'Issue Warning to Author' }}
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Author: <span class="font-semibold text-gray-900 dark:text-white">{{ activeReport?.reportable?.user?.name }}</span>
                </p>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                        Reason logged in audit trail:
                    </label>
                    <textarea
                        v-model="actionReason"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        required
                    ></textarea>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="actionModalOpen = false"
                        class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitAction"
                        :class="actionType === 'ban' ? 'bg-rose-600 hover:bg-rose-500' : 'bg-indigo-600 hover:bg-indigo-500'"
                        class="rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm transition"
                    >
                        Confirm {{ actionType === 'ban' ? 'Ban' : 'Warning' }}
                    </button>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="modalState.show"
            :title="modalState.title"
            :message="modalState.message"
            :confirm-text="modalState.confirmText"
            :cancel-text="modalState.cancelText"
            :variant="modalState.variant"
            :type="modalState.type"
            @confirm="handleConfirm"
            @close="handleClose"
        />
    </AdminLayout>
</template>
