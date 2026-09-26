<script setup>
import { MoonIcon, SunIcon } from '@heroicons/vue/24/outline';
import { useThemeStore } from '../../stores/theme';

const theme = useThemeStore();
</script>

<template>
    <button
        type="button"
        class="grid h-9 w-9 place-items-center rounded-field border border-line text-muted transition hover:bg-brand-soft hover:text-brand-strong"
        :aria-label="theme.isDark ? 'Chuyển sang chế độ sáng' : 'Chuyển sang chế độ tối'"
        :title="theme.isDark ? 'Chế độ sáng' : 'Chế độ tối'"
        @click="theme.toggle()"
    >
        <!-- Mặt trăng khi đang tối, mặt trời khi đang sáng -->
        <MoonIcon v-if="theme.isDark" class="h-5 w-5" />
        <SunIcon v-else class="h-5 w-5" />
    </button>
</template>
