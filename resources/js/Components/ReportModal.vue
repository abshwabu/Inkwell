<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    reportableType: {
        type: String,
        required: true, // 'post' or 'comment'
    },
    reportableId: {
        type: Number,
        required: true,
    },
    itemTitle: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'reported']);

const reasons = [
    { value: 'spam', label: 'Spam or advertising' },
    { value: 'harassment', label: 'Harassment or hate speech' },
    { value: 'misinformation', label: 'Misinformation or misleading content' },
    { value: 'copyright', label: 'Copyright violation' },
    { value: 'inappropriate', label: 'Inappropriate or harmful content' },
    { value: 'other', label: 'Other concern' },
];

const selectedReason = ref('spam');
const notes = ref('');
const isSubmitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const submitReport = async () => {
    isSubmitting.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await axios.post(route('reports.store'), {
            reportable_type: props.reportableType,
            reportable_id: props.reportableId,
            reason: selectedReason.value,
            notes: notes.value,
        });

        successMessage.value = response.data.message || 'Thank you for reporting this. Our moderation team will review it.';
        emit('reported');

        setTimeout(() => {
            closeModal();
        }, 1500);
    } catch (err) {
        if (err.response?.status === 429) {
            errorMessage.value = 'Too many requests. Please wait a moment before reporting again.';
        } else {
            errorMessage.value = err.response?.data?.message || 'Failed to submit report. Please try again.';
        }
    } finally {
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    errorMessage.value = '';
    successMessage.value = '';
    notes.value = '';
    selectedReason.value = 'spam';
    emit('close');
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
        @click.self="closeModal"
    >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Report {{ reportableType === 'post' ? 'Story' : 'Comment' }}
                </h3>
                <button
                    type="button"
                    @click="closeModal"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div v-if="successMessage" class="py-6 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 mb-3">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ successMessage }}</p>
            </div>

            <form v-else @submit.prevent="submitReport" class="mt-4 space-y-4">
                <p v-if="itemTitle" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    Target: <span class="font-medium text-gray-700 dark:text-gray-300">"{{ itemTitle }}"</span>
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-300 mb-2">
                        Reason for report
                    </label>
                    <div class="space-y-2">
                        <label
                            v-for="reason in reasons"
                            :key="reason.value"
                            class="flex items-center gap-3 p-2.5 rounded-lg border text-sm cursor-pointer transition"
                            :class="selectedReason === reason.value ? 'border-indigo-500 bg-indigo-50/50 dark:border-indigo-500 dark:bg-indigo-950/30 text-indigo-900 dark:text-indigo-200' : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/60'"
                        >
                            <input
                                type="radio"
                                name="reason"
                                :value="reason.value"
                                v-model="selectedReason"
                                class="text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>{{ reason.label }}</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-300 mb-1">
                        Additional details (optional)
                    </label>
                    <textarea
                        v-model="notes"
                        rows="3"
                        maxlength="1000"
                        placeholder="Provide any context that will help our moderation team..."
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    ></textarea>
                </div>

                <div v-if="errorMessage" class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-950/50 dark:text-rose-400">
                    {{ errorMessage }}
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg dark:text-gray-300 dark:hover:bg-gray-800 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-500 rounded-lg shadow-sm transition disabled:opacity-50"
                    >
                        <span v-if="isSubmitting">Submitting...</span>
                        <span v-else>Submit Report</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
