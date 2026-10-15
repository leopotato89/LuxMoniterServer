<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import {
    CheckCircleIcon,
    PlusIcon,
    XCircleIcon,
    BoltIcon,
    ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline';
import Button from '../components/ui/Button.vue';
import Modal from '../components/ui/Modal.vue';
import ToggleSwitch from '../components/ui/ToggleSwitch.vue';
import EnergyDiagram from '../components/realtime/EnergyDiagram.vue';
import { useAuthStore } from '../stores/auth';
import { useDevicesStore } from '../stores/devices';
import { useToastStore } from '../stores/toast';
import api from '../lib/api';

const auth = useAuthStore();
const devices = useDevicesStore();
const toast = useToastStore();
const router = useRouter();

const pinnedSerial = ref(localStorage.getItem('luxmonitor_pinned_device') || '');
const selectedSerial = ref('');

// Thiết bị hiển thị trên Diagram:
// 1. Ưu tiên click chọn hiện tại
// 2. Nếu không có click, chọn thiết bị được ghim
// 3. Nếu chưa ghim, chọn thiết bị đầu tiên trong danh sách
const activeDevice = computed(() => {
    if (!devices.items || devices.items.length === 0) return null;

    if (selectedSerial.value) {
        const found = devices.items.find(d => d.serial === selectedSerial.value);
        if (found) return found;
    }

    if (pinnedSerial.value) {
        const found = devices.items.find(d => d.serial === pinnedSerial.value);
        if (found) return found;
    }

    return devices.items[0];
});

function togglePin(device, event) {
    if (event) event.stopPropagation();
    if (pinnedSerial.value === device.serial) {
        pinnedSerial.value = '';
        localStorage.removeItem('luxmonitor_pinned_device');
        toast.info(`Đã bỏ ghim ${device.name || device.serial}`);
    } else {
        pinnedSerial.value = device.serial;
        selectedSerial.value = device.serial;
        localStorage.setItem('luxmonitor_pinned_device', device.serial);
        toast.success(`Đã ghim ${device.name || device.serial} lên màn hình chính`);
    }
}

function selectDevice(device) {
    selectedSerial.value = device.serial;
}

if (!auth.isAdmin) {
    devices.filters.per_page = 5;
}

const search = ref('');
const claimOpen = ref(false);
const claimForm = ref({ serial: '', device_code: '' });
const claimSubmitting = ref(false);

const createOpen = ref(false);
const createForm = ref({ serial: '', name: '' });
const createSubmitting = ref(false);

const editTarget = ref(null);
const editForm = ref({ name: '' });
const editSubmitting = ref(false);

const deleteTarget = ref(null);
const deleteSubmitting = ref(false);

const unclaimTarget = ref(null);
const unclaimSubmitting = ref(false);

const users = ref([]);

async function fetchUsers() {
    if (!auth.isAdmin) return;
    try {
        const { data } = await api.get('/users');
        users.value = data.data;
    } catch (e) {
        console.error('Failed to fetch users', e);
    }
}





// Gõ tìm kiếm: chờ 300ms rồi mới gọi API.
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        devices.filters.search = value;
        devices.filters.page = 1;
        devices.fetch();
    }, 300);
});

watch(
    () => [devices.filters.page, devices.filters.per_page, devices.filters.sort, devices.filters.direction],
    () => devices.fetch(),
);

onMounted(() => {
    devices.fetch();
    fetchUsers();
});



async function submitClaim() {
    claimSubmitting.value = true;

    try {
        const ok = await devices.claim(claimForm.value.serial.trim(), claimForm.value.device_code.trim());

        if (ok) {
            claimOpen.value = false;
            claimForm.value = { serial: '', device_code: '' };
        }
    } finally {
        claimSubmitting.value = false;
    }
}

async function submitCreate() {
    createSubmitting.value = true;

    try {
        const payload = {
            serial: createForm.value.serial.trim(),
            name: createForm.value.name.trim() || null,
        };
        if (createForm.value.owner_id !== undefined) {
            payload.owner_id = createForm.value.owner_id;
        }

        const ok = await devices.create(payload);

        if (ok) {
            createOpen.value = false;
            createForm.value = { serial: '', name: '', owner_id: '' };
        }
    } finally {
        createSubmitting.value = false;
    }
}

function openEdit(device) {
    editTarget.value = device;
    editForm.value = { 
        name: device.name ?? '',
        owner_id: device.owner?.id ?? '',
    };
}

