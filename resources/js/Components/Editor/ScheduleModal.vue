<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    initialDate: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

// Format current time + 5 minutes as minimum local datetime-local string
const minDateTime = computed(() => {
    const d = new Date(Date.now() + 5 * 60 * 1000);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
});

const scheduledDateTime = ref('');

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            if (props.initialDate) {
                const d = new Date(props.initialDate);
                d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                scheduledDateTime.value = d.toISOString().slice(0, 16);
            } else {
                scheduledDateTime.value = minDateTime.value;
            }
        }
    }
);

const onConfirm = () => {
    if (!scheduledDateTime.value) return;
    emit('confirm', scheduledDateTime.value);
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Schedule Story Publication
            </h3>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                Choose the date and time when your story should be automatically published and distributed to feeds.
            </p>

            <div class="mt-4">
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Publication Date & Time
                </label>
                <input
                    type="datetime-local"
                    v-model="scheduledDateTime"
                    :min="minDateTime"
                    class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 dark:text-gray-100"
                    required
                />
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-xl px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="onConfirm"
                    :disabled="!scheduledDateTime"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 transition"
                >
                    Confirm Schedule
                </button>
            </div>
        </div>
    </div>
</template>
