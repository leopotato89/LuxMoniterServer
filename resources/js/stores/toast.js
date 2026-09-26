import { defineStore } from 'pinia';
import { ref } from 'vue';

let nextId = 1;

/**
 * Thông báo nổi (thay Filament\Notifications).
 */
export const useToastStore = defineStore('toast', () => {
    const items = ref([]);

    function push(message, type = 'success') {
        const id = nextId++;

        items.value.push({ id, message, type });
        setTimeout(() => dismiss(id), 4500);
    }

    function dismiss(id) {
        items.value = items.value.filter((item) => item.id !== id);
    }

    return {
        items,
        push,
        dismiss,
        success: (message) => push(message, 'success'),
        error: (message) => push(message, 'error'),
        warning: (message) => push(message, 'warning'),
    };
});