async function submitEdit() {
    editSubmitting.value = true;

    try {
        const payload = { name: editForm.value.name || null };
        if (auth.isAdmin) {
            payload.owner_id = editForm.value.owner_id || null;
        }

        const ok = await devices.update(editTarget.value.serial, payload);

        if (ok) {
            editTarget.value = null;
        }
    } finally {
        editSubmitting.value = false;
    }
}

async function confirmDelete() {
    deleteSubmitting.value = true;

    try {
        const ok = await devices.destroy(deleteTarget.value.serial);

        if (ok) {
            deleteTarget.value = null;
        }
    } finally {
        deleteSubmitting.value = false;
    }
}

async function confirmUnclaim() {
    unclaimSubmitting.value = true;

    try {
        const ok = await devices.unclaim(unclaimTarget.value.serial);

        if (ok) {
            unclaimTarget.value = null;
        }
    } finally {
        unclaimSubmitting.value = false;
    }
}

async function copyCode(text) {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Đã copy: ' + text);
    } catch (e) {
        toast.error('Không thể copy');
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Danh sách thiết bị (4/12 phần) -->
            <div class="lg:col-span-4">
                <div class="x-rounded-card p-0 flex flex-col overflow-hidden">
                    <!-- Header & Bộ lọc -->
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-line">
                        <h1 class="text-lg font-bold tracking-tight text-ink">Thiết bị</h1>

                        <Button v-if="auth.isAdmin" size="sm" @click="createOpen = true">
                            <PlusIcon class="h-4 w-4" />
                            Tạo
                        </Button>
                        <Button v-else size="sm" @click="claimOpen = true">
                            <PlusIcon class="h-4 w-4" />
                            Thêm
                        </Button>
                    </div>

                    <div class="flex flex-col gap-3 px-4 py-3 border-b border-line">
                        <div class="flex items-center gap-2">
                            <input v-model="search" class="field w-full" type="search" placeholder="Tìm theo serial hoặc tên…">
                            <span v-if="devices.loading" class="text-sm text-muted shrink-0">Tải…</span>
                        </div>
                    </div>

                    <!-- Danh sách từng thiết bị -->
                    <div class="flex flex-col gap-3 p-4">
                        <div 
                            v-for="device in devices.items" 
                            :key="device.serial" 
                            class="rounded-xl border p-4 transition cursor-pointer flex flex-col gap-2"
                            :class="activeDevice?.serial === device.serial ? 'border-brand bg-brand-soft/20 shadow-sm ring-1 ring-brand/50' : 'border-line hover:border-brand/40 bg-surface shadow-sm hover:shadow-md'"
                            @click="selectDevice(device)"
                        >
                            <!-- Header row -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <button 
                                        type="button" 
                                        class="text-base font-bold text-brand truncate hover:opacity-80 transition text-left focus:outline-none flex items-center gap-1"
                                        title="Nhấn để copy"
                                        @click.stop="copyCode(device.serial)"
                                    >
                                        SN: {{ device.serial }}
                                    </button>
                                    <span
                                        v-if="pinnedSerial === device.serial"
                                        class="inline-flex items-center rounded bg-amber-500/10 px-1.5 py-0.2 text-[9px] font-bold text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0"
                                        title="Thiết bị được ghim"
                                    >
                                        Ghim
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <!-- Nút Ghim -->
                                    <button
                                        type="button"
                                        class="p-1 rounded-md transition hover:bg-black/5 dark:hover:bg-white/5 focus:outline-none"
                                        :title="pinnedSerial === device.serial ? 'Bỏ ghim' : 'Ghim thiết bị này'"
                                        @click.stop="togglePin(device, $event)"
                                    >
                                        <svg
                                            class="w-4 h-4 transition"
                                            :class="pinnedSerial === device.serial ? 'text-amber-500 fill-amber-500' : 'text-muted hover:text-ink'"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <line x1="12" y1="17" x2="12" y2="22"></line>
                                            <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a1 1 0 0 0 0-2H8a1 1 0 0 0 0 2h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"></path>
                                        </svg>
                                    </button>

                                    <CheckCircleIcon v-if="device.verified_at" class="h-5 w-5 text-ok shrink-0" title="Đã xác minh" />
                                    <XCircleIcon v-else class="h-5 w-5 text-danger shrink-0" title="Chưa xác minh" />
                                </div>
                            </div>

                            <!-- Text block -->
                            <div class="text-sm text-muted">
                                <p v-if="device.name" class="truncate font-medium text-ink">Tên: {{ device.name }}</p>
                                <p class="truncate text-xs mt-0.5">Chủ sở hữu: <span v-if="device.owner" class="font-medium text-brand">{{ device.owner.name }}</span><span v-else>Chưa gắn</span></p>
                                <p v-if="auth.isAdmin" class="truncate font-mono text-xs text-ink mt-0.5">
                                    Mã TB: 
                                    <button 
                                        v-if="device.device_code"
                                        type="button" 
                                        class="hover:text-brand transition focus:outline-none"
                                        title="Nhấn để copy"
                                        @click.stop="copyCode(device.device_code)"
                                    >
                                        {{ device.device_code }}
                                    </button>
                                    <span v-else class="text-muted">—</span>
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-1 pt-2 flex items-center justify-between text-xs font-medium border-t border-line/40">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-brand hover:underline font-semibold"
                                    @click.stop="router.push({ name: 'devices.show', params: { serial: device.serial } })"
                                >
                                    <span>Chi tiết</span>
                                    <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                                </button>

                                <div class="flex items-center gap-3">
                                    <button
                                        v-if="auth.isAdmin || auth.user?.id === device.owner?.id"
                                        type="button"
                                        class="text-ink hover:text-brand transition"
                                        @click.stop="openEdit(device)"
                                    >
                                        Sửa
                                    </button>
                                    <button
                                        v-if="auth.isAdmin || auth.user?.id === device.owner?.id"
                                        type="button"
                                        class="text-danger hover:brightness-90 transition"
                                        @click.stop="auth.isAdmin ? deleteTarget = device : unclaimTarget = device"
                                    >
                                        Xóa
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!devices.loading && devices.items.length === 0" class="rounded-xl border border-line bg-surface/60 p-8 text-center text-muted text-sm backdrop-blur-sm">
                            Không có thiết bị nào.
                        </div>
                    </div>

                    <!-- Phân trang -->
                    <div 
                        v-if="auth.isAdmin || devices.meta.last_page > 1" 
                        class="border-t border-line px-4 py-3 flex flex-col gap-3 text-sm"
                    >
                        <div v-if="auth.isAdmin" class="flex items-center justify-between text-muted">
                            <div class="flex items-center gap-2">
                                <select v-model.number="devices.filters.per_page" class="field w-auto py-1 pl-2.5 pr-7 text-xs cursor-pointer">
                                    <option v-for="size in [5, 10, 25, 50]" :key="size" :value="size">{{ size }}</option>
                                </select>
                                <span>/trang</span>
                            </div>
                            <span>{{ devices.meta.total }} thiết bị</span>
                        </div>

                        <div 
                            v-if="devices.meta.last_page > 1 || auth.isAdmin" 
                            class="flex items-center justify-between"
                        >
                            <Button
                                variant="secondary"
                                size="sm"
                                :disabled="devices.filters.page <= 1"
                                @click="devices.filters.page--"
                            >
                                Trước
                            </Button>
                            <span class="text-muted text-xs">Trang {{ devices.meta.current_page }} / {{ devices.meta.last_page }}</span>
                            <Button
                                variant="secondary"
                                size="sm"
                                :disabled="devices.filters.page >= devices.meta.last_page"
                                @click="devices.filters.page++"
                            >
                                Sau
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cột bên phải: Diagram thiết bị được ghim / chọn (8/12 phần) -->
            <div class="lg:col-span-8">
                <div v-if="activeDevice" class="x-rounded-card p-0 flex flex-col overflow-hidden">
                    <!-- Header của Diagram Card -->
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-line bg-surface/50">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="rounded-lg bg-brand-soft p-1.5 text-brand shrink-0">
                                <BoltIcon class="w-5 h-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-bold text-ink truncate">
                                        {{ activeDevice.name || activeDevice.serial }}
                                    </h2>
                                    <span
                                        v-if="pinnedSerial === activeDevice.serial"
                                        class="inline-flex items-center gap-1 rounded bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0"
                                    >
                                        <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a1 1 0 0 0 0-2H8a1 1 0 0 0 0 2h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"></path>
                                        </svg>
                                        Đã ghim
                                    </span>
                                </div>
                                <span class="text-xs font-mono text-muted">SN: {{ activeDevice.serial }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Nút Ghim / Bỏ ghim -->
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-muted hover:text-ink hover:bg-black/5 dark:hover:bg-white/5 transition"
                                @click="togglePin(activeDevice)"
                            >
                                <svg
                                    class="w-3.5 h-3.5"
                                    :class="pinnedSerial === activeDevice.serial ? 'text-amber-500 fill-amber-500' : 'text-muted'"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a1 1 0 0 0 0-2H8a1 1 0 0 0 0 2h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"></path>
                                </svg>
                                <span>{{ pinnedSerial === activeDevice.serial ? 'Bỏ ghim' : 'Ghim thiết bị' }}</span>
                            </button>

                            <RouterLink
                                :to="{ name: 'devices.show', params: { serial: activeDevice.serial } }"
                                class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:brightness-110 transition"
                            >
                                <span>Xem chi tiết</span>
                                <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                            </RouterLink>
                        </div>
                    </div>

                    <!-- Diagram Container -->
                    <div class="p-2 sm:p-4">
                        <EnergyDiagram :key="activeDevice.serial" :serial="activeDevice.serial" />
                    </div>
                </div>

                <!-- Empty state khi chưa có thiết bị nào -->
                <div v-else class="x-rounded-card p-12 text-center text-muted flex flex-col items-center justify-center gap-3">
                    <div class="rounded-full bg-brand-soft p-4 text-brand">
                        <BoltIcon class="w-8 h-8" />
                    </div>
                    <h3 class="text-base font-bold text-ink">Chưa có thiết bị nào</h3>
                    <p class="text-sm max-w-sm text-muted">
                        Hãy thêm thiết bị giám sát ESP32 vào tài khoản để theo dõi sơ đồ luồng năng lượng mặt trời thời gian thực.
                    </p>
                    <Button v-if="auth.isAdmin" size="sm" @click="createOpen = true">
                        <PlusIcon class="h-4 w-4" />
                        Tạo thiết bị ngay
                    </Button>
                    <Button v-else size="sm" @click="claimOpen = true">
                        <PlusIcon class="h-4 w-4" />
                        Thêm thiết bị ngay
                    </Button>
                </div>
            </div>
        </div>

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
                    <input id="claim-serial" v-model="claimForm.serial" class="field" type="text" required>
                </div>

                <div>
                    <label for="claim-code" class="mb-1.5 block text-sm font-medium text-ink">
                        Mã thiết bị (từ ESP32) <span class="text-brand">*</span>
                    </label>
                    <input id="claim-code" v-model="claimForm.device_code" class="field" type="text" required>
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
                    <input id="create-serial" v-model="createForm.serial" class="field" type="text" required>
                </div>

                <div>
                    <label for="create-name" class="mb-1.5 block text-sm font-medium text-ink">Tên thiết bị</label>
                    <input id="create-name" v-model="createForm.name" class="field" type="text">
                </div>

                <div v-if="auth.isAdmin">
                    <label for="create-owner" class="mb-1.5 block text-sm font-medium text-ink">Chủ sở hữu</label>
                    <select id="create-owner" v-model="createForm.owner_id" class="field">
                        <option value="">Không gán</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.username }})
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
            <form id="edit-form" class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <label for="edit-name" class="mb-1.5 block text-sm font-medium text-ink">Tên thiết bị</label>
                    <input id="edit-name" v-model="editForm.name" class="field" type="text">
                </div>

                <div v-if="auth.isAdmin">
                    <label for="edit-owner" class="mb-1.5 block text-sm font-medium text-ink">Chủ sở hữu</label>
                    <select id="edit-owner" v-model="editForm.owner_id" class="field">
                        <option value="">Không gán</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.username }})
                        </option>
                    </select>
                </div>
            </form>

            <template #footer>
                <Button variant="ghost" @click="editTarget = null">Huỷ</Button>
                <Button type="submit" form="edit-form" :disabled="editSubmitting">
                    {{ editSubmitting ? 'Đang lưu…' : 'Lưu' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: xác nhận xoá -->
        <Modal :open="deleteTarget !== null" title="Xoá thiết bị" @close="deleteTarget = null">
            <p class="text-sm text-muted">
                Thiết bị <strong class="text-ink">{{ deleteTarget?.serial }}</strong> sẽ bị xoá khỏi hệ thống.
                Hành động này không hoàn tác được.
            </p>

            <template #footer>
                <Button variant="ghost" @click="deleteTarget = null">Huỷ</Button>
                <Button variant="danger" :disabled="deleteSubmitting" @click="confirmDelete">
                    {{ deleteSubmitting ? 'Đang xoá…' : 'Xoá' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: xác nhận huỷ theo dõi (user) -->
        <Modal :open="unclaimTarget !== null" title="Xóa thiết bị" @close="unclaimTarget = null">
            <p class="text-sm text-muted">
                Bạn có chắc chắn muốn xóa thiết bị <strong class="text-ink">{{ unclaimTarget?.serial }}</strong> khỏi danh sách của bạn không?
            </p>

            <template #footer>
                <Button variant="ghost" @click="unclaimTarget = null">Huỷ</Button>
                <Button variant="danger" :disabled="unclaimSubmitting" @click="confirmUnclaim">
                    {{ unclaimSubmitting ? 'Đang xóa…' : 'Xóa' }}
                </Button>
            </template>
        </Modal>
    </div>
</template>
