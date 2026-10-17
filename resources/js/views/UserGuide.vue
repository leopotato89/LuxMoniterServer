<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
    BoltIcon,
    WifiIcon,
    BookmarkSquareIcon,
    ArrowTopRightOnSquareIcon,
    CheckCircleIcon,
    InformationCircleIcon,
    ComputerDesktopIcon,
    ArrowPathIcon,
    KeyIcon,
    StarIcon,
    ShieldCheckIcon,
    SparklesIcon,
    DocumentDuplicateIcon,
    ShoppingCartIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import { useToastStore } from '../stores/toast';

const toast = useToastStore();

// Affiliate links từ .env (Vite env variables)
const affiliateEsp32Devkit = import.meta.env.VITE_AFFILIATE_ESP32_URL || import.meta.env.VITE_AFFILIATE_ESP32_DEVKIT_URL || 'https://shopee.vn/search?keyword=esp32%20devkit%20v1';
const affiliateEsp32C3 = import.meta.env.VITE_AFFILIATE_ESP32_C3_URL || 'https://shopee.vn/search?keyword=esp32%20c3%20super%20mini';
const affiliateCable = import.meta.env.VITE_AFFILIATE_USB_CABLE_URL || 'https://shopee.vn/search?keyword=cap%20type%20c%20truyen%20du%20lieu';

const sections = [
    { id: 'sec-flash', title: '1. Nạp Firmware ESP32', subtitle: 'Web Serial Flasher', icon: BoltIcon },
    { id: 'sec-config', title: '2. Kết nối & Cấu hình ESP32', subtitle: 'Setup WiFi & Giám sát Dongle', icon: WifiIcon },
    { id: 'sec-claim', title: '3. Thêm vào danh sách theo dõi', subtitle: 'Xác thực Web & Ghim mặc định', icon: BookmarkSquareIcon },
];

const activeSection = ref('sec-flash');
let observer = null;

function scrollTo(id) {
    activeSection.value = id;
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function copyText(text, label = 'nội dung') {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(`Đã sao chép ${label}: ${text}`);
    } catch {
        toast.error('Không thể sao chép tự động');
    }
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                    break;
                }
            }
        },
        {
            rootMargin: '-20% 0px -60% 0px',
            threshold: 0,
        },
    );

    sections.forEach((s) => {
        const el = document.getElementById(s.id);
        if (el) observer.observe(el);
    });
});

