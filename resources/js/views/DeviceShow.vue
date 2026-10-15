<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    BoltIcon,
    ArrowPathIcon,
    CpuChipIcon,
    ChevronDownIcon,
    PencilSquareIcon,
    TrashIcon,
    CheckCircleIcon,
    PlusIcon,
    StarIcon,
    BookOpenIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid';
import LogoMark from '../components/ui/LogoMark.vue';
import EnergyDiagram from '../components/realtime/EnergyDiagram.vue';
import DashboardChart from '../components/DashboardChart.vue';
import DashboardKpis from '../components/DashboardKpis.vue';
import DashboardEnergyChart from '../components/DashboardEnergyChart.vue';
import HistoryChart from '../components/HistoryChart.vue';
import DeviceSettings from '../components/settings/DeviceSettings.vue';
import Button from '../components/ui/Button.vue';
import Modal from '../components/ui/Modal.vue';
import api, { firstError } from '../lib/api';
import { useAuthStore } from '../stores/auth';
import { useDevicesStore } from '../stores/devices';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const devicesStore = useDevicesStore();
const toast = useToastStore();

const device = ref(null);
const loading = ref(true);
const activeTab = ref('realtime'); // Mặc định mở tab realtime trước

// Dropdown chọn thiết bị như bên mobile
const selectorRef = ref(null);
const dropdownOpen = ref(false);

// State ghim thiết bị
const pinnedSerial = ref(localStorage.getItem('luxmonitor_pinned_device') || '');

function togglePin(d, event) {
    if (event) event.stopPropagation();
    if (!d) return;
    if (pinnedSerial.value === d.serial) {
        pinnedSerial.value = '';
        localStorage.removeItem('luxmonitor_pinned_device');
        toast.info(`Đã bỏ ghim ${d.name || d.serial}`);
    } else {
        pinnedSerial.value = d.serial;
        localStorage.setItem('luxmonitor_pinned_device', d.serial);
        toast.success(`Đã ghim ${d.name || d.serial} làm thiết bị mặc định`);
    }
}

// Modal Thêm thiết bị (User: Claim)
const claimOpen = ref(false);
const claimForm = ref({ serial: '', device_code: '' });
const claimSubmitting = ref(false);

async function submitClaim() {
    claimSubmitting.value = true;
    try {
        const targetSerial = claimForm.value.serial.trim();
        const ok = await devicesStore.claim(targetSerial, claimForm.value.device_code.trim());
        if (ok) {
            claimOpen.value = false;
            claimForm.value = { serial: '', device_code: '' };
            await devicesStore.fetch();
            router.push({ name: 'devices.show', params: { serial: targetSerial } });
        }
    } finally {
        claimSubmitting.value = false;
    }
}

// Modal Tạo thiết bị (Admin: Create)
const createOpen = ref(false);
const createForm = ref({ serial: '', name: '', owner_id: '' });
const createSubmitting = ref(false);

async function submitCreate() {
    createSubmitting.value = true;
    try {
        const targetSerial = createForm.value.serial.trim();
        const payload = {
            serial: targetSerial,
            name: createForm.value.name.trim() || null,
        };
        if (createForm.value.owner_id !== undefined && createForm.value.owner_id !== '') {
            payload.owner_id = createForm.value.owner_id;
        }
        const ok = await devicesStore.create(payload);
        if (ok) {
            createOpen.value = false;
            createForm.value = { serial: '', name: '', owner_id: '' };
            await devicesStore.fetch();
            router.push({ name: 'devices.show', params: { serial: targetSerial } });
        }
    } finally {
        createSubmitting.value = false;
    }
}

function openAddModal() {
    dropdownOpen.value = false;
    if (auth.isAdmin) {
        createOpen.value = true;
    } else {
        claimOpen.value = true;
    }
}

// State sửa thiết bị
const editTarget = ref(null);
const editForm = ref({ name: '', owner_id: '' });
const editSubmitting = ref(false);

// State xoá thiết bị
const deleteTarget = ref(null);
const deleteSubmitting = ref(false);

// Danh sách user (cho Admin khi phân bổ thiết bị)
const users = ref([]);

// Firmware & OTA state
const manifest = ref(null);
const otaModalOpen = ref(false);
const otaBoard = ref('devkit');
const otaSubmitting = ref(false);

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
        window: '5m', // Cửa sổ lấy mẫu 5 phút
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

