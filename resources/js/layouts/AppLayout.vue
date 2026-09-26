<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { UsersIcon, ArrowRightOnRectangleIcon, ChevronDownIcon, KeyIcon } from '@heroicons/vue/24/outline';
import LogoMark from '../components/ui/LogoMark.vue';
import ThemeToggle from '../components/ui/ThemeToggle.vue';
import Toaster from '../components/ui/Toaster.vue';
import ChangePasswordModal from '../components/auth/ChangePasswordModal.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const userMenuOpen = ref(false);
const changePasswordOpen = ref(false);

async function logout() {
    await auth.logout();
    await router.replace('/login');
}

const userMenuRef = ref(null);

function handleClickOutside(event) {
    if (userMenuOpen.value && userMenuRef.value && !userMenuRef.value.contains(event.target)) {
        userMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative min-h-screen bg-page text-ink">
        <!-- Hoạ tiết nền: lưới accent + làm mờ ở rìa (đặt trước nội dung để không bị nền body che) -->
        <div class="bg-grid vignette pointer-events-none absolute inset-0" aria-hidden="true" />

        <header class="glass-bar sticky top-0 z-20 border-b border-line">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-2 lg:px-4">
                <RouterLink :to="{ name: 'devices.index' }" class="flex shrink-0 items-center gap-2">
                    <LogoMark />
                    <span class="font-bold tracking-tight">LuxMonitor</span>
                </RouterLink>

                <div class="ml-auto flex items-center gap-2">
                    <ThemeToggle />

                    <div class="relative" ref="userMenuRef">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-field p-1 pr-2 text-left transition hover:bg-brand-soft focus:outline-none"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <!-- Avatar chữ -->
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-bold text-brand-strong">
                                {{ auth.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </div>
                            
                            <!-- Name & Email (ẩn trên mobile để gọn, hiện trên md) -->
                            <div class="hidden flex-col md:flex">
                                <span class="text-sm font-semibold text-ink leading-tight flex items-center gap-1.5">
                                    {{ auth.user?.name }}
                                    <span v-if="auth.isAdmin" class="rounded bg-brand-soft px-1 py-0.5 text-[9px] uppercase font-bold text-brand-strong tracking-wide">Admin</span>
                                </span>
                                <span class="text-xs text-muted leading-tight mt-0.5">{{ auth.user?.email || auth.user?.username }}</span>
                            </div>

                            <ChevronDownIcon class="ml-1 h-4 w-4 text-muted transition" :class="userMenuOpen ? 'rotate-180' : ''" />
                        </button>


                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 top-full z-50 mt-1 w-56 rounded-lg border border-line bg-surface p-1 shadow-lg"
                        >
                            <!-- Info for mobile view -->
                            <div class="px-3 py-2 border-b border-line mb-1 md:hidden">
                                <span class="block text-sm font-semibold text-ink truncate flex items-center gap-1.5">
                                    {{ auth.user?.name }}
                                    <span v-if="auth.isAdmin" class="rounded bg-brand-soft px-1 py-0.5 text-[9px] uppercase font-bold text-brand-strong tracking-wide">Admin</span>
                                </span>
                                <span class="block text-xs text-muted truncate mt-0.5">{{ auth.user?.email || auth.user?.username }}</span>
                            </div>

                            <RouterLink
                                v-if="auth.isAdmin"
                                :to="{ name: 'admin.users' }"
                                class="flex w-full items-center gap-2.5 rounded-field px-3 py-2 text-left text-sm font-medium text-muted transition hover:bg-brand-soft hover:text-brand-strong"
                                active-class="!bg-brand-soft !text-brand-strong"
                                @click="userMenuOpen = false"
                            >
                                <UsersIcon class="h-4 w-4" />
                                Người dùng
                            </RouterLink>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 rounded-field px-3 py-2 text-left text-sm font-medium text-muted transition hover:bg-brand-soft hover:text-brand-strong"
                                @click="changePasswordOpen = true; userMenuOpen = false;"
                            >
                                <KeyIcon class="h-4 w-4" />
                                Đổi mật khẩu
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2.5 rounded-field px-3 py-2 text-left text-sm font-medium text-muted transition hover:bg-danger-soft hover:text-danger-strong"
                                @click="logout(); userMenuOpen = false;"
                            >
                                <ArrowRightOnRectangleIcon class="h-4 w-4" />
                                Đăng xuất
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="relative mx-auto w-full max-w-7xl p-2 lg:p-4">
            <RouterView />
        </main>

        <Toaster />
        
        <ChangePasswordModal v-model="changePasswordOpen" />
    </div>
</template>
