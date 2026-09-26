<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import EnergyDiagram from '../components/realtime/EnergyDiagram.vue';
import DashboardChart from '../components/DashboardChart.vue';
import DashboardKpis from '../components/DashboardKpis.vue';
import DashboardEnergyChart from '../components/DashboardEnergyChart.vue';
import HistoryChart from '../components/HistoryChart.vue';
import DeviceSettings from '../components/settings/DeviceSettings.vue';
import Button from '../components/ui/Button.vue';
import api, { firstError } from '../lib/api';
import { useToastStore } from '../stores/toast';
import { computed } from 'vue';

const route = useRoute();
const toast = useToastStore();

const device = ref(null);
const loading = ref(true);
const activeTab = ref('realtime'); // Mặc định mở tab realtime trước

const getTodayString = () => {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
};

const selectedDate = ref(getTodayString());

const chartParams = computed(() => {
    // Gửi ngày chính xác (00:00 -> 23:59 giờ địa phương)
    const start = new Date(selectedDate.value + 'T00:00:00');
    const stop = new Date(selectedDate.value + 'T23:59:59');
    return {
        start: start.toISOString(),
        stop: stop.toISOString(),
        window: '15m' // Cửa sổ lấy mẫu 15 phút
    };
});

function prevDate() {
    const d = new Date(selectedDate.value);
    d.setDate(d.getDate() - 1);
    selectedDate.value = getTodayStringForDate(d);
}

function nextDate() {
    const d = new Date(selectedDate.value);
    d.setDate(d.getDate() + 1);
    selectedDate.value = getTodayStringForDate(d);
}

function getTodayStringForDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

