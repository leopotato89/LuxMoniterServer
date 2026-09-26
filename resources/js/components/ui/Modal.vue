<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, default: '' },
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink/40 backdrop-blur-[2px]" @click="emit('close')" />

            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-card border border-line bg-surface shadow-card">
                <header class="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
                    <h3 class="font-semibold text-ink">{{ title }}</h3>
                    <button
                        type="button"
                        class="rounded-field p-1 text-muted transition hover:bg-surface-alt hover:text-ink"
                        aria-label="Đóng"
                        @click="emit('close')"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </header>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <slot />
                </div>

                <footer class="flex items-center justify-end gap-2 border-t border-line px-5 py-3">
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
