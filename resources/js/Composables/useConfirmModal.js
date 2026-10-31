import { ref } from 'vue';

export function useConfirmModal() {
    const modalState = ref({
        show: false,
        title: '',
        message: '',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        variant: 'danger',
        type: 'confirm',
        onConfirm: () => {},
    });

    const confirm = (options) => {
        modalState.value = {
            show: true,
            title: options.title || 'Confirm Action',
            message: options.message || 'Are you sure you want to proceed?',
            confirmText: options.confirmText || 'Confirm',
            cancelText: options.cancelText || 'Cancel',
            variant: options.variant || 'danger',
            type: options.type || 'confirm',
            onConfirm: options.onConfirm || (() => {}),
        };
    };

    const alert = (options) => {
        const isString = typeof options === 'string';
        modalState.value = {
            show: true,
            title: isString ? 'Notice' : (options.title || 'Notice'),
            message: isString ? options : (options.message || ''),
            confirmText: (!isString && options.confirmText) ? options.confirmText : 'OK',
            variant: (!isString && options.variant) ? options.variant : 'info',
            type: 'alert',
            onConfirm: (!isString && options.onConfirm) ? options.onConfirm : () => {},
        };
    };

    const handleConfirm = () => {
        const action = modalState.value.onConfirm;
        modalState.value.show = false;
        if (action) {
            action();
        }
    };

    const handleClose = () => {
        modalState.value.show = false;
    };

    return {
        modalState,
        confirm,
        alert,
        handleConfirm,
        handleClose,
    };
}
