<script setup>
import { computed, onMounted, ref, nextTick } from 'vue';
import {
    ArrowLeftIcon,
    BoltIcon,
    CheckCircleIcon,
    CommandLineIcon,
    ExclamationTriangleIcon,
    QuestionMarkCircleIcon,
    XCircleIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
    CpuChipIcon,
    ComputerDesktopIcon,
    ShieldExclamationIcon,
    ArrowTopRightOnSquareIcon,
    WrenchScrewdriverIcon,
    LightBulbIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import Modal from '../components/ui/Modal.vue';
import { ESPLoader, Transport } from 'esptool-js';
import api from '../lib/api';
import { useToastStore } from '../stores/toast';

const toast = useToastStore();

// Trạng thái kiểm tra hỗ trợ trình duyệt Web Serial
const isSerialSupported = ref(typeof navigator !== 'undefined' && 'serial' in navigator);

// Manifest từ máy chủ
const manifest = ref(null);
const loadingManifest = ref(true);

// Chế độ nạp: 'update' (Giữ cấu hình), 'full' (Cài mới xuất xưởng)
const installMode = ref('update');

// Tốc độ nạp mặc định tối ưu
const DEFAULT_BAUD_RATE = 460800;

// Trạng thái kết nối & Flasher
const isConnecting = ref(false);
const isConnected = ref(false);
const isFlashing = ref(false);
const statusStep = ref('idle'); // 'idle', 'connecting', 'connected', 'downloading', 'writing', 'done', 'error'
const statusMessage = ref('Chưa kết nối');
const progressPercent = ref(0);
const progressDetails = ref('');

// Link sản phẩm (Affiliate) cấu hình từ .env
const affiliateEspUrl = import.meta.env.VITE_AFFILIATE_ESP32_URL || import.meta.env.VITE_AFFILIATE_ESP32_DEVKIT_URL || 'https://shopee.vn/search?keyword=esp32%20devkit%20v1';
const affiliateUsbUrl = import.meta.env.VITE_AFFILIATE_USB_CABLE_URL || 'https://shopee.vn/search?keyword=cap%20type%20c%20truyen%20du%20lieu';


// Thông tin chip sau khi kết nối
const chipInfo = ref({
    model: '',
    flashSize: '',
    boardType: '', // 'devkit' hoặc 'c3'
});

// Kiểm tra xem chip có phải là dòng ESP32 được hỗ trợ (DevKit hoặc C3)
const isEsp32Supported = computed(() => {
    if (!isConnected.value || !chipInfo.value.model) return false;
    return chipInfo.value.boardType === 'devkit' || chipInfo.value.boardType === 'c3';
});

// Xác định loại bo mạch thân thiện
const detectedBoardLabel = computed(() => {
    if (!chipInfo.value.model) return 'Chưa xác định';
    const model = chipInfo.value.model.toUpperCase();
    if (model.includes('ESP32-C3') || model.includes('ESP32C3')) {
        return 'ESP32-C3 SuperMini';
    }
    if (model.includes('ESP32')) {
        return 'ESP32 DevKit (WROOM / NodeMCU)';
    }
    return chipInfo.value.model;
});

// Logs terminal
const logs = ref([]);
const terminalRef = ref(null);

// Modal trợ giúp Driver & Hướng dẫn
const helpModalOpen = ref(false);

function log(msg, type = 'info') {
    const time = new Date().toLocaleTimeString('vi-VN');
    logs.value.push({ time, text: String(msg), type });
    nextTick(() => {
        if (terminalRef.value) {
            terminalRef.value.scrollTop = terminalRef.value.scrollHeight;
        }
    });
}

function clearLogs() {
    logs.value = [];
}

function copyLogs() {
    const text = logs.value.map(l => `[${l.time}] ${l.text}`).join('\n');
    navigator.clipboard?.writeText(text);
    log('Đã sao chép toàn bộ nhật ký vào clipboard.', 'success');
}

function toSecureUrl(url) {
    if (!url) return '';
    if (typeof window !== 'undefined' && window.location.protocol === 'https:' && url.startsWith('http://')) {
        return url.replace(/^http:\/\//i, 'https://');
    }
    return url;
}

// Tải thông tin manifest từ Backend
async function fetchManifest() {
    loadingManifest.value = true;
    try {
        const { data } = await api.get('/firmware/manifest');
        manifest.value = data;
        log(`Đã tải thông tin Firmware v${data.version} từ máy chủ.`, 'success');
    } catch (e) {
        log(`Không thể tải firmware manifest: ${e?.response?.data?.error || e.message}`, 'error');
    } finally {
        loadingManifest.value = false;
    }
}

onMounted(() => {
    fetchManifest();
});

// Đối tượng ESPLoader & Transport đang kết nối
let esploader = null;
let transport = null;

// Terminal interface đáp ứng cho esptool-js
const terminalAdapter = {
    clean() {
        // clear terminal
    },
    writeLine(data) {
        if (data && data.trim()) log(data.trim(), 'device');
    },
    write(data) {
        if (data && data.trim()) log(data.trim(), 'device');
    },
};

// ==========================================
// BƯỚC 1: CHỌN CỔNG COM VÀ NHẬN DIỆN CHIP
// ==========================================
async function connectPort() {
    if (!isSerialSupported.value) {
        helpModalOpen.value = true;
        return;
    }

    if (isConnected.value) {
        await disconnectPort(false);
    }

    isConnecting.value = true;
    statusStep.value = 'connecting';
    statusMessage.value = 'Đang mở cổng kết nối Serial...';

    try {
        log('Yêu cầu người dùng chọn cổng COM (Web Serial)...', 'info');

        let port;
        try {
            port = await navigator.serial.requestPort();
        } catch (err) {
            log('Người dùng đã hủy chọn cổng COM hoặc không tìm thấy thiết bị.', 'warning');
            isConnecting.value = false;
            statusStep.value = 'idle';
            statusMessage.value = 'Chưa kết nối thiết bị. Vui lòng bấm "Chọn thiết bị".';
            toast.warning('Bạn chưa chọn cổng COM nào.');
            return;
        }

        transport = new Transport(port);
        esploader = new ESPLoader({
            transport,
            baudrate: DEFAULT_BAUD_RATE,
            terminal: terminalAdapter,
            enableTracing: false,
        });

        statusMessage.value = 'Đang đồng bộ bootloader... Nhấn giữ nút BOOT nếu cần!';
        log('Đang kết nối ROM bootloader...', 'info');
        log('👉 Mẹo: Nếu mạch không tự kết nối, hãy NHẤN GIỮ NÚT BOOT (IO0/IO9) trên mạch ngay bây giờ!', 'warning');

        let detectedChip;
        try {
            detectedChip = await esploader.main();
        } catch (bootErr) {
            console.error('Bootloader main error:', bootErr);
            const bMsg = String(bootErr?.message || '');
            log(`LỖI ĐỒNG BỘ BOOTLOADER: ${bMsg}`, 'error');
            log('👉 Lưu ý: Cổng COM đã chọn có thể là chuột, bàn phím hoặc thiết bị ngoại vi khác. Vui lòng chọn lại đúng cổng ESP32 hoặc nhấn giữ nút BOOT trên mạch.', 'warning');

            await disconnectPort(false);
            statusStep.value = 'error';
            statusMessage.value = 'Không thể đồng bộ Bootloader với ESP32: Vui lòng chọn lại cổng COM (chuột, phím hoặc thiết bị ngoại vi khác cũng có thể nhận cổng COM) hoặc nhấn giữ nút BOOT trên mạch rồi thử lại.';
            toast.error('Không thể đồng bộ Bootloader với ESP32! Vui lòng chọn lại cổng COM (chuột, phím...) hoặc giữ nút BOOT.');
            return;
        }

        isConnected.value = true;
        statusStep.value = 'connected';

        const chipModel = detectedChip || 'Unknown';
        chipInfo.value.model = chipModel;
        log(`Đã kết nối thành công với chip: ${chipModel}`, 'success');

        // Đọc kích thước Flash
        try {
            const fSize = await esploader.detectFlashSize();
            chipInfo.value.flashSize = fSize || '4MB';
            log(`Kích thước bộ nhớ Flash: ${chipInfo.value.flashSize}`, 'info');
        } catch {
            chipInfo.value.flashSize = '4MB';
        }

        // Tự động phân loại bo mạch
        const upper = chipModel.toUpperCase();
        if (upper.includes('ESP32-C3') || upper.includes('ESP32C3')) {
            chipInfo.value.boardType = 'c3';
        } else if (upper.includes('ESP32')) {
            chipInfo.value.boardType = 'devkit';
        } else {
            chipInfo.value.boardType = 'unsupported';
        }

        // Kiểm tra loại chip có hợp lệ không
        if (!upper.includes('ESP32')) {
            log(`⚠️ CẢNH BÁO: Phát hiện thiết bị không phải ESP32 (${chipModel}). Nút nạp firmware đã bị vô hiệu hóa!`, 'error');
            statusStep.value = 'error';
            statusMessage.value = `Thiết bị (${chipModel}) không phải vi điều khiển ESP32 / ESP32-C3. Vui lòng chọn lại đúng cổng COM.`;
            toast.error(`Thiết bị (${chipModel}) không phải vi điều khiển ESP32!`);
        } else {
            log(`✓ Thiết bị hợp lệ: ${detectedBoardLabel.value}. Sẵn sàng cho Bước 2.`, 'success');
            statusMessage.value = `Đã nhận diện: ${detectedBoardLabel.value}`;
            toast.success(`Đã kết nối thành công: ${detectedBoardLabel.value}`);
        }
    } catch (err) {
        console.error('Connect error:', err);
        log(`LỖI KẾT NỐI: ${err.message}`, 'error');
        await disconnectPort(false);
        statusStep.value = 'error';

        const errMsg = String(err?.message || '').toLowerCase();
        if (err?.name === 'NetworkError' || errMsg.includes('already open') || errMsg.includes('access denied') || errMsg.includes('failed to open')) {
            statusMessage.value = 'Cổng COM đang bị ứng dụng khác chiếm giữ hoặc bị khóa. Hãy đóng các app khác hoặc cắm lại cáp USB.';
            toast.error('Cổng COM đang bị ứng dụng khác chiếm giữ hoặc bị khóa!');
        } else {
            statusMessage.value = `Lỗi kết nối cổng COM: ${err.message || 'Không thể mở cổng'}. Vui lòng kiểm tra lại cáp hoặc driver.`;
            toast.error(`Lỗi kết nối: ${err.message}`);
        }
    } finally {
        isConnecting.value = false;
    }
}

// Ngắt kết nối cổng
async function disconnectPort(notify = true) {
    if (transport) {
        try {
            await transport.disconnect();
            if (notify) log('Đã ngắt kết nối cổng COM.', 'info');
        } catch {
            // ignore disconnect error
        }
    }
    transport = null;
    esploader = null;
    isConnected.value = false;
    isConnecting.value = false;
    isFlashing.value = false;
    chipInfo.value = { model: '', flashSize: '', boardType: '' };
    statusStep.value = 'idle';
    statusMessage.value = 'Chưa kết nối thiết bị. Vui lòng bấm "Chọn thiết bị".';
}

// Gửi xung lệnh Reset phần cứng tới vi điều khiển (hỗ trợ cả ESP32-C3 USB JTAG và ESP32 DevKit UART)
async function resetDevice(showToast = true) {
    if (!transport) {
        if (showToast) toast.warning('Chưa kết nối cổng COM.');
        return;
    }

    log('Đang gửi lệnh Reset cứng để khởi động lại bo mạch...', 'info');

    try {
        // Bước 1: Xung Reset mạch nạp UART tự động tiêu chuẩn (CH340 / CP2102 / FTDI)
        // Kéo EN LOW (RTS=true, DTR=false) trong 150ms để ép chip Reset
        await transport.setDTR(false);
        await transport.setRTS(true);
        await new Promise((r) => setTimeout(r, 150));

        // Thả EN HIGH (RTS=false, DTR=false) và giữ IO0 HIGH để chip vào chế độ chạy SPI Flash
        await transport.setRTS(false);
        await transport.setDTR(false);
        await new Promise((r) => setTimeout(r, 150));

        // Bước 2: Xung UsbJtagSerialReset dành riêng cho ESP32-C3 / ESP32-S3 / C6 (cổng USB Type-C trực tiếp)
        await transport.setDTR(true);
        await transport.setRTS(false);
        await new Promise((r) => setTimeout(r, 100));
        await transport.setRTS(true);
        await transport.setDTR(false);
        await new Promise((r) => setTimeout(r, 100));
        await transport.setRTS(false);
        await transport.setDTR(false);
        await new Promise((r) => setTimeout(r, 100));

        // Bước 3: Gọi after('hard_reset') từ thư viện esptool-js
        if (esploader) {
            try {
                await esploader.after('hard_reset');
            } catch {
                // ignore
            }
        }

        log('✅ Đã gửi lệnh Reset thành công! Bo mạch đang khởi động lại.', 'success');
        if (showToast) {
            toast.success('Đã gửi lệnh Reset thành công! Bo mạch đang khởi động lại.');
        }
    } catch (err) {
        console.error('Reset error:', err);
        log(`Cảnh báo khi gửi lệnh reset: ${err.message}`, 'warning');
        if (showToast) {
            toast.error(`Không thể gửi lệnh reset: ${err.message}`);
        }
    }
}

// ==========================================
// BƯỚC 2: BẮT ĐẦU NẠP FIRMWARE
// ==========================================
async function startFlashProcess() {
    if (!isConnected.value || !esploader) {
        log('Vui lòng kết nối cổng COM ở Bước 1 trước.', 'warning');
        return;
    }

    if (!isEsp32Supported.value) {
        log('Không thể nạp: Thiết bị không phải là ESP32 tương thích.', 'error');
        return;
    }

    if (!manifest.value) {
        await fetchManifest();
        if (!manifest.value) return;
    }

    isFlashing.value = true;
    progressPercent.value = 0;
    progressDetails.value = '';

    try {
        const targetBoardKey = chipInfo.value.boardType || 'devkit';
        const boardManifest = manifest.value.boards?.[targetBoardKey];
        if (!boardManifest) {
            throw new Error(`Không tìm thấy cấu hình firmware cho dòng bo mạch ${targetBoardKey}.`);
        }

        // Tải các file binary từ server
        statusStep.value = 'downloading';
        statusMessage.value = 'Đang tải các file firmware từ máy chủ...';
        log(`Bắt đầu tải các file firmware cho [${boardManifest.name}]...`, 'info');

        const fileArray = [];

        if (installMode.value === 'update') {
            // Chế độ Cập nhật: Chỉ nạp đè firmware.bin tại offset 0x10000 (giữ nguyên NVS, WiFi, device_code)
            const appPart = boardManifest.parts.find(p => p.file === boardManifest.ota_file || p.address === 65536);
            if (!appPart) {
                throw new Error('Không tìm thấy file firmware ứng dụng trong manifest.');
            }
            const appUrl = toSecureUrl(appPart.url);
            log(`Tải file ứng dụng: ${appUrl}...`, 'info');
            const resp = await fetch(appUrl);
            if (!resp.ok) throw new Error(`Tải file ${appPart.file} thất bại (${resp.status})`);
            const buf = await resp.arrayBuffer();
            fileArray.push({
                data: new Uint8Array(buf),
                address: appPart.address,
            });
            log(`Đã tải ${appPart.file} (${(buf.byteLength / 1024).toFixed(1)} KB) -> Sẵn sàng ghi vào 0x${appPart.address.toString(16)}.`, 'success');
        } else {
            // Chế độ Cài mới hoàn toàn: Nạp đầy đủ các phân vùng (Bootloader, Partition table, FW, LittleFS)
            for (const part of boardManifest.parts) {
                const partUrl = toSecureUrl(part.url);
                log(`Tải ${part.name} (${part.file}) từ ${partUrl}...`, 'info');
                const resp = await fetch(partUrl);
                if (!resp.ok) throw new Error(`Tải file ${part.file} thất bại (${resp.status})`);
                const buf = await resp.arrayBuffer();
                fileArray.push({
                    data: new Uint8Array(buf),
                    address: part.address,
                });
                log(`✓ ${part.name}: ${(buf.byteLength / 1024).toFixed(1)} KB -> Offset 0x${part.address.toString(16)}`, 'success');
            }
        }

        // Bắt đầu quá trình nạp Flash
        statusStep.value = 'writing';
        statusMessage.value = installMode.value === 'full' ? 'Đang xóa flash & ghi dữ liệu...' : 'Đang ghi firmware mới...';
        log(`Bắt đầu ghi flash (${installMode.value === 'full' ? 'Cài mới hoàn toàn + Xóa flash' : 'Nâng cấp giữ cấu hình'})...`, 'info');

        const totalBytes = fileArray.reduce((acc, f) => acc + f.data.length, 0);
        let writtenBytesAcc = 0;

        await esploader.writeFlash({
            fileArray,
            flashSize: 'keep',
            flashMode: 'dio',
            flashFreq: '40m',
            eraseAll: installMode.value === 'full',
            compress: true,
            reportProgress: (fileIndex, written, total) => {
                const percent = Math.min(100, Math.round(((writtenBytesAcc + written) / totalBytes) * 100));
                progressPercent.value = percent;
                progressDetails.value = `File ${fileIndex + 1}/${fileArray.length}: ${(written / 1024).toFixed(0)} KB / ${(total / 1024).toFixed(0)} KB (${percent}%)`;
                if (written === total) {
                    writtenBytesAcc += total;
                }
            },
        });

        // Reset ESP32 để chạy firmware mới
        statusStep.value = 'done';
        statusMessage.value = 'Nạp Firmware hoàn tất! Đang gửi lệnh Reset khởi động lại...';
        progressPercent.value = 100;
        log('🎉 Nạp hoàn tất 100%! Đang gửi lệnh Reset khởi động lại bo mạch...', 'success');

        // Gửi xung lệnh reset phần cứng đầy đủ (hỗ trợ cả USB JTAG và UART)
        await resetDevice(false);

        // Chờ 300ms rồi giải phóng cổng COM để tránh driver USB giữ treo chân DTR/RTS trên chip
        await new Promise((r) => setTimeout(r, 300));
        await disconnectPort(false);

        statusStep.value = 'done';
        statusMessage.value = '🎉 Nạp Firmware thành công! Bo mạch đã được gửi lệnh reset và tự khởi động lại.';
        log('🔌 Đã giải phóng cổng COM. ESP32 đã tự khởi động lại và sẵn sàng hoạt động!', 'success');
        toast.success('🎉 Nạp thành công! Bo mạch đã tự khởi động lại.');
    } catch (err) {
        console.error('Flash error:', err);
        statusStep.value = 'error';
        statusMessage.value = `Lỗi nạp: ${err.message}`;
        log(`LỖI NẠP FIRMWARE: ${err.message}`, 'error');
        toast.error(`Quá trình nạp thất bại: ${err.message}`);
    } finally {
        isFlashing.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Cảnh báo nếu không hỗ trợ Web Serial -->
            <div
                v-if="!isSerialSupported"
                class="rounded-xl border border-danger/30 bg-danger-soft p-4 text-sm text-danger-strong flex items-start gap-3 shadow-sm"
            >
                <ExclamationTriangleIcon class="w-6 h-6 shrink-0 mt-0.5 text-danger" />
                <div>
                    <h3 class="font-bold text-base mb-1">Trình duyệt không hỗ trợ Web Serial API</h3>
                    <p class="leading-relaxed">
                        Tính năng nạp qua cổng USB trực tiếp yêu cầu công nghệ <strong>Web Serial API</strong>.
                        Vui lòng mở trang web này bằng trình duyệt <strong>Google Chrome, Microsoft Edge, Cốc Cốc hoặc Brave</strong> trên máy tính (Windows/Mac/Linux)
                        hoặc trên điện thoại Android (kết nối qua cáp OTG Type-C). Trình duyệt iOS/Safari/Firefox hiện chưa hỗ trợ Web Serial.
                    </p>
                </div>
            </div>

            <!-- Grid 3 cột trên màn hình lớn: Cột 1 (Quy trình 1, 2) - Cột 2 (Tiến trình & Nhật ký) - Cột 3 (Driver & Mẹo) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- ============================================== -->
                <!-- CỘT 1: QUY TRÌNH NẠP CHÍNH (4 CỘT) -->
                <!-- ============================================== -->
                <div class="lg:col-span-4 space-y-4">
                    <!-- 1. CHUẨN BỊ PHẦN CỨNG -->
                    <div class="rounded-xl border border-line bg-surface p-4 shadow-sm space-y-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-muted flex items-center gap-2">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand text-[11px] font-black text-white">1</span>
                            Chuẩn bị phần cứng
                        </h2>

                        <ul class="space-y-2.5 text-xs">
                            <li class="flex items-start gap-2.5">
                                <CpuChipIcon class="w-4 h-4 text-brand shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <strong class="text-ink">Bo mạch ESP32</strong>
                                        <a
                                            :href="affiliateEspUrl"
                                            target="_blank"
                                            class="inline-flex items-center gap-0.5 text-[11px] font-semibold text-brand hover:underline"
                                        >
                                            Link sản phẩm
                                            <ArrowTopRightOnSquareIcon class="w-3 h-3" />
                                        </a>
                                    </div>
                                    <p class="text-[11px] text-muted mt-0.5">
                                        ESP32 DevKit V1 (WROOM-32) hoặc ESP32-C3 SuperMini.
                                    </p>
                                </div>
                            </li>

                            <li class="flex items-start gap-2.5 border-t border-line/60 pt-2">
                                <svg class="w-4 h-4 text-brand shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="19" r="1.5" />
                                    <path d="M12 17.5V6" />
                                    <path d="M12 12.5l3.5 -2.5v-2" />
                                    <circle cx="15.5" cy="6.5" r="1.2" />
                                    <path d="M12 14.5l-3.5 -2.5v-2" />
                                    <rect x="7" y="5.5" width="2.5" height="2.5" rx="0.5" />
                                    <path d="M10 6l2 -3l2 3" />
                                </svg>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <strong class="text-ink">Dây cáp USB truyền dữ liệu</strong>
                                        <a
                                            :href="affiliateUsbUrl"
                                            target="_blank"
                                            class="inline-flex items-center gap-0.5 text-[11px] font-semibold text-brand hover:underline"
                                        >
                                            Link sản phẩm
                                            <ArrowTopRightOnSquareIcon class="w-3 h-3" />
                                        </a>
                                    </div>
                                    <p class="text-[11px] text-muted mt-0.5">
                                        Cáp 4 lõi hỗ trợ truyền data (không dùng cáp chỉ sạc nguồn).
                                    </p>
                                </div>
                            </li>

                            <li class="flex items-start gap-2.5 border-t border-line/60 pt-2">
                                <ComputerDesktopIcon class="w-4 h-4 text-brand shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <strong class="text-ink">Máy tính Windows hoặc macOS</strong>
                                    <p class="text-[11px] text-muted mt-0.5">
                                        Dùng trình duyệt Google Chrome, Edge, Cốc Cốc hoặc Brave.
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- 2. KIỂU NẠP FIRMWARE -->
                    <div class="rounded-xl border border-line bg-surface p-4 shadow-sm space-y-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-muted flex items-center gap-2">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand text-[11px] font-black text-white">2</span>
                            Nạp Firmware
                        </h2>

                        <div class="grid grid-cols-1 gap-2.5">
                            <!-- Nâng cấp giữ cấu hình (Khuyến nghị) -->
                            <label
                                class="relative flex cursor-pointer rounded-lg border p-3 transition select-none"
                                :class="installMode === 'update' ? 'border-brand bg-brand-soft/30 ring-1 ring-brand' : 'border-line hover:border-line-strong bg-surface'"
                            >
                                <input
                                    type="radio"
                                    name="installMode"
                                    value="update"
                                    v-model="installMode"
                                    :disabled="isFlashing || isConnecting"
                                    class="sr-only"
                                />
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-ink">Nâng cấp FW (Giữ cấu hình)</span>
                                        <span class="rounded bg-brand px-1.5 py-0.2 text-[9px] font-bold text-white">Khuyến nghị</span>
                                    </div>
                                    <p class="text-[11px] text-muted mt-0.5">
                                        Chỉ ghi đè phần mềm ứng dụng. <strong>Giữ nguyên 100%</strong> cấu hình WiFi, Dongle và mã xác minh.
                                    </p>
                                </div>
                            </label>

                            <!-- Cài mới hoàn toàn -->
                            <label
                                class="relative flex cursor-pointer rounded-lg border p-3 transition select-none"
                                :class="installMode === 'full' ? 'border-danger/60 bg-danger-soft/30 ring-1 ring-danger' : 'border-line hover:border-line-strong bg-surface'"
                            >
                                <input
                                    type="radio"
                                    name="installMode"
                                    value="full"
                                    v-model="installMode"
                                    :disabled="isFlashing || isConnecting"
                                    class="sr-only"
                                />
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-ink">Cài mới hoàn toàn (Full Install)</span>
                                        <!-- <span class="rounded bg-danger px-1.5 py-0.2 text-[9px] font-bold text-white">Xuất xưởng</span> -->
                                    </div>
                                    <p class="text-[11px] text-muted mt-0.5">
                                        Xóa sạch flash và nạp lại từ đầu 4 phân vùng (Bootloader, Partitions, App, LittleFS). Dành cho mạch mới.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <!-- CỤM NÚT HÀNH ĐỘNG THEO VÒNG ĐỜI (TRONG CARD 2) -->
                        <div class="space-y-2 pt-2 border-t border-line/60">
                            <!-- 1. Lúc đầu: Nút "Chọn thiết bị" -->
                            <button
                                v-if="!isConnected && !isConnecting"
                                type="button"
                                @click="connectPort"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-5 py-3.5 text-sm font-bold text-white shadow-md shadow-brand/20 transition hover:brightness-110 active:scale-[0.99]"
                            >
                                <BoltIcon class="w-5 h-5" />
                                Chọn thiết bị
                            </button>

                            <!-- 2. Đang kết nối/đồng bộ: "Kiểm tra thiết bị" (Disabled) -->
                            <button
                                v-else-if="isConnecting"
                                type="button"
                                disabled
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand/70 px-5 py-3.5 text-sm font-bold text-white opacity-80 cursor-not-allowed shadow-none"
                            >
                                <ArrowPathIcon class="w-5 h-5 animate-spin" />
                                Kiểm tra thiết bị
                            </button>

                            <!-- 3. Đã kết nối & Không đang nạp: "Bắt đầu nạp FW" -->
                            <template v-else-if="!isFlashing">
                                <button
                                    type="button"
                                    @click="startFlashProcess"
                                    :disabled="!isEsp32Supported"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand/25 transition hover:brightness-110 active:scale-[0.99] disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none"
                                >
                                    <BoltIcon class="w-5 h-5" />
                                    Bắt đầu nạp FW
                                </button>

                                <!-- Nút Khởi động lại (Reset) -->
                                <button
                                    type="button"
                                    @click="() => resetDevice(true)"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-muted hover:text-ink hover:border-brand/40 hover:bg-brand-soft/20 transition"
                                >
                                    <ArrowPathIcon class="w-3.5 h-3.5" />
                                    Khởi động lại mạch (Reset)
                                </button>

                                <!-- Nút Đổi cổng / Ngắt kết nối -->
                                <button
                                    type="button"
                                    @click="disconnectPort"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-muted hover:text-danger hover:border-danger/40 hover:bg-danger-soft/20 transition"
                                >
                                    <XCircleIcon class="w-3.5 h-3.5" />
                                    Đổi thiết bị khác / Ngắt kết nối
                                </button>
                            </template>

                            <!-- 4. Đang nạp FW: Nút Hủy / Dừng -->
                            <button
                                v-else
                                type="button"
                                @click="disconnectPort"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-danger px-5 py-3.5 text-sm font-bold text-white shadow-md shadow-danger/25 transition hover:brightness-110"
                            >
                                <XCircleIcon class="w-5 h-5" />
                                Dừng / Ngắt kết nối
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- CỘT 2: KẾT NỐI, TIẾN TRÌNH & TERMINAL (5 CỘT) -->
                <!-- ============================================== -->
                <div class="lg:col-span-5">
                    <!-- 3. TIẾN ĐỘ & THÔNG TIN THIẾT BỊ + NHẬT KÝ FLASH -->
                    <div class="rounded-xl border border-line bg-surface p-4 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-muted flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand text-[11px] font-black text-white">3</span>
                                Tiến trình & Nhật ký
                            </h2>
                        </div>

                        <!-- Dòng hướng dẫn trước khi chọn thiết bị -->
                        <div v-if="!isConnected && !isConnecting">
                            <div v-if="statusStep === 'error'" class="p-2.5 rounded-lg border border-danger/30 bg-danger-soft/40 text-xs flex items-start gap-2">
                                <ExclamationTriangleIcon class="w-4 h-4 shrink-0 mt-0.5 text-danger" />
                                <span class="text-danger leading-relaxed">{{ statusMessage }}</span>
                            </div>
                            <div v-else-if="statusStep === 'done'" class="p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-xs flex items-start gap-2.5">
                                <CheckCircleIcon class="w-5 h-5 shrink-0 mt-0.5 text-emerald-500" />
                                <div>
                                    <h4 class="font-bold text-emerald-600 dark:text-emerald-400 text-xs">Nạp thành công & Đã tự khởi động lại!</h4>
                                    <p class="text-muted text-[11px] mt-0.5 leading-relaxed">{{ statusMessage }}</p>
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted leading-relaxed">
                                Cắm cáp USB nối ESP32 với máy tính rồi bấm "Chọn thiết bị" để bắt đầu.
                            </p>
                        </div>

                        <!-- Trạng thái đang kết nối -->
                        <div v-else-if="isConnecting" class="flex items-center gap-2 text-xs text-brand font-medium py-1">
                            <ArrowPathIcon class="w-4 h-4 animate-spin" />
                            <span>Đang kiểm tra và nhận diện vi điều khiển...</span>
                        </div>

                        <!-- Thông tin chip nhận diện sau khi kết nối -->
                        <div v-else-if="isConnected" class="space-y-3">
                            <div class="rounded-lg border border-line bg-page p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="rounded-md bg-brand-soft p-1.5 text-brand">
                                            <CpuChipIcon class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <span class="text-[10px] uppercase font-bold text-muted block">Loại Bo Mạch:</span>
                                            <span class="text-sm font-bold text-ink">{{ detectedBoardLabel }}</span>
                                        </div>
                                    </div>
                                    <span class="rounded bg-brand px-1.5 py-0.5 text-[10px] font-mono font-bold text-white uppercase">
                                        {{ chipInfo.model }}
                                    </span>
                                </div>
                            </div>

                            <!-- Cảnh báo nếu không phải ESP32 -->
                            <div
                                v-if="!isEsp32Supported"
                                class="rounded-lg border border-danger/40 bg-danger-soft p-3 text-xs text-danger-strong flex items-start gap-2.5 shadow-sm"
                            >
                                <ShieldExclamationIcon class="w-5 h-5 shrink-0 text-danger mt-0.5" />
                                <div>
                                    <h4 class="font-bold text-xs uppercase mb-0.5">Thiết bị không tương thích!</h4>
                                    <p class="leading-relaxed">
                                        Thiết bị được nhận diện là <strong>{{ chipInfo.model }}</strong>, không phải là vi điều khiển <strong>ESP32 / ESP32-C3</strong>.
                                        Hệ thống đã khóa nút nạp để tránh hỏng thiết bị.
                                    </p>
                                </div>
                            </div>

                            <!-- Thanh tiến độ (Thay thế vị trí thông báo nhận diện) -->
                            <div v-else class="space-y-1.5 pt-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-muted text-[11px]">{{ progressDetails || (isFlashing ? 'Đang nạp firmware...' : 'Sẵn sàng nạp') }}</span>
                                    <span class="font-bold font-mono text-ink text-xs">{{ progressPercent }}%</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-line/60">
                                    <div
                                        class="h-full bg-gradient-to-r from-brand to-amber-400 transition-all duration-300"
                                        :style="{ width: `${progressPercent}%` }"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Khung Log Terminal (bên trong Card Mục 3) -->
                        <div class="rounded-lg border border-line bg-black/95 text-green-400 shadow-sm flex flex-col h-[340px] font-mono text-xs overflow-hidden">
                            <!-- Header Terminal -->
                            <div class="flex items-center justify-between border-b border-white/10 px-3 py-2 bg-white/5">
                                <div class="flex items-center gap-2">
                                    <CommandLineIcon class="w-4 h-4 text-brand" />
                                    <span class="font-semibold text-white/80 text-[11px]">Nhật ký Flasher</span>
                                    <span class="text-[10px] text-white/40">({{ logs.length }} dòng)</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="copyLogs"
                                        class="rounded px-2 py-0.5 text-[10px] text-white/60 hover:text-white hover:bg-white/10 transition"
                                    >
                                        Sao chép
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearLogs"
                                        class="rounded px-2 py-0.5 text-[10px] text-white/60 hover:text-white hover:bg-white/10 transition"
                                    >
                                        Xóa
                                    </button>
                                </div>
                            </div>

                            <!-- Nội dung Log -->
                            <div ref="terminalRef" class="flex-1 p-3 overflow-y-auto space-y-1 select-text">
                                <div v-if="logs.length === 0" class="text-white/30 italic">
                                    Sẵn sàng... Bấm "Chọn thiết bị" để bắt đầu.
                                </div>
                                <div
                                    v-for="(item, idx) in logs"
                                    :key="idx"
                                    class="leading-relaxed break-all"
                                    :class="{
                                        'text-emerald-400': item.type === 'success',
                                        'text-amber-400': item.type === 'warning',
                                        'text-rose-400 font-semibold': item.type === 'error',
                                        'text-sky-300': item.type === 'device',
                                        'text-white/70': item.type === 'info',
                                    }"
                                >
                                    <span class="text-white/30 text-[10px] mr-1.5">[{{ item.time }}]</span>
                                    <span>{{ item.text }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- CỘT 3: TẢI DRIVER & MẸO / CHÚ Ý (3 CỘT) -->
                <!-- ============================================== -->
                <div class="lg:col-span-3 space-y-4">
                    <!-- Card Tải Driver -->
                    <div class="rounded-xl border border-line bg-surface p-4 shadow-sm space-y-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-muted flex items-center gap-2">
                            <ArrowDownTrayIcon class="w-4 h-4 text-brand" />
                            Tải Driver USB
                        </h2>
                        <p class="text-[11px] text-muted">
                            Nếu cắm cáp vào máy tính mà bảng chọn cổng COM bị trống, hãy tải và cài đặt driver tương ứng:
                        </p>

                        <div class="space-y-2">
                            <!-- Driver CH340 -->
                            <a
                                href="https://www.wch-ic.com/downloads/CH341SER_EXE.html"
                                target="_blank"
                                class="block rounded-lg border border-line p-2.5 hover:border-brand hover:bg-brand-soft/20 transition group"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-ink group-hover:text-brand">Driver CH340 / CH341</span>
                                    <ArrowDownTrayIcon class="w-3.5 h-3.5 text-muted group-hover:text-brand" />
                                </div>
                                <span class="text-[10px] text-muted block mt-0.5">Dành cho mạch ESP32 NodeMCU / DevKit phổ biến</span>
                            </a>

                            <!-- Driver CP210x -->
                            <a
                                href="https://www.silabs.com/developers/usb-to-uart-bridge-vcp-drivers"
                                target="_blank"
                                class="block rounded-lg border border-line p-2.5 hover:border-brand hover:bg-brand-soft/20 transition group"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-ink group-hover:text-brand">Driver CP210x (Silicon Labs)</span>
                                    <ArrowDownTrayIcon class="w-3.5 h-3.5 text-muted group-hover:text-brand" />
                                </div>
                                <span class="text-[10px] text-muted block mt-0.5">Dành cho các mạch ESP32 chính hãng</span>
                            </a>
                        </div>
                    </div>

                    <!-- Card Mẹo & Chú ý -->
                    <div class="rounded-xl border border-line bg-surface p-4 shadow-sm space-y-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-muted flex items-center gap-2">
                            <LightBulbIcon class="w-4 h-4 text-amber-500" />
                            Mẹo & Chú ý
                        </h2>

                        <div class="space-y-3 text-xs text-muted">
                            <!-- Mẹo nút BOOT -->
                            <div class="rounded-lg bg-amber-500/10 p-2.5 border border-amber-500/20 text-ink">
                                <span class="font-bold text-[11px] text-amber-600 dark:text-amber-400 block mb-1">
                                    👉 Mẹo nhấn nút BOOT:
                                </span>
                                <p class="text-[11px] leading-relaxed text-muted">
                                    Nếu báo lỗi <em>"Failed to connect"</em>, hãy dùng tay <strong>NHẤN GIỮ NÚT BOOT</strong> trên mạch khi cắm cáp, bấm nút "Chọn thiết bị" rồi mới thả tay ra.
                                </p>
                            </div>

                            <!-- Lưu ý Cáp USB -->
                            <div class="space-y-1">
                                <strong class="text-ink text-[11px] block">⚠️ Cáp USB:</strong>
                                <p class="text-[11px] text-muted leading-relaxed">
                                    Rất nhiều cáp Type-C giá rẻ chỉ là cáp sạc 2 lõi nguồn. Bắt buộc phải dùng cáp 4 lõi có đường truyền dữ liệu D+/D-.
                                </p>
                            </div>

                            <!-- macOS / Linux -->
                            <div class="space-y-1 border-t border-line/60 pt-2">
                                <strong class="text-ink text-[11px] block">🍎 macOS:</strong>
                                <p class="text-[11px] text-muted leading-relaxed">
                                    macOS Ventura / Sonoma đã tích hợp sẵn driver, cổng sẽ có tên dạng <code>/dev/cu.usbserial...</code> hoặc <code>wchusbserial</code>.
                                </p>
                            </div>

                            <!-- Android OTG -->
                            <div class="space-y-1 border-t border-line/60 pt-2">
                                <strong class="text-ink text-[11px] block">📱 Điện thoại Android:</strong>
                                <p class="text-[11px] text-muted leading-relaxed">
                                    Có thể cắm cáp OTG Type-C kết nối ESP32 với điện thoại Android, mở trình duyệt Chrome để nạp trực tiếp!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Trợ giúp & Cài đặt Driver -->
        <Modal :open="helpModalOpen" title="Hướng dẫn Kết nối & Cài đặt Driver USB" @close="helpModalOpen = false">
            <div class="space-y-4 text-sm text-ink">
                <div class="rounded-lg bg-brand-soft/40 p-3 text-xs text-brand-strong border border-brand/20">
                    💡 Nếu bạn cắm cáp USB nhưng bảng chọn cổng COM của trình duyệt bị trống, máy tính của bạn cần cài Driver cho chip chuyển đổi USB-UART.
                </div>

                <div class="space-y-2">
                    <h4 class="font-bold text-xs uppercase tracking-wide text-muted">1. Tải bộ cài Driver tự động (Windows):</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <a
                            href="https://www.wch-ic.com/downloads/CH341SER_EXE.html"
                            target="_blank"
                            class="flex items-center justify-between rounded-lg border border-line p-3 hover:border-brand hover:bg-brand-soft/20 transition group"
                        >
                            <div>
                                <span class="font-semibold text-xs text-ink block">Driver CH340 / CH341</span>
                                <span class="text-[11px] text-muted">Dành cho đa số bo ESP32 NodeMCU</span>
                            </div>
                            <ArrowDownTrayIcon class="w-4 h-4 text-muted group-hover:text-brand" />
                        </a>

                        <a
                            href="https://www.silabs.com/developers/usb-to-uart-bridge-vcp-drivers"
                            target="_blank"
                            class="flex items-center justify-between rounded-lg border border-line p-3 hover:border-brand hover:bg-brand-soft/20 transition group"
                        >
                            <div>
                                <span class="font-semibold text-xs text-ink block">Driver CP210x (Silicon Labs)</span>
                                <span class="text-[11px] text-muted">Dành cho các mạch ESP32 chính hãng</span>
                            </div>
                            <ArrowDownTrayIcon class="w-4 h-4 text-muted group-hover:text-brand" />
                        </a>
                    </div>
                </div>

                <div class="space-y-2 border-t border-line pt-3">
                    <h4 class="font-bold text-xs uppercase tracking-wide text-muted">2. Mẹo xử lý khi không nhận cổng hoặc báo lỗi:</h4>
                    <ul class="list-disc pl-5 space-y-1.5 text-xs text-muted">
                        <li><strong>Dùng cáp truyền dữ liệu</strong>: Rất nhiều cáp Type-C giá rẻ chỉ là cáp sạc (chỉ có 2 sợi nguồn, không có 2 sợi dữ liệu D+/D-).</li>
                        <li><strong>Thao tác nút BOOT</strong>: Khi cắm cáp USB vào máy tính, hãy nhấn giữ nút <code>BOOT</code> (GPIO0 hoặc GPIO9) trong 3 giây rồi nhả ra để ép chip vào chế độ chờ nạp bootloader.</li>
                        <li><strong>Trên điện thoại Android</strong>: Cắm cáp OTG Type-C vào điện thoại, kết nối với ESP32, mở trình duyệt Chrome và bấm "Chọn Cổng COM". Điện thoại sẽ hiện popup xin cấp quyền cổng USB Serial.</li>
                    </ul>
                </div>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="rounded-lg bg-surface border border-line px-4 py-2 text-xs font-semibold text-ink hover:bg-black/5 dark:hover:bg-white/5 transition"
                    @click="helpModalOpen = false"
                >
                    Đã hiểu & Đóng
                </button>
            </template>
        </Modal>
    </div>
</template>
