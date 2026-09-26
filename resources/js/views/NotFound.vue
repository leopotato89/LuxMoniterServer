<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="vignette bg-grid relative flex min-h-screen items-center justify-center overflow-hidden bg-page px-6 text-center">
        <div class="relative z-10">
            <p class="text-6xl font-extrabold tracking-tight text-brand">404</p>
            <p class="mt-3 text-sm text-muted">Không tìm thấy trang này.</p>
            <RouterLink
                to="/"
                class="mt-6 inline-block rounded-field border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-surface-alt"
            >
                Về trang chính
            </RouterLink>
        </div>
    </div>
</template>