onUnmounted(() => {
    if (observer) observer.disconnect();
});
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto pb-16">
        <!-- Banner giới thiệu -->
        <div class="relative overflow-hidden rounded-2xl border border-line bg-gradient-to-br from-brand/10 via-surface to-surface p-6 sm:p-8 shadow-sm">
            <div class="relative z-10 max-w-3xl space-y-2">
                <div class="inline-flex items-center gap-1.5 rounded-full bg-brand/10 border border-brand/25 px-3 py-1 text-xs font-bold text-brand">
                    <SparklesIcon class="w-3.5 h-3.5" />
                    <span>Tài liệu hướng dẫn triển khai</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink">
                    Hướng dẫn cài đặt & sử dụng Lux<span class="text-brand">Monitor</span>
                </h1>
                <p class="text-sm sm:text-base text-muted leading-relaxed">
                    Hệ thống sử dụng bảng mạch ESP32 để giám sát thời gian thực và thiết lập thông số cho biến tần LuxPower của bạn. Vui lòng đọc kỹ hướng dẫn từng bước dưới đây để kết nối và kích hoạt thiết bị thành công. Chúc bạn có những trải nghiệm tuyệt vời và tiện ích cùng hệ thống năng lượng xanh của gia đình!
                </p>
            </div>
        </div>

        <!-- Bố cục 2 cột: Sidebar Mục lục bên trái + Nội dung bên phải -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- SIDEBAR MỤC LỤC BÊN TRÁI -->
            <aside class="lg:col-span-4 xl:col-span-3 lg:sticky lg:top-20 space-y-4">
                <div class="x-rounded-card p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-line pb-2.5 px-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted">Mục lục hướng dẫn</span>
                        <span class="text-[10px] font-bold text-brand rounded bg-brand-soft px-1.5 py-0.5">3 phần</span>
                    </div>

                    <nav class="space-y-1">
                        <button
                            v-for="s in sections"
                            :key="s.id"
                            type="button"
                            @click="scrollTo(s.id)"
                            class="w-full text-left flex items-start gap-3 p-2.5 rounded-xl text-xs transition group"
                            :class="activeSection === s.id
                                ? 'bg-brand/10 text-brand font-bold border border-brand/30 shadow-sm'
                                : 'text-muted hover:text-ink hover:bg-surface border border-transparent'"
                        >
                            <component
                                :is="s.icon"
                                class="w-4 h-4 mt-0.5 shrink-0 transition"
                                :class="activeSection === s.id ? 'text-brand' : 'text-muted group-hover:text-ink'"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-ink font-semibold group-hover:text-brand transition" :class="activeSection === s.id ? '!text-brand !font-bold' : ''">
                                    {{ s.title }}
                                </div>
                                <div class="text-[11px] text-muted truncate mt-0.5">
                                    {{ s.subtitle }}
                                </div>
                            </div>
                        </button>
                    </nav>
                </div>
            </aside>

            <!-- NỘI DUNG CHI TIẾT BÊN PHẢI -->
            <main class="lg:col-span-8 xl:col-span-9 space-y-6">
                <!-- SECTION 1: FLASH FIRMWARE -->
                <section id="sec-flash" class="x-rounded-card p-6 sm:p-8 space-y-6 scroll-mt-20">
                    <div class="flex items-center gap-3 border-b border-line pb-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-brand">
                            <BoltIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-ink">1. Nạp Firmware ESP32 (Web Flasher)</h2>
                            <p class="text-xs text-muted">Chuẩn bị phần cứng và nạp trực tiếp qua cổng USB trên trình duyệt</p>
                        </div>
                    </div>

                    <!-- Chuẩn bị phần cứng có link mua affiliate -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                <CheckCircleIcon class="w-4 h-4 text-emerald-500" />
                                Chuẩn bị phần cứng & linh kiện
                            </h3>
                            <span class="text-[11px] text-muted italic">Click để xem nơi mua linh kiện</span>
                        </div>

                        <div class="grid sm:grid-cols-3 gap-3 text-xs">
                            <!-- ESP32 DevKit V1 -->
                            <div class="p-3.5 rounded-xl border border-line bg-page flex flex-col justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-ink">ESP32 DevKit V1</span>
                                        <span class="text-[9px] font-bold rounded bg-brand-soft text-brand-strong px-1.5 py-0.5">Khuyên dùng</span>
                                    </div>
                                    <p class="text-muted leading-relaxed">
                                        Mạch ESP32 30 chân hoặc 38 chân tiêu chuẩn, ổn định, dễ đấu nối.
                                    </p>
                                </div>
                                <a
                                    :href="affiliateEsp32Devkit"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand/30 bg-surface px-2.5 py-1.5 font-bold text-brand hover:bg-brand hover:text-white transition shadow-sm"
                                >
                                    <ShoppingCartIcon class="w-3.5 h-3.5" />
                                    <span>Mua sản phẩm</span>
                                    <ArrowTopRightOnSquareIcon class="w-3 h-3" />
                                </a>
                            </div>

                            <!-- ESP32-C3 Super Mini -->
                            <div class="p-3.5 rounded-xl border border-line bg-page flex flex-col justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-ink">ESP32-C3 Super Mini</span>
                                        <span class="text-[9px] font-bold rounded bg-purple-500/10 text-purple-600 px-1.5 py-0.5">Gọn nhẹ</span>
                                    </div>
                                    <p class="text-muted leading-relaxed">
                                        Kích thước siêu nhỏ, tích hợp cổng Type-C, thích hợp đặt kín trong tủ điện.
                                    </p>
                                </div>
                                <a
                                    :href="affiliateEsp32C3"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand/30 bg-surface px-2.5 py-1.5 font-bold text-brand hover:bg-brand hover:text-white transition shadow-sm"
                                >
                                    <ShoppingCartIcon class="w-3.5 h-3.5" />
                                    <span>Mua sản phẩm</span>
                                    <ArrowTopRightOnSquareIcon class="w-3 h-3" />
                                </a>
                            </div>

                            <!-- Cáp USB Type-C / Micro -->
                            <div class="p-3.5 rounded-xl border border-line bg-page flex flex-col justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-ink">Cáp USB truyền dữ liệu</span>
                                        <span class="text-[9px] font-bold rounded bg-blue-500/10 text-blue-600 px-1.5 py-0.5">Data Cable</span>
                                    </div>
                                    <p class="text-muted leading-relaxed">
                                        Cáp Type-C hoặc Micro-USB có <strong>hỗ trợ truyền dữ liệu</strong> (không dùng cáp chỉ sạc).
                                    </p>
                                </div>
                                <a
                                    :href="affiliateCable"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand/30 bg-surface px-2.5 py-1.5 font-bold text-brand hover:bg-brand hover:text-white transition shadow-sm"
                                >
                                    <ShoppingCartIcon class="w-3.5 h-3.5" />
                                    <span>Mua sản phẩm</span>
                                    <ArrowTopRightOnSquareIcon class="w-3 h-3" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Link duy nhất đến trang Flash -->
                    <div class="rounded-xl border border-brand/30 bg-brand-soft/20 p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="space-y-1 text-center sm:text-left">
                            <h4 class="text-sm font-bold text-ink">Bạn đã có bo ESP32 và cab USB?</h4>
                            <p class="text-xs text-muted">
                                Kết nối vi điều khiển ESP32 với máy tính qua cổng USB và nạp firmware trực tiếp trên trình duyệt Chrome / Edge.
                            </p>
                        </div>
                        <RouterLink
                            to="/flash"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-brand/25 hover:brightness-110 transition shrink-0"
                        >
                            <BoltIcon class="w-4 h-4" />
                            <span>Nạp Firmware ngay</span>
                            <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                        </RouterLink>
                    </div>
                </section>

                <!-- SECTION 2: CONNECT & CONFIGURE ESP32 -->
                <section id="sec-config" class="x-rounded-card p-6 sm:p-8 space-y-8 scroll-mt-20">
                    <div class="flex items-center gap-3 border-b border-line pb-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500">
                            <WifiIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-ink">2. Hướng dẫn kết nối và cấu hình ESP32</h2>
                            <p class="text-xs text-muted">Trình tự cài đặt WiFi gia đình, truy cập Dashboard nội bộ, cấu hình Dongle và lấy mã xác thực Cloud</p>
                        </div>
                    </div>

                    <!-- QUY TRÌNH 4 BƯỚC LIÊN HOÀN -->
                    <div class="space-y-6">
                        <!-- BƯỚC 1 -->
                        <div class="rounded-xl border border-line bg-page p-5 space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-xs font-bold text-white shadow-sm shadow-blue-500/30">1</span>
                                <h3 class="text-sm sm:text-base font-bold text-ink">Kết nối vào WiFi phát từ ESP32 (Chế độ cấu hình ban đầu)</h3>
                            </div>
                            <p class="text-xs text-muted leading-relaxed pl-10">
                                Cấp nguồn cho ESP32 bằng cáp USB hoặc adapter 5V. Khi chưa có WiFi gia đình, bo mạch sẽ tự động phát mạng Access Point (AP) riêng để bạn truy cập cài đặt:
                            </p>
                            <div class="grid sm:grid-cols-2 gap-3 pl-10 text-xs">
                                <div class="p-3 rounded-lg border border-line bg-surface space-y-1">
                                    <span class="text-muted block text-[11px] font-medium">Tên WiFi (SSID):</span>
                                    <div class="flex items-center justify-between font-mono font-bold text-brand text-sm">
                                        <span>LUX + MAC (VD: LUXCC8DA2C7C388)</span>
                                        <button
                                            type="button"
                                            @click="copyText('LUX', 'tiền tố SSID')"
                                            class="text-muted hover:text-ink p-1"
                                            title="Sao chép"
                                        >
                                            <DocumentDuplicateIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <span class="text-[10px] text-muted italic">(Bắt đầu bằng chữ LUX theo sau là địa chỉ MAC của ESP32)</span>
                                </div>
                                <div class="p-3 rounded-lg border border-line bg-surface space-y-1">
                                    <span class="text-muted block text-[11px] font-medium">Mật khẩu mặc định:</span>
                                    <div class="flex items-center justify-between font-mono font-bold text-ink text-sm">
                                        <span>12345678</span>
                                        <button
                                            type="button"
                                            @click="copyText('12345678', 'mật khẩu')"
                                            class="text-muted hover:text-ink p-1"
                                            title="Sao chép"
                                        >
                                            <DocumentDuplicateIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <span class="text-[10px] text-muted italic">(Dùng để kết nối điện thoại hoặc laptop vào mạch)</span>
                                </div>
                            </div>
                            <div class="pl-10 text-xs text-muted">
                                Sau khi điện thoại hoặc laptop đã kết nối vào mạng WiFi phát từ ESP32, mở trình duyệt web và truy cập:
                                <div class="mt-1.5 inline-flex items-center gap-2 font-mono font-bold text-brand bg-surface px-3 py-1.5 rounded-lg border border-line">
                                    <span>http://192.168.9.1</span>
                                    <button
                                        type="button"
                                        @click="copyText('http://192.168.9.1', 'địa chỉ web AP')"
                                        class="text-muted hover:text-ink ml-1"
                                        title="Sao chép"
                                    >
                                        <DocumentDuplicateIcon class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <span class="block mt-1 text-[11px] text-muted italic">(hoặc http://192.168.4.1 đối với các bản firmware cũ)</span>
                            </div>
                        </div>

                        <!-- BƯỚC 2 -->
                        <div class="rounded-xl border border-line bg-page p-5 space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-xs font-bold text-white shadow-sm shadow-blue-500/30">2</span>
                                <h3 class="text-sm sm:text-base font-bold text-ink">Cài đặt WiFi nhà, chờ đếm ngược & truy cập IP mới</h3>
                            </div>
                            <div class="pl-10 space-y-3 text-xs sm:text-sm text-muted">
                                <div class="flex items-start gap-2">
                                    <span class="font-bold text-ink shrink-0">• Thao tác:</span>
                                    <span>
                                        Trên giao diện web <code class="font-mono text-ink">192.168.9.1</code>, bấm nút <strong>"Quét"</strong> → Chọn tên WiFi gia đình của bạn từ danh sách (lưu ý: <strong>chỉ chọn mạng băng tần 2.4GHz</strong>) → Nhập mật khẩu WiFi → Bấm <strong>"Kết Nối"</strong>.
                                    </span>
                                </div>

                                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 space-y-2 text-xs">
                                    <div class="flex items-center gap-2 font-bold text-emerald-600 dark:text-emerald-400">
                                        <CheckCircleIcon class="w-4 h-4 shrink-0" />
                                        <span>Màn hình hiển thị: ✅ Kết nối thành công! & Bắt đầu đếm ngược 10 giây</span>
                                    </div>
                                    <p class="text-muted leading-relaxed">
                                        Trang web sẽ hiển thị ngay <strong>Địa chỉ IP mới</strong> mà router nhà cấp cho ESP32 (ví dụ: <code class="font-mono text-brand font-bold">http://192.168.1.50</code>) và đồng hồ đếm ngược 10 giây để ESP32 khởi động lại, chuyển sang chế độ mạng gia đình và tắt trạm phát AP.
                                    </p>
                                    <div class="p-2.5 rounded-lg border border-brand/30 bg-surface flex items-start gap-2 text-ink">
                                        <span class="text-base">📱</span>
                                        <div>
                                            <strong class="text-brand">Chuyển đổi kết nối ngay:</strong>
                                            <p class="text-muted text-[11px] mt-0.5">
                                                Hãy chuyển WiFi của điện thoại/máy tính về lại <strong>WiFi gia đình</strong>. Khi hết thời gian đếm ngược (hoặc bấm nút <em>"🚀 Chuyển đến Dashboard"</em>), bạn truy cập vào <strong>địa chỉ IP mới của ESP32</strong> (ví dụ: <code class="font-mono text-ink">http://192.168.1.50</code>).
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BƯỚC 3 -->
                        <div class="rounded-xl border border-line bg-page p-5 space-y-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-500 text-xs font-bold text-white shadow-sm shadow-blue-500/30">3</span>
                                <h3 class="text-sm sm:text-base font-bold text-ink">Mở menu "Cài đặt Dongle" & Điền 3 thông số biến tần</h3>
                            </div>
                            <div class="pl-10 space-y-3">
                                <p class="text-xs text-muted leading-relaxed">
                                    Khi đã vào được giao diện Dashboard của ESP32 qua IP mới trong mạng nhà, mở menu thanh điều hướng bên trái và click vào mục <strong>"Cài đặt Dongle"</strong>. Bạn cần điền đầy đủ 3 thông số định danh:
                                </p>

                                <div class="grid sm:grid-cols-3 gap-3 text-xs">
                                    <!-- IP Dongle -->
                                    <div class="p-3.5 rounded-xl border border-line bg-surface space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-blue-500 uppercase tracking-wider text-[11px]">1. Địa chỉ IP Dongle</span>
                                        </div>
                                        <p class="text-muted text-[11px] leading-relaxed">
                                            IP nội bộ của chiếc Dongle WiFi cắm dưới đáy biến tần khi Dongle nối vào router nhà (VD: <strong class="text-ink font-mono">192.168.1.100</strong>).
                                        </p>
                                        <div class="text-[10px] text-muted border-t border-line/60 pt-1.5">
                                            💡 Xem trong mục DHCP Client của Modem hoặc app quét LAN <em>Fing</em>.
                                        </div>
                                    </div>

                                    <!-- Dongle SN -->
                                    <div class="p-3.5 rounded-xl border border-line bg-surface space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-purple-500 uppercase tracking-wider text-[11px]">2. Serial Dongle WiFi</span>
                                        </div>
                                        <p class="text-muted text-[11px] leading-relaxed">
                                            Số sê-ri in trên tem vỏ của chiếc Dongle WiFi (VD: <strong class="text-ink font-mono">DG44302247</strong> hoặc <strong class="text-ink font-mono">BA...</strong>).
                                        </p>
                                        <div class="text-[10px] text-muted border-t border-line/60 pt-1.5">
                                            💡 Xem trực tiếp trên tem nhãn dán ở mặt ngoài Dongle.
                                        </div>
                                    </div>

                                    <!-- Inverter SN -->
                                    <div class="p-3.5 rounded-xl border border-line bg-surface space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-brand uppercase tracking-wider text-[11px]">3. Serial Inverter</span>
                                        </div>
                                        <p class="text-muted text-[11px] leading-relaxed">
                                            Mã sê-ri gồm 10 chữ số của máy biến tần LuxPower (VD: <strong class="text-ink font-mono">4434280298</strong> hoặc <strong class="text-ink font-mono">4313800597</strong>).
                                        </p>
                                        <div class="text-[10px] text-muted border-t border-line/60 pt-1.5">
                                            💡 Xem trên tem kim loại bên hông máy hoặc màn hình biến tần.
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 text-xs text-ink font-medium pt-1">
                                    <span>👉 Sau khi điền đủ 3 thông số, nhấn nút <strong>"Lưu"</strong>. ESP32 sẽ ghi cấu hình vào bộ nhớ và bắt đầu kết nối đọc dữ liệu Modbus từ biến tần.</span>
                                </div>
                            </div>
                        </div>

                        <!-- BƯỚC 4 -->
                        <div class="rounded-xl border border-brand/30 bg-brand-soft/10 p-5 space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand text-xs font-bold text-white shadow-sm shadow-brand/30">4</span>
                                <h3 class="text-sm sm:text-base font-bold text-ink">Lấy thông tin xác thực Cloud (Serial Inverter & Device Code)</h3>
                            </div>
                            <div class="pl-10 space-y-3 text-xs sm:text-sm text-muted">
                                <p class="leading-relaxed">
                                    Ngay trên trang <strong>"Cài đặt Dongle"</strong> (cuộn xuống khung bên dưới) hoặc click vào menu <strong>"Thông tin thiết bị"</strong>, bạn sẽ thấy mục <strong>🔑 Xác thực Cloud (LuxMonitor)</strong>:
                                </p>

                                <div class="grid sm:grid-cols-2 gap-3 max-w-lg">
                                    <div class="p-3 rounded-lg border border-line bg-surface">
                                        <span class="text-[11px] text-muted block uppercase tracking-wider font-semibold">Serial Inverter</span>
                                        <span class="font-mono text-base font-bold text-ink">4434280298</span>
                                        <span class="text-[10px] text-muted block mt-0.5">(Số sê-ri 10 chữ số của biến tần)</span>
                                    </div>
                                    <div class="p-3 rounded-lg border border-line bg-surface">
                                        <span class="text-[11px] text-muted block uppercase tracking-wider font-semibold">Mã Thiết Bị (Device Code)</span>
                                        <span class="font-mono text-base font-bold text-brand tracking-widest">ABC123</span>
                                        <span class="text-[10px] text-muted block mt-0.5">(Gồm 6 ký tự viết hoa do Cloud cấp)</span>
                                    </div>
                                </div>

                                <div class="text-xs text-muted flex items-center gap-2">
                                    <span class="text-emerald-500 font-bold">● Đã kết nối</span>
                                    <span>Trạng thái Cloud MQTT báo đã kết nối nghĩa là ESP32 đã sẵn sàng đồng bộ dữ liệu.</span>
                                </div>

                                <div class="rounded-lg border border-brand/20 bg-surface p-3 text-xs font-medium text-ink flex items-center justify-between gap-3">
                                    <span>
                                        📌 <strong>Lưu ý:</strong> Hãy ghi lại <strong>Serial Inverter</strong> và <strong>Mã Thiết Bị (Device Code)</strong> này để thực hiện bước thêm thiết bị vào tài khoản theo dõi ở Mục 3 bên dưới!
                                    </span>
                                    <a
                                        href="#sec-claim"
                                        class="shrink-0 text-brand font-bold hover:underline"
                                    >
                                        Xem Mục 3 ↓
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Lưu ý & Reset nút BOOT -->
                        <div class="p-4 rounded-xl border border-red-500/25 bg-red-500/5 flex items-start gap-3 text-xs">
                            <ArrowPathIcon class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <strong class="text-red-600 dark:text-red-400">Cách đổi mạng WiFi hoặc Khôi phục cài đặt gốc (Reset WiFi):</strong>
                                <p class="text-muted leading-relaxed">
                                    Khi đổi tên WiFi nhà hoặc chuyển biến tần sang vị trí khác: Hãy nhấn và <strong>giữ nút BOOT trên bo mạch ESP32 trong 5 giây</strong>. Mạch sẽ tự động xóa WiFi cũ và phát lại mạng AP <code class="font-mono font-bold text-ink">LuxMonitor-XXXXXX</code> để bạn cài đặt lại từ đầu.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SECTION 3: CLAIM DEVICE (WEB) -->
                <section id="sec-claim" class="x-rounded-card p-6 sm:p-8 space-y-6 scroll-mt-20">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
                                <BookmarkSquareIcon class="w-6 h-6" />
                            </div>
                            <div>
                                <h2 class="text-lg sm:text-xl font-bold text-ink">3. Hướng dẫn thêm thiết bị vào danh sách theo dõi</h2>
                                <p class="text-xs text-muted">Kích hoạt và gán biến tần vào tài khoản cá nhân trên giao diện Web LuxMonitor</p>
                            </div>
                        </div>

                        <RouterLink
                            to="/"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-brand/40 bg-brand-soft/30 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand hover:text-white transition"
                        >
                            <span>Đến trang giám sát</span>
                            <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                        </RouterLink>
                    </div>

                    <!-- Nội dung thêm thiết bị trên Web -->
                    <div class="rounded-xl border border-line bg-page p-5 space-y-4">
                        <div class="flex items-center gap-2 text-brand font-bold text-sm">
                            <ComputerDesktopIcon class="w-5 h-5" />
                            <span>Quy trình kích hoạt trên Website LuxMonitor</span>
                        </div>

                        <ol class="space-y-3.5 text-xs sm:text-sm text-muted">
                            <li class="flex items-start gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand">1</span>
                                <div>
                                    <strong class="text-ink">Đăng nhập tài khoản:</strong> Đăng nhập vào hệ thống <RouterLink to="/" class="text-brand font-semibold underline">LuxMonitor</RouterLink>.
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand">2</span>
                                <div>
                                    <strong class="text-ink">Bấm Thêm thiết bị:</strong> Tại trang chính, bấm vào thanh chọn biến tần ở trên cùng và bấm nút <strong>"+ Thêm"</strong> (hoặc nhấn nút <em>"Thêm thiết bị ngay"</em> nếu là tài khoản mới chưa có thiết bị).
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand">3</span>
                                <div>
                                    <strong class="text-ink">Nhập thông tin xác thực:</strong>
                                    <div class="mt-2 space-y-2 p-3 rounded-lg border border-line bg-surface">
                                        <div class="flex items-center gap-2">
                                            <span class="w-32 shrink-0 font-bold text-ink">Serial thiết bị:</span>
                                            <span class="text-muted">Nhập 10 số Serial biến tần (ví dụ: <code class="font-mono text-ink">4313800597</code>)</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-32 shrink-0 font-bold text-ink">Mã thiết bị:</span>
                                            <span class="text-muted">Nhập mã xác thực 6 ký tự lấy từ trang web ESP32</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand">4</span>
                                <div>
                                    <strong class="text-ink">Hoàn tất kích hoạt:</strong> Bấm nút <strong>"Thêm thiết bị"</strong>. Hệ thống sẽ xác thực và liên kết biến tần vào tài khoản của bạn ngay lập tức, sơ đồ năng lượng sẽ bắt đầu cập nhật dữ liệu thời gian thực.
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand">5</span>
                                <div>
                                    <strong class="text-ink">Ghim làm thiết bị mặc định:</strong> Bấm vào biểu tượng ngôi sao <StarIcon class="w-4 h-4 inline text-amber-500" /> bên cạnh tên biến tần để hệ thống luôn tự động mở biến tần này mỗi khi bạn đăng nhập.
                                </div>
                            </li>
                        </ol>
                    </div>

                    <!-- Bảo mật -->
                    <div class="p-4 rounded-xl border border-line bg-surface space-y-2 text-xs">
                        <span class="font-bold text-ink flex items-center gap-1.5">
                            <ShieldCheckIcon class="w-4 h-4 text-emerald-500" />
                            Bảo mật quyền sở hữu thiết bị
                        </span>
                        <p class="text-muted leading-relaxed">
                            Mã thiết bị (Device Code) được sinh ngẫu nhiên và lưu an toàn trên bộ nhớ của ESP32. Chỉ người có quyền truy cập trực tiếp vào mạng nội bộ của ESP32 mới có thể đọc được mã này. Sau khi một thiết bị được liên kết vào tài khoản, người dùng khác sẽ không thể liên kết lại trừ khi chủ sở hữu bấm <em>"Hủy theo dõi"</em>.
                        </p>
                    </div>
                </section>

                <!-- THÔNG TIN TÁC GIẢ & DONATE -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                    <!-- Thông tin tác giả -->
                    <div class="x-rounded-card p-5 space-y-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ink mb-3">
                                <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                <span>Cần thêm sự hỗ trợ kỹ thuật? Liên hệ</span>
                            </div>
                            
                            <div class="text-xs space-y-2.5 text-muted">
                                <div class="flex items-center gap-2">
                                    <span class="w-24 shrink-0 font-medium">Tác giả:</span>
                                    <span class="font-bold text-brand">Dương Xuân Giang</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-24 shrink-0 font-medium">SĐT / Zalo:</span>
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
                                    <span class="w-24 shrink-0 font-medium">Facebook:</span>
                                    <a href="https://www.facebook.com/leopotato89" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#1877F2] hover:underline flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                        facebook.com/leopotato89
                                    </a>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-24 shrink-0 font-medium">Email:</span>
                                    <a href="mailto:giangdx.tn@gmail.com" class="font-semibold text-ink hover:text-brand transition flex items-center gap-1">
                                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        giangdx.tn@gmail.com
                                    </a>
                                </div>
                            </div>
                        </div>

                        <p class="text-[11px] text-muted italic border-t border-line pt-2.5">
                            Cảm ơn bạn đã đồng hành cùng LuxMonitor. Chúc bạn có những trải nghiệm thật trọn vẹn và hiệu quả với hệ thống năng lượng xanh của mình!
                        </p>
                    </div>

                    <!-- QR Donate -->
                    <div class="x-rounded-card p-5 space-y-3 border-dashed border-line-strong flex flex-col justify-between">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-brand">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            <span>Donate ủng hộ tác giả</span>
                        </div>

                        <div class="flex items-center gap-4">
                            <img
                                src="https://img.vietqr.io/image/tpb-99325101989-qr_only.png"
                                alt="QR Donate"
                                class="w-24 h-24 sm:w-28 sm:h-28 rounded-lg border border-line bg-white p-1 shadow-sm shrink-0"
                            />
                            <div class="text-xs space-y-1.5 text-muted">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-16 shrink-0">Ngân hàng:</span>
                                    <span class="font-semibold text-ink flex items-center gap-1">
                                        <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/02/Icon-TPBank.png" class="w-3.5 h-3.5 object-contain" alt="TPBank" />
                                        TPBank
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-16 shrink-0">STK:</span>
                                    <button
                                        type="button"
                                        @click="copyText('99325101989', 'số tài khoản')"
                                        class="font-mono font-bold text-ink hover:text-brand flex items-center gap-1"
                                        title="Click để copy"
                                    >
                                        <span>99325101989</span>
                                        <DocumentDuplicateIcon class="w-3.5 h-3.5 text-muted" />
                                    </button>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-16 shrink-0">Chủ TK:</span>
                                    <span class="font-bold text-ink">DUONG XUAN GIANG</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-[11px] text-muted italic border-t border-line pt-2">
                            Mỗi sự ủng hộ là nguồn động lực lớn để tác giả tiếp tục duy trì và phát triển dự án.
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