onMounted(async () => {
    try {
        const { data } = await api.get(`/devices/${route.params.serial}`);
        device.value = data.data;
    } catch (e) {
        toast.error(firstError(e));
    } finally {
        loading.value = false;
    }
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <RouterLink
                :to="{ name: 'devices.index' }"
                class="inline-flex items-center justify-center p-1.5 -ml-1.5 rounded-full text-muted transition hover:text-brand hover:bg-black/5 dark:hover:bg-white/5"
                title="Danh sách thiết bị"
            >
                <ArrowLeftIcon class="h-5 w-5" stroke-width="2" />
            </RouterLink>

            <div v-if="loading" class="text-sm text-muted">Đang tải…</div>
            <h1 v-else-if="device" class="text-xl font-bold tracking-tight text-ink m-0 ml-1">
                {{ device.name || device.serial }}
            </h1>
        </div>

        <template v-if="device">
            <div class="grid gap-4 sm:grid-cols-24 min-w-0 w-full">

                <div class="x-rounded-card p-0 sm:col-span-24 lg:col-span-16 flex flex-col">
                    <div class="border-b border-line px-4 pt-4 flex flex-wrap justify-between items-center gap-4">
                        <div class="flex gap-6 overflow-x-auto whitespace-nowrap hide-scrollbar">
                            <button 
                                @click="activeTab = 'realtime'" 
                                class="pb-3 text-sm font-medium transition-colors border-b-2 outline-none"
                                :class="activeTab === 'realtime' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink hover:border-line-strong'">
                                Hiện tại
                            </button>
                            <button 
                                @click="activeTab = 'dashboard'" 
                                class="pb-3 text-sm font-medium transition-colors border-b-2 outline-none"
                                :class="activeTab === 'dashboard' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink hover:border-line-strong'">
                                Lịch sử
                            </button>
                            <button 
                                @click="activeTab = 'energy'" 
                                class="pb-3 text-sm font-medium transition-colors border-b-2 outline-none"
                                :class="activeTab === 'energy' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink hover:border-line-strong'">
                                Thống kê
                            </button>
                            <button 
                                @click="activeTab = 'settings'" 
                                class="pb-3 text-sm font-medium transition-colors border-b-2 outline-none"
                                :class="activeTab === 'settings' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink hover:border-line-strong'">
                                Cài đặt
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 p-1 xl:p-4">
                        <div v-if="activeTab === 'realtime'">
                            <EnergyDiagram :serial="device.serial" />
                        </div>
                        <div v-else-if="activeTab === 'dashboard'" class="px-2 pb-2">
                            <div class="mb-4 flex items-center justify-end gap-2">
                                <button 
                                    @click="prevDate" 
                                    class="p-1 rounded bg-surface hover:bg-line border border-line text-muted hover:text-ink transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                </button>
                                <input 
                                    type="date" 
                                    :max="getTodayString()"
                                    v-model="selectedDate" 
                                    class="rounded bg-surface border border-line px-3 py-1 text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand outline-none transition"
                                >
                                 <button 
                                    @click="nextDate" 
                                    :disabled="selectedDate >= getTodayString()"
                                    class="p-1 rounded bg-surface hover:bg-line border border-line text-muted hover:text-ink transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </button>
                            </div>
                            <DashboardKpis :serial="device.serial" :date="selectedDate" />
                            <!-- Chỉnh chiều cao DashboardChart giãn ra 1 chút ở trong DashboardChart.vue: đổi h-[400px] thành h-[450px] hoặc tuỳ chỉnh -->
                            <DashboardChart :serial="device.serial" :params="chartParams" />
                        </div>
                        <div v-else-if="activeTab === 'energy'" class="px-2 pb-2">
                            <DashboardEnergyChart :serial="device.serial" />
                        </div>
                        <div v-else-if="activeTab === 'settings'" class="pb-2">
                            <DeviceSettings :serial="device.serial" />
                        </div>
                    </div>
                </div>

                                <div class="hidden lg:block sm:col-span-24 lg:col-span-8 py-2">
                    <!-- <div class="x-rounded-card"> -->
                    <dl class="grid text-right gap-x-10 gap-y-1 sm:grid-cols-1">
                        <div class="flex gap-7">
                            <dt class="w-32 shrink-0 text-sm text-muted">Serial</dt>
                            <dd class="text-sm font-semibold text-ink">{{ device.serial }}</dd>
                        </div>
                        <div class="flex gap-7">
                            <dt class="w-32 shrink-0 text-sm text-muted">Tên thiết bị</dt>
                            <dd class="text-sm text-ink font-semibold ">{{ device.name || '—' }}</dd>
                        </div>
                        <div class="flex gap-7">
                            <dt class="w-32 shrink-0 text-sm text-muted">Chủ sở hữu</dt>
                            <dd class="text-sm text-ink">{{ device.owner?.name ?? 'Chưa gắn' }}</dd>
                        </div>

                         <div class="flex gap-7">
                            <dt class="w-32 shrink-0 text-sm text-muted">Tạo lúc</dt>
                            <dd class="text-sm text-ink">{{ formatDate(device.created_at) }}</dd>
                        </div>
                        <!-- <hr class="my-4 border-gray-400"> -->
                        <!-- 2 dòng trạng thái Lỗi -->
                        <div class="flex gap-7" v-if="device.fault_code">
                            <dt class="w-32 shrink-0 text-sm text-muted">Lỗi</dt>
                            <dd class="text-sm font-semibold" :class="device.fault_code ? 'text-danger' : 'text-ok'">
                                {{ device.fault_code || 'Bình thường' }}
                            </dd>
                        </div>
                        <div class="flex gap-7" v-if="device.fault_message">
                            <dt class="w-32 shrink-0 text-sm text-muted">Thông báo lỗi</dt>
                            <dd class="text-sm text-ink truncate" :title="device.fault_message || 'Không có'">
                                {{ device.fault_message || '—' }}
                            </dd>
                        </div>

                       
                    </dl>
                    <!-- </div> -->
                </div>
            </div>

            
            <!-- <p class="rounded-card border border-dashed border-line-strong bg-surface/60 p-10 text-center text-sm text-muted">
                Biểu đồ lịch sử sẽ được dựng ở bước tiếp theo.
            </p> -->
        </template>
    </div>
</template>