// Danh sách thiết bị kết hợp store và thiết bị hiện tại
const devicesList = computed(() => {
    const list = [...devicesStore.items];
    if (device.value && !list.some((d) => d.serial === device.value.serial)) {
        list.unshift(device.value);
    }
    return list;
});

function handleClickOutside(event) {
    if (dropdownOpen.value && selectorRef.value && !selectorRef.value.contains(event.target)) {
        dropdownOpen.value = false;
    }
}

async function fetchUsers() {
    if (!auth.isAdmin) return;
    try {
        const { data } = await api.get('/admin/users');
        users.value = data.data;
    } catch {
        // silent
    }
}

async function loadDevice(serial) {
    loading.value = true;
    try {
        const { data } = await api.get(`/devices/${serial}`);
        device.value = data.data;
    } catch (e) {
        toast.error(firstError(e));
    } finally {
        loading.value = false;
    }
}

async function resolveAndLoadDevice() {
    loading.value = true;
    try {
        await devicesStore.fetch();

        let targetSerial = route.params.serial;

        // Nếu không có serial trên URL (trang chủ /), chọn thiết bị ghim hoặc thiết bị đầu tiên
        if (!targetSerial) {
            if (pinnedSerial.value && devicesStore.items.some((d) => d.serial === pinnedSerial.value)) {
                targetSerial = pinnedSerial.value;
            } else if (devicesStore.items.length > 0) {
                targetSerial = devicesStore.items[0].serial;
            }
        }

        if (targetSerial) {
            await loadDevice(targetSerial);
        } else {
            device.value = null;
        }
    } finally {
        loading.value = false;
    }
}

function selectDevice(d) {
    dropdownOpen.value = false;
    if (d.serial !== device.value?.serial) {
        router.push({ name: 'devices.show', params: { serial: d.serial } });
    }
}

function openEdit(d) {
    dropdownOpen.value = false;
    editTarget.value = d;
    editForm.value = {
        name: d.name ?? '',
        owner_id: d.owner?.id ?? '',
    };
}

async function submitEdit() {
    if (!editTarget.value) return;
    editSubmitting.value = true;
    try {
        const payload = { name: editForm.value.name || null };
        if (auth.isAdmin) {
            payload.owner_id = editForm.value.owner_id || null;
        }
        const ok = await devicesStore.update(editTarget.value.serial, payload);
        if (ok) {
            if (device.value && device.value.serial === editTarget.value.serial) {
                device.value.name = editForm.value.name;
            }
            editTarget.value = null;
        }
    } finally {
        editSubmitting.value = false;
    }
}

function openDelete(d) {
    dropdownOpen.value = false;
    deleteTarget.value = d;
}

async function confirmDelete() {
    if (!deleteTarget.value) return;
    deleteSubmitting.value = true;
    try {
        const targetSerial = deleteTarget.value.serial;
        const isCurrent = device.value && device.value.serial === targetSerial;
        const ok = auth.isAdmin
            ? await devicesStore.destroy(targetSerial)
            : await devicesStore.unclaim(targetSerial);

        if (ok) {
            if (pinnedSerial.value === targetSerial) {
                pinnedSerial.value = '';
                localStorage.removeItem('luxmonitor_pinned_device');
            }
            deleteTarget.value = null;
            if (editTarget.value?.serial === targetSerial) {
                editTarget.value = null;
            }
            await devicesStore.fetch();
            if (isCurrent) {
                if (devicesStore.items.length > 0) {
                    const next = devicesStore.items.find((d) => d.serial !== targetSerial) || devicesStore.items[0];
                    router.push({ name: 'devices.show', params: { serial: next.serial } });
                } else {
                    device.value = null;
                    router.push({ name: 'devices.index' });
                }
            }
        }
    } finally {
        deleteSubmitting.value = false;
    }
}

watch(
    () => route.params.serial,
    async (newSerial) => {
        if (newSerial) {
            if (!device.value || device.value.serial !== newSerial) {
                await loadDevice(newSerial);
            }
        } else {
            await resolveAndLoadDevice();
        }
    },
);

