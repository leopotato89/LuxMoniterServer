<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import {
    CheckCircleIcon,
    PlusIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import Button from '../components/ui/Button.vue';
import Modal from '../components/ui/Modal.vue';
import ToggleSwitch from '../components/ui/ToggleSwitch.vue';
import { useAuthStore } from '../stores/auth';
import { useDevicesStore } from '../stores/devices';
import api from '../lib/api';

const auth = useAuthStore();
const devices = useDevicesStore();
const router = useRouter();

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
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Danh sách thiết bị (1/3) -->
            <div class="lg:col-span-1">
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
                            class="rounded-xl border border-line bg-surface shadow-sm p-4 transition hover:border-brand/50 hover:shadow-md cursor-pointer flex flex-col gap-2"
                            @click="router.push({ name: 'devices.show', params: { serial: device.serial } })"
                        >
                            <!-- Header row -->
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-base font-bold text-brand truncate" :title="device.serial">SN: {{ device.serial }}</h3>
                                <CheckCircleIcon v-if="device.verified_at" class="h-5 w-5 text-ok shrink-0" title="Đã xác minh" />
                                <XCircleIcon v-else class="h-5 w-5 text-danger shrink-0" title="Chưa xác minh" />
                            </div>

                            <!-- Text block -->
                            <div class="text-sm text-muted">
                                <p v-if="device.name" class="truncate">Tên thiết bị: {{ device.name }}</p>
                                <p class="truncate">Chủ sở hữu: <span v-if="device.owner" class="font-medium text-brand">{{ device.owner.name }}</span><span v-else>Chưa gắn</span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-1 pt-2 flex justify-end gap-4 text-sm font-medium border-t border-line/40">
                                <button
                                    type="button"
                                    class="text-danger hover:brightness-90 transition"
                                    @click.stop="deleteTarget = device"
                                >
                                    Xóa
                                </button>
                                <button
                                    type="button"
                                    class="text-ink hover:text-brand transition"
                                    @click.stop="openEdit(device)"
                                >
                                    Sửa
                                </button>
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

            <!-- Hướng dẫn (2/3) -->
            <div class="lg:col-span-1">
                <div class="p-2 lg:p-6">
                    <h2 class="text-xl font-bold text-brand mb-6 border-b border-line pb-4">Hướng dẫn cài đặt & Kết nối thiết bị</h2>
                    
                    <div class="space-y-8 text-ink text-sm lg:text-base leading-relaxed">
                        <section>
                            <h3 class="text-lg font-semibold flex items-center gap-2 mb-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-brand-soft text-brand text-sm font-bold">1</span>
                                Lấy thông tin từ ESP32
                            </h3>
                            <p class="mb-2">Để thêm thiết bị vào hệ thống, bạn cần kết nối vào mạng WiFi do bộ theo dõi (ESP32) phát ra. Mạng này thường có tên dạng <code class="bg-surface-alt px-1.5 py-0.5 rounded border border-line text-brand">LuxMonitor_XXXX</code>.</p>
                            <p>Sau khi kết nối WiFi, hãy mở trình duyệt web và truy cập vào địa chỉ <code class="bg-surface-alt px-1.5 py-0.5 rounded border border-line text-brand">http://192.168.4.1</code>.</p>
                            <p class="mt-2">Tại trang quản lý của ESP32, bạn sẽ thấy thông tin <strong>Serial</strong> và <strong>Mã thiết bị (Device Code)</strong>. Hãy ghi lại hoặc copy hai thông tin này.</p>
                        </section>

                        <section>
                            <h3 class="text-lg font-semibold flex items-center gap-2 mb-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-brand-soft text-brand text-sm font-bold">2</span>
                                Thêm thiết bị vào tài khoản
                            </h3>
                            <p class="mb-2">Nhấn vào nút <strong>Thêm thiết bị giám sát</strong> ở góc trên bên phải của trang này.</p>
                            <p>Nhập <strong>Serial</strong> và <strong>Mã thiết bị</strong> vừa lấy được ở bước 1 vào biểu mẫu và xác nhận.</p>
                            <p class="mt-2">Nếu thông tin hợp lệ, thiết bị sẽ được thêm vào danh sách bên trái và hiển thị trạng thái <span class="inline-flex items-center gap-1 text-ok font-medium"><CheckCircleIcon class="w-4 h-4" /> Đã xác minh</span>.</p>
                        </section>
                        
                        <section>
                            <h3 class="text-lg font-semibold flex items-center gap-2 mb-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-brand-soft text-brand text-sm font-bold">3</span>
                                Cấu hình MQTT cho ESP32
                            </h3>
                            <p class="mb-2">Khi thiết bị đã được thêm thành công vào tài khoản, bạn cần quay lại trang quản lý của ESP32 (ở bước 1) để cấu hình kết nối tới máy chủ:</p>
                            <ul class="list-disc list-inside space-y-1.5 ml-1 mt-3 mb-3 text-muted">
                                <li><strong>MQTT Server:</strong> Nhập địa chỉ IP hoặc tên miền của máy chủ giám sát này.</li>
                                <li><strong>MQTT Port:</strong> Thường là <code class="bg-surface-alt px-1.5 py-0.5 rounded border border-line">1883</code>.</li>
                                <li><strong>MQTT User / Password:</strong> Điền thông tin đăng nhập MQTT nếu hệ thống yêu cầu.</li>
                            </ul>
                            <p>Lưu cấu hình và khởi động lại ESP32. Thiết bị sẽ bắt đầu đọc dữ liệu từ biến tần (Inverter) và gửi lên hệ thống.</p>
                        </section>

                        <section>
                            <h3 class="text-lg font-semibold flex items-center gap-2 mb-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-brand-soft text-brand text-sm font-bold">4</span>
                                Theo dõi dữ liệu
                            </h3>
                            <p>Bấm vào thiết bị của bạn trong danh sách bên trái để xem bảng điều khiển chi tiết, theo dõi biểu đồ dữ liệu thời gian thực và lịch sử hoạt động của hệ thống điện năng lượng mặt trời.</p>
                        </section>
                    </div>
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
    </div>
</template>
