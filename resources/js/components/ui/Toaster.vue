<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();

const styles = {
    success: 'border-l-ok',
    error: 'border-l-danger',
    warning: 'border-l-brand',
};
</script>

<template>
    <div class="pointer-events-none fixed right-4 bottom-4 z-60 flex w-80 flex-col gap-2">
        <div
            v-for="item in toast.items"
            :key="item.id"
            class="pointer-events-auto flex items-start gap-3 rounded-field border border-line border-l-4 bg-surface px-4 py-3 text-sm text-ink shadow-card"
            :class="styles[item.type] ?? styles.success"
        >
            <span class="flex-1">{{ item.message }}</span>
            <button type="button" class="text-muted hover:text-ink" aria-label="Đóng" @click="toast.dismiss(item.id)">
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