onMounted(async () => {
    document.addEventListener('click', handleClickOutside);
    devicesStore.filters.per_page = 50;
    fetchUsers();
    await resolveAndLoadDevice();

    try {
        const { data: mf } = await api.get('/firmware/manifest');
        manifest.value = mf;
    } catch {
        // manifest load is optional
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

async function triggerOta() {
    otaSubmitting.value = true;
    try {
        const { data } = await api.post(`/devices/${device.value.serial}/ota`, {
            board: otaBoard.value,
        });
        toast.success(data.message || 'Đã gửi lệnh nâng cấp OTA thành công!');
        otaModalOpen.value = false;
    } catch (e) {
        toast.error(firstError(e));
    } finally {
        otaSubmitting.value = false;
    }
}

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
        <!-- Khi không có thiết bị nào trong tài khoản -->
        <div v-if="!loading && !device" class="x-rounded-card p-8 sm:p-12 text-center space-y-4 my-8 max-w-lg mx-auto">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-soft text-brand shadow-sm">
                <CpuChipIcon class="h-8 w-8" />
            </div>
            <div class="space-y-1">
                <h3 class="text-lg font-bold text-ink">Chưa có thiết bị nào</h3>
                <p class="text-sm text-muted max-w-sm mx-auto">
                    {{ auth.isAdmin ? 'Hãy tạo thiết bị giám sát mới để bắt đầu theo dõi.' : 'Hãy thêm thiết bị giám sát biến tần vào tài khoản để theo dõi.' }}
                </p>
            </div>
            <div class="flex flex-col items-center gap-3 pt-1">
                <Button size="md" @click="openAddModal">
                    <PlusIcon class="h-4 w-4" />
                    <span>{{ auth.isAdmin ? 'Tạo thiết bị ngay' : 'Thêm thiết bị ngay' }}</span>
                </Button>

                <RouterLink
                    to="/guide"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-brand transition py-1 px-3 rounded-lg hover:bg-brand-soft/40"
                >
                    <BookOpenIcon class="h-4 w-4 text-brand" />
                    <span>Xem hướng dẫn cài đặt & kết nối biến tần</span>
                </RouterLink>
            </div>
        </div>

        <div v-else class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <div v-if="loading && !device" class="text-sm text-muted">Đang tải thiết bị…</div>

                <!-- Select box chọn thiết bị tương tự Mobile -->
                <div v-else-if="device" class="relative" ref="selectorRef">
                    <button
                        type="button"
                        @click="dropdownOpen = !dropdownOpen"
                        class="group flex items-center gap-2.5 rounded-xl border border-brand/35 bg-brand-soft/20 px-3 py-1.5 shadow-sm transition hover:border-brand hover:bg-brand-soft/35 focus:outline-none"
                        :class="dropdownOpen ? 'border-brand ring-2 ring-brand/25 bg-brand-soft/35' : ''"
                    >
                        <LogoMark class="h-6 w-6 shrink-0 transition group-hover:scale-105" />

                        <div class="flex flex-col text-left min-w-0">
                            <span class="text-sm font-bold text-ink group-hover:text-brand transition leading-tight truncate max-w-[200px] sm:max-w-[320px]">
                                {{ device.name || device.serial }}
                            </span>
                            <span class="text-[10px] font-mono text-muted leading-tight mt-0.5 truncate">
                                SN: {{ device.serial }}
                            </span>
                        </div>

                        <ChevronDownIcon
                            class="ml-1 h-4 w-4 text-muted group-hover:text-brand transition duration-200"
                            :class="dropdownOpen ? 'rotate-180 text-brand' : ''"
                        />
                    </button>

                    <!-- Dropdown Menu Danh sách thiết bị -->
                    <div
                        v-if="dropdownOpen"
                        class="absolute left-0 top-full z-50 mt-1.5 w-88 max-w-[92vw] rounded-xl border border-line bg-surface p-1.5 shadow-xl shadow-black/15 backdrop-blur"
                    >
                        <!-- Header dropdown: Tiêu đề + Nút Thêm thiết bị -->
                        <div class="px-3 py-2 border-b border-line flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted">Chọn biến tần</span>
                            <button
                                type="button"
                                @click="openAddModal"
                                class="inline-flex items-center gap-1 rounded-lg bg-brand px-2.5 py-1 text-xs font-bold text-white shadow-sm hover:brightness-110 active:scale-95 transition"
                                :title="auth.isAdmin ? 'Tạo thiết bị mới' : 'Thêm thiết bị giám sát'"
                            >
                                <PlusIcon class="w-3.5 h-3.5" />
                                <span>Thêm</span>
                            </button>
                        </div>

                        <div class="max-h-72 overflow-y-auto py-1 space-y-1">
                            <div
                                v-for="d in devicesList"
                                :key="d.serial"
                                @click="selectDevice(d)"
                                class="group/item flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left transition cursor-pointer"
                                :class="d.serial === device.serial ? 'bg-brand-soft/70 text-brand font-semibold' : 'hover:bg-black/5 dark:hover:bg-white/5 text-ink'"
                            >
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <!-- Trạng thái online -->
                                    <span
                                        class="h-2 w-2 rounded-full shrink-0"
                                        :class="d.online ? 'bg-ok shadow-sm shadow-ok/50' : 'bg-muted/40'"
                                        :title="d.online ? 'Đang online' : 'Ngoại tuyến'"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold truncate block">
                                                {{ d.name || d.serial }}
                                            </span>
                                            <CheckCircleIcon
                                                v-if="d.serial === device.serial"
                                                class="w-3.5 h-3.5 text-brand shrink-0"
                                            />
                                        </div>
                                        <span class="text-[10px] font-mono text-muted block truncate">
                                            SN: {{ d.serial }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Nút hành động: Ghim / Sửa / Xóa -->
                                <div class="flex items-center gap-0.5 shrink-0" @click.stop>
                                    <button
                                        type="button"
                                        @click="togglePin(d, $event)"
                                        class="p-1.5 rounded-lg transition"
                                        :class="pinnedSerial === d.serial ? 'text-amber-500 bg-amber-500/10' : 'text-muted hover:text-amber-500 hover:bg-amber-500/10'"
                                        :title="pinnedSerial === d.serial ? 'Đã ghim (Click để bỏ ghim)' : 'Ghim làm thiết bị mặc định'"
                                    >
                                        <StarIconSolid v-if="pinnedSerial === d.serial" class="w-4 h-4 text-amber-500" />
                                        <StarIcon v-else class="w-4 h-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="openEdit(d)"
                                        class="p-1.5 rounded-lg text-muted hover:text-brand hover:bg-brand-soft transition"
                                        title="Sửa thiết bị"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="openDelete(d)"
                                        class="p-1.5 rounded-lg text-muted hover:text-danger hover:bg-danger-soft transition"
                                        title="Xóa thiết bị"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Footer: Chỉ hiển thị cho Admin liên kết quản lý tất cả thiết bị -->
                        <div v-if="auth.isAdmin" class="border-t border-line pt-1.5 mt-1 flex items-center justify-between px-2">
                            <RouterLink
                                :to="{ name: 'admin.devices' }"
                                class="text-[11px] font-semibold text-brand hover:underline flex items-center gap-1 py-1"
                            >
                                Quản lý tất cả thiết bị (Admin) →
                            </RouterLink>
                        </div>
                    </div>
                </div>

                <!-- Nút Ghim thiết bị hiện tại -->
                <button
                    v-if="device"
                    type="button"
                    @click="togglePin(device)"
                    class="flex items-center gap-1.5 rounded-xl border px-2.5 py-1.5 text-xs font-semibold shadow-sm transition"
                    :class="pinnedSerial === device.serial
                        ? 'border-amber-400/50 bg-amber-500/10 text-amber-500'
                        : 'border-line bg-surface text-muted hover:text-amber-500 hover:border-amber-400/40'"
                    :title="pinnedSerial === device.serial ? 'Đã ghim làm thiết bị mặc định (Click để bỏ ghim)' : 'Ghim thiết bị này làm mặc định'"
                >
                    <StarIconSolid v-if="pinnedSerial === device.serial" class="w-4 h-4 text-amber-500" />
                    <StarIcon v-else class="w-4 h-4" />
                    <span class="hidden sm:inline">{{ pinnedSerial === device.serial ? 'Đã ghim' : 'Ghim' }}</span>
                </button>
            </div>
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
                            <EnergyDiagram :key="device.serial" :serial="device.serial" />
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
                            <DashboardKpis :key="device.serial + selectedDate" :serial="device.serial" :date="selectedDate" />
                            <!-- Chỉnh chiều cao DashboardChart giãn ra 1 chút ở trong DashboardChart.vue: đổi h-[400px] thành h-[450px] hoặc tuỳ chỉnh -->
                            <DashboardChart :key="device.serial" :serial="device.serial" :params="chartParams" />
                        </div>
                        <div v-else-if="activeTab === 'energy'" class="px-2 pb-2">
                            <DashboardEnergyChart :key="device.serial" :serial="device.serial" />
                        </div>
                        <div v-else-if="activeTab === 'settings'" class="pb-2">
                            <DeviceSettings :key="device.serial" :serial="device.serial" />
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-24 lg:col-span-8 flex">

                    <!-- Cột thông tin thiết bị & Tác giả -->
                    <div class="text-left w-full space-y-4">
                        <!-- Tạm thời ẩn Card Phần cứng & Firmware OTA -->
                        <!--
                        <div class="x-rounded-card mt-1 p-3.5 border border-line-subtle rounded-md shadow-sm space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold text-ink flex items-center gap-1.5">
                                    <CpuChipIcon class="w-4 h-4 text-brand" />
                                    PHẦN CỨNG & FIRMWARE
                                </p>
                                <span v-if="manifest" class="rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-bold text-brand-strong border border-brand/20">
                                    v{{ manifest.version }}
                                </span>
                            </div>

                            <div class="text-[13px] text-muted space-y-2">
                                <div class="flex items-center justify-between">
                                    <span>Số Serial:</span>
                                    <span class="font-mono font-bold text-ink">{{ device.serial }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Trạng thái:</span>
                                    <span class="font-semibold text-emerald-500 flex items-center gap-1">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Sẵn sàng nhận lệnh
                                    </span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-line flex flex-col gap-2">
                                <RouterLink
                                    to="/flash"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-field bg-brand px-3 py-2 text-xs font-bold text-white shadow-sm hover:brightness-110 transition active:scale-[0.99]"
                                >
                                    <BoltIcon class="w-3.5 h-3.5" />
                                    Nạp Cáp USB (Web Flasher)
                                </RouterLink>
                            </div>
                        </div>
                        -->

                        <!-- Thông tin tác giả & Donate -->
                        <div class="x-rounded-card p-3 border border-line-subtle rounded-md shadow-sm">
                            <p class="text-xs font-bold text-ink mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                THÔNG TIN LIÊN HỆ
                            </p>
                            <div class="text-[13px] text-muted space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-20 shrink-0">Tác giả:</span>
                                    <span class="font-semibold text-brand">Dương Xuân Giang</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-20 shrink-0">SĐT / Zalo:</span>
                                    <a
                                        href="zalo://conversation?phone=0384162574"
                                        class="font-semibold text-ink hover:text-[#0068FF] hover:underline flex items-center gap-1.5 transition-colors cursor-pointer"
                                        title="Bấm để mở ứng dụng Zalo PC"
                                    >
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/9/91/Icon_of_Zalo.svg" class="w-4 h-4 object-contain" alt="Zalo" />
                                        0384 162 574
                                    </a>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-20 shrink-0">Facebook:</span>
                                    <a
                                        href="https://www.facebook.com/leopotato89"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-semibold text-[#1877F2] hover:underline flex items-center gap-1.5"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                        facebook.com/leopotato89
                                    </a>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-20 shrink-0">Email:</span>
                                    <a href="mailto:giangdx.tn@gmail.com" class="font-semibold text-ink hover:text-brand transition-colors flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        giangdx.tn@gmail.com
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="x-rounded-card mt-4 p-3 border border-line-strong border-dashed rounded-md shadow-sm">
                            <p class="text-xs font-bold text-brand mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                                DONATE ỦNG HỘ TÁC GIẢ
                            </p>
                            <div class="flex items-center gap-3 mt-1">
                                <div class="shrink-0">
                                    <img src="https://img.vietqr.io/image/tpb-99325101989-qr_only.png" alt="QR Donate" class="w-24 h-24 rounded-md border border-line-subtle shadow-sm" />
                                </div>
                                <div class="text-[13px] text-muted space-y-2 w-full">
                                    <div class="flex items-center gap-2">
                                        <span class="w-18 shrink-0">Ngân hàng:</span>
                                        <span class="font-semibold text-ink flex items-center gap-1.5">
                                            <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/02/Icon-TPBank.png" class="w-4 h-4 object-contain" alt="TPBank" />
                                            TPBank
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-18 shrink-0">STK:</span>
                                        <span class="font-semibold text-ink">99325101989</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-18 shrink-0">Chủ TK:</span>
                                        <span class="font-semibold text-ink">DUONG XUAN GIANG</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Thông tin tác giả -->
                </div>
            </div>


            <!-- Modal Xác nhận Kích hoạt OTA từ xa -->
            <Modal :open="otaModalOpen" title="Cập nhật Firmware Từ xa (Cloud OTA)" @close="otaModalOpen = false">
                <div class="space-y-4 text-sm text-ink">
                    <p class="text-xs text-muted leading-relaxed">
                        Hệ thống sẽ gửi bản tin MQTT kích hoạt ESP32 tải gói firmware mới qua HTTPS và nâng cấp vào phân vùng an toàn mà không cần tháo mạch khỏi tủ điện.
                    </p>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-muted">Dòng bo mạch thiết bị:</label>
                        <select
                            v-model="otaBoard"
                            class="w-full rounded-lg border border-line bg-page px-3 py-2 text-xs text-ink outline-none focus:border-brand"
                        >
                            <option value="devkit">ESP32 DevKit (D0WD / 30-38 chân)</option>
                            <option value="c3">ESP32-C3 SuperMini (USB-JTAG / CDC)</option>
                        </select>
                    </div>

                    <div v-if="manifest?.boards?.[otaBoard]" class="rounded-lg bg-page p-3 border border-line space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted">Phiên bản:</span>
                            <span class="font-bold text-brand">v{{ manifest.version }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Kích thước gói:</span>
                            <span class="font-mono text-ink">{{ (manifest.boards[otaBoard].ota_size / 1024).toFixed(0) }} KB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Mã băm MD5:</span>
                            <span class="font-mono text-[11px] text-muted truncate max-w-[200px]" :title="manifest.boards[otaBoard].ota_md5">
                                {{ manifest.boards[otaBoard].ota_md5 }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg bg-amber-500/10 p-3 text-[11px] text-amber-600 dark:text-amber-400 border border-amber-500/20">
                        ⚠️ Lưu ý: Trong lúc thiết bị đang cập nhật (khoảng 30-45 giây), không ngắt nguồn điện biến tần hoặc ESP32.
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-muted hover:text-ink transition"
                            @click="otaModalOpen = false"
                            :disabled="otaSubmitting"
                        >
                            Hủy bỏ
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-1.5 text-xs font-bold text-white shadow-sm hover:brightness-110 transition disabled:opacity-50"
                            @click="triggerOta"
                            :disabled="otaSubmitting"
                        >
                            <ArrowPathIcon class="w-3.5 h-3.5" :class="otaSubmitting ? 'animate-spin' : ''" />
                            {{ otaSubmitting ? 'Đang gửi lệnh...' : 'Xác nhận Nâng cấp OTA' }}
                        </button>
                    </div>
                </template>
            </Modal>
        </template>

        <!-- Modal: thêm thiết bị giám sát (user) -->
        <Modal :open="claimOpen" title="Thêm thiết bị giám sát" @close="claimOpen = false">
            <p class="mb-4 text-sm text-muted">
                Nhập serial và mã thiết bị lấy từ trang web của ESP32 để xác minh.
            </p>

            <form id="claim-form" class="space-y-4" @submit.prevent="submitClaim">
                <div>
                    <label for="claim-serial" class="mb-1.5 block text-sm font-medium text-ink">
                        Serial thiết bị <span class="text-brand">*</span>
                    </label>
                    <input id="claim-serial" v-model="claimForm.serial" class="field" type="text" placeholder="VD: 4313800597" required>
                </div>

                <div>
                    <label for="claim-code" class="mb-1.5 block text-sm font-medium text-ink">
                        Mã thiết bị (từ ESP32) <span class="text-brand">*</span>
                    </label>
                    <input id="claim-code" v-model="claimForm.device_code" class="field" type="text" placeholder="Nhập mã thiết bị..." required>
                </div>
            </form>

            <template #footer>
                <Button variant="ghost" @click="claimOpen = false">Huỷ</Button>
                <Button type="submit" form="claim-form" :disabled="claimSubmitting">
                    {{ claimSubmitting ? 'Đang kiểm tra…' : 'Thêm thiết bị' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: tạo thiết bị (admin) -->
        <Modal :open="createOpen" title="Tạo thiết bị" @close="createOpen = false">
            <form id="create-form" class="space-y-4" @submit.prevent="submitCreate">
                <div>
                    <label for="create-serial" class="mb-1.5 block text-sm font-medium text-ink">
                        Serial <span class="text-brand">*</span>
                    </label>
                    <input id="create-serial" v-model="createForm.serial" class="field" type="text" placeholder="VD: 4313800597" required>
                </div>

                <div>
                    <label for="create-name" class="mb-1.5 block text-sm font-medium text-ink">Tên thiết bị</label>
                    <input id="create-name" v-model="createForm.name" class="field" type="text" placeholder="Nhập tên gợi nhớ...">
                </div>

                <div v-if="auth.isAdmin">
                    <label for="create-owner" class="mb-1.5 block text-sm font-medium text-ink">Chủ sở hữu</label>
                    <select id="create-owner" v-model="createForm.owner_id" class="field">
                        <option value="">Không gán</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.username }})
                        </option>
                    </select>
                </div>
            </form>

            <template #footer>
                <Button variant="ghost" @click="createOpen = false">Huỷ</Button>
                <Button type="submit" form="create-form" :disabled="createSubmitting">
                    {{ createSubmitting ? 'Đang tạo…' : 'Tạo thiết bị' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: sửa thiết bị -->
        <Modal :open="editTarget !== null" title="Sửa thiết bị" @close="editTarget = null">
            <form id="edit-device-form" class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Serial thiết bị</label>
                    <input :value="editTarget?.serial" disabled class="field bg-black/5 dark:bg-white/5 opacity-70 cursor-not-allowed font-mono text-xs" type="text">
                </div>

                <div>
                    <label for="edit-dev-name" class="mb-1.5 block text-sm font-medium text-ink">Tên thiết bị</label>
                    <input id="edit-dev-name" v-model="editForm.name" class="field" type="text" placeholder="Nhập tên gợi nhớ...">
                </div>

                <div v-if="auth.isAdmin">
                    <label for="edit-dev-owner" class="mb-1.5 block text-sm font-medium text-ink">Chủ sở hữu</label>
                    <select id="edit-dev-owner" v-model="editForm.owner_id" class="field">
                        <option value="">Không gán</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.username }})
                        </option>
                    </select>
                </div>

                <div class="pt-3 border-t border-line flex items-center justify-between">
                    <span class="text-xs text-muted">
                        {{ auth.isAdmin ? 'Xóa hoàn toàn khỏi hệ thống' : 'Hủy theo dõi thiết bị này' }}
                    </span>
                    <Button
                        type="button"
                        variant="danger"
                        size="sm"
                        @click="openDelete(editTarget)"
                    >
                        <TrashIcon class="w-3.5 h-3.5" />
                        {{ auth.isAdmin ? 'Xóa thiết bị' : 'Hủy theo dõi' }}
                    </Button>
                </div>
            </form>

            <template #footer>
                <Button variant="ghost" @click="editTarget = null">Huỷ</Button>
                <Button type="submit" form="edit-device-form" :disabled="editSubmitting">
                    {{ editSubmitting ? 'Đang lưu…' : 'Lưu' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: xác nhận xoá / hủy theo dõi thiết bị -->
        <Modal :open="deleteTarget !== null" :title="auth.isAdmin ? 'Xóa thiết bị' : 'Hủy theo dõi thiết bị'" @close="deleteTarget = null">
            <p class="text-sm text-muted">
                <template v-if="auth.isAdmin">
                    Thiết bị <strong class="text-ink font-mono">{{ deleteTarget?.serial }}</strong> ({{ deleteTarget?.name || 'Không có tên' }}) sẽ bị xóa hoàn toàn khỏi hệ thống. Hành động này không thể hoàn tác.
                </template>
                <template v-else>
                    Bạn có chắc chắn muốn xóa thiết bị <strong class="text-ink font-mono">{{ deleteTarget?.serial }}</strong> khỏi danh sách quản lý của bạn không?
                </template>
            </p>

            <template #footer>
                <Button variant="ghost" @click="deleteTarget = null">Huỷ</Button>
                <Button variant="danger" :disabled="deleteSubmitting" @click="confirmDelete">
                    {{ deleteSubmitting ? 'Đang xóa…' : 'Xác nhận xóa' }}
                </Button>
            </template>
        </Modal>
    </div>
</template>
