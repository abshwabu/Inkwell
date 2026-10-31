<script setup>
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Confirm Action',
    },
    message: {
        type: String,
        default: 'Are you sure you want to proceed?',
    },
    confirmText: {
        type: String,
        default: 'Confirm',
    },
    cancelText: {
        type: String,
        default: 'Cancel',
    },
    variant: {
        type: String,
        default: 'danger', // 'danger' | 'warning' | 'info' | 'primary'
    },
    type: {
        type: String,
        default: 'confirm', // 'confirm' | 'alert'
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'confirm']);

const handleConfirm = () => {
    emit('confirm');
};

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="handleClose">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <!-- Icon based on variant -->
                <div
                    :class="[
                        variant === 'danger' ? 'bg-red-100 text-red-600 dark:bg-red-950/80 dark:text-red-400' :
                        variant === 'warning' ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/80 dark:text-amber-400' :
                        'bg-indigo-100 text-indigo-600 dark:bg-indigo-950/80 dark:text-indigo-400',
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl'
                    ]"
                >
                    <!-- Danger icon -->
                    <svg v-if="variant === 'danger'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <!-- Warning icon -->
                    <svg v-else-if="variant === 'warning'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <!-- Info icon -->
                    <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    <h3 class="font-serif text-lg font-bold text-gray-900 dark:text-white">
                        {{ title }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                        {{ message }}
                    </p>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="mt-6 flex justify-end items-center gap-3">
                <SecondaryButton
                    v-if="type === 'confirm'"
                    type="button"
                    @click="handleClose"
                    :disabled="processing"
                >
                    {{ cancelText }}
                </SecondaryButton>

                <DangerButton
                    v-if="variant === 'danger'"
                    type="button"
                    @click="handleConfirm"
                    :disabled="processing"
                    class="disabled:opacity-50"
                >
                    {{ confirmText }}
                </DangerButton>

                <button
                    v-else-if="variant === 'warning'"
                    type="button"
                    @click="handleConfirm"
                    :disabled="processing"
                    class="inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-xs transition duration-150 ease-in-out hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-800"
                >
                    {{ confirmText }}
                </button>

                <PrimaryButton
                    v-else
                    type="button"
                    @click="handleConfirm"
                    :disabled="processing"
                    class="disabled:opacity-50"
                >
                    {{ confirmText }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
