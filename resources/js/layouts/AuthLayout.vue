<script setup>
import LogoMark from '../components/ui/LogoMark.vue';
import ThemeToggle from '../components/ui/ThemeToggle.vue';
</script>

<template>
    <div class="relative min-h-screen bg-page text-ink">
        <!-- Hoạ tiết nền: lưới accent + làm mờ ở rìa (đặt trước nội dung để không bị nền body che) -->
        <div class="bg-grid vignette pointer-events-none absolute inset-0" aria-hidden="true" />

        <header class="relative mx-auto flex h-16 max-w-7xl items-center justify-between px-4 lg:px-6">
            <div class="flex items-center gap-2">
                <LogoMark />
                <span class="font-bold tracking-tight">Lux<span class="text-brand">Monitor</span></span>
            </div>

            <div class="flex items-center gap-4">
                <!-- <span class="hidden text-xs font-medium tracking-[0.2em] text-muted uppercase sm:block">
                    Giám sát năng lượng mặt trời
                </span> -->
                <ThemeToggle />
            </div>
        </header>

        <main class="relative mx-auto flex w-full max-w-7xl justify-center px-4 py-10 lg:px-6">
            <div class="w-full max-w-md">
                <div class="mb-6 text-center">
                    <h1 class="text-3xl font-extrabold tracking-tight">Lux<span class="text-brand">Monitor</span></h1>
                    <p class="mt-2 text-sm text-muted">Giám sát hệ thống điện mặt trời gia đình</p>
                </div>

                <div class="rounded-card border border-line bg-surface p-6 shadow-card sm:p-8">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
