<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { createParticleEngine } from '../../composables/useParticleEngine';
import { useTelemetry } from '../../composables/useTelemetry';
import { useTween } from '../../composables/useTween';

const props = defineProps({
    serial: { type: String, required: true },
});

const { latest, online, timestamp, connected } = useTelemetry(props.serial);

/** Các khoá số cần đếm lên/xuống thay vì nhảy. */
const TWEEN_KEYS = [
    'pv_power',
    'pv1_power',
    'pv2_power',
    'accouple_power',
    'grid_power',
    'load_power',
    'inverter_power',
    'battery_power',
    'battery_current',
    'eps_power',
];

// Giá trị đích suy ra từ telemetry thô (cùng công thức bản gốc).
const targets = computed(() => {
    const l = latest.value ?? {};
    const batteryPower = Math.max(l.charge_power ?? 0, l.discharge_power ?? 0);

    return {
        pv_power: l.pv_power ?? 0,
        pv1_power: l.pv1_power ?? 0,
        pv2_power: l.pv2_power ?? 0,
        accouple_power: l.accouple_power ?? 0,
        grid_power: Math.max(l.export_power ?? 0, l.import_power ?? 0),
        load_power: (l.eps_power ?? 0) > 0 ? 0 : (l.load_power ?? 0),
        inverter_power: l.inverter_power ?? 0,
        battery_power: batteryPower,
        battery_current: batteryPower / (l.battery_voltage || 1),
        eps_power: l.eps_power ?? 0,
    };
});

const { values } = useTween(targets, TWEEN_KEYS);

/** Chiều cao phần màu của pin, tỷ lệ với % pin (mọc lên từ đáy y=408, tối đa 136px). */
const batteryFill = computed(() => 136 * (Math.min(100, Math.max(0, latest.value?.battery_soc ?? 0)) / 100));

const svgRef = ref(null);
let engine = null;

onMounted(() => {
    engine = createParticleEngine(svgRef.value);
    engine.setLatest(latest.value ?? {});
    engine.start();
});

// Nuôi engine bằng telemetry mới; KHÔNG tạo lại engine (tạo lại là SVG khởi động lại).
watch(latest, (value) => engine?.setLatest(value ?? {}));

onUnmounted(() => {
    engine?.stop();
    engine = null;
});

function fmt(value, decimals = 0) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const number = Number(value);

    return Number.isNaN(number)
        ? '—'
        : number.toLocaleString('en-US', { maximumFractionDigits: decimals, minimumFractionDigits: 0 });
}

function stateColor(state) {
    if (state === 1) {
        return '#ef4444'; // Fault
    }

    if (state === 0) {
        return '#9ca3af'; // Standby
    }

    if (state === 2) {
        return '#f59e0b'; // Programming
    }

    return '#22c55e'; // Đang hoạt động
}

function stateLabel(state) {
    const map = {
        0: 'Standby',
        1: 'Fault',
        2: 'Programming',
        4: 'PV nối lưới',
        8: 'PV sạc ắc quy',
        12: 'PV sạc + nối lưới',
        16: 'Ắc quy xả ra lưới',
        20: 'PV + Ắc quy xả ra lưới',
        32: 'Sạc AC từ lưới',
        40: 'PV + AC cùng sạc',
        64: 'Ắc quy chạy off-grid',
        96: 'Off-grid + sạc ắc quy',
        128: 'PV chạy off-grid',
        136: 'PV sạc + off-grid',
        192: 'PV + Ắc quy off-grid',
    };

    return map[state] || 'Không xác định';
}

function rssiLabel(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    if (value >= -50) {
        return 'Rất tốt';
    }

    if (value >= -60) {
        return 'Tốt';
    }

    if (value >= -67) {
        return 'Khá';
    }

    if (value >= -75) {
        return 'Trung bình';
    }

    return 'Yếu';
}
</script>

<template>
    <div class="diagram-container hide-scrollbar">
        <svg
            ref="svgRef"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 850 700"
            class="responsive-svg"
        >
            <!-- ===== DÂY KẾT NỐI (TĨNH) ===== -->
            <line x1="440" y1="140" x2="440" y2="238" class="wire-base" />
            <line x1="266" y1="200" x2="266" y2="470" class="wire-base" />
            <line x1="195" y1="340" x2="338" y2="340" class="wire-base" />
            <line x1="542" y1="340" x2="613" y2="340" class="wire-base" />
            <line x1="440" y1="442" x2="440" y2="530" class="wire-base" />

            <!-- ===== POOL HẠT ĐIỆN TÍCH — engine điều khiển vị trí/tốc độ ===== -->
            <g>
                <circle v-for="n in 5" :key="`ac${n}`" data-track="ac" class="particle particle-slot" r="3.5" />
                <circle v-for="n in 5" :key="`grid${n}`" data-track="grid" class="particle particle-slot" r="3.5" />
                <circle v-for="n in 5" :key="`inv${n}`" data-track="inv" class="particle particle-slot" r="3.5" />
                <circle v-for="n in 5" :key="`load${n}`" data-track="load" class="particle particle-slot" r="3.5" />
                <circle v-for="n in 3" :key="`pv${n}`" data-track="pv" class="particle-orange particle-slot" r="4" />
                <circle v-for="n in 4" :key="`batt${n}`" data-track="batt" class="particle-green particle-slot" r="4" />
                <circle v-for="n in 3" :key="`eps${n}`" data-track="eps" class="particle-purple particle-slot" r="4" />
            </g>

            <!-- ===== KHỐI 1: TẤM PIN ===== -->
            <g>
                <g transform="translate(355, 42)">
                    <path d="M 10 54 L 24 38 M 68 54 L 54 38" stroke="#0f172a" stroke-width="2" stroke-linecap="round" />
                    <line x1="20" y1="46" x2="58" y2="46" stroke="#0f172a" stroke-width="1.5" />
                    <line x1="6" y1="54" x2="72" y2="54" stroke="#0f172a" stroke-width="2.2" stroke-linecap="round" />
                    <polygon points="3,38 75,38 84,8 12,8" fill="#ffffff" stroke="#0f172a" stroke-width="2" stroke-linejoin="round" />
                    <polygon points="6,36 72,36 80,11 14,11" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1" />
                    <line x1="29" y1="10" x2="23" y2="36" stroke="#0f172a" stroke-width="1.2" />
                    <line x1="46" y1="10" x2="45" y2="36" stroke="#0f172a" stroke-width="1.2" />
                    <line x1="63" y1="10" x2="60" y2="36" stroke="#0f172a" stroke-width="1.2" />
                    <line x1="11" y1="19" x2="75" y2="19" stroke="#0f172a" stroke-width="1.2" />
                    <line x1="8" y1="28" x2="77" y2="28" stroke="#0f172a" stroke-width="1.2" />
                    <rect x="42" y="3" width="12" height="5" rx="1" fill="#0f172a" />
                    <g transform="translate(82, 4)">
                        <circle cx="8" cy="8" r="6.5" fill="#f59e0b" stroke="#0f172a" stroke-width="1.2" />
                        <path
                            d="M 8 -1 L 8 -3 M 8 17 L 8 19 M -1 8 L -3 8 M 17 8 L 19 8 M 1.5 1.5 L -0.5 -0.5 M 14.5 14.5 L 16.5 16.5 M 1.5 14.5 L -0.5 16.5 M 14.5 1.5 L 16.5 -0.5"
                            stroke="#ea580c"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                    </g>
                </g>

                <text x="465" y="73" font-size="18" font-weight="400">Tấm pin</text>
                <text x="465" y="103" font-size="28">
                    <tspan font-weight="700">{{ fmt(values.pv_power, 0) }}</tspan><tspan font-weight="400">W</tspan>
                </text>

                <text x="585" y="73" font-size="13">
                    <tspan font-weight="400">PV1: </tspan>
                    <tspan font-weight="700">{{ fmt(values.pv1_power, 0) }}</tspan><tspan font-weight="400">W </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.pv1_voltage, 1) }}</tspan><tspan font-weight="400">V </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.pv1_current, 1) }}</tspan><tspan font-weight="400">A</tspan>
                </text>
                <text x="585" y="93" font-size="13">
                    <tspan font-weight="400">PV2: </tspan>
                    <tspan font-weight="700">{{ fmt(values.pv2_power, 0) }}</tspan><tspan font-weight="400">W </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.pv2_voltage, 1) }}</tspan><tspan font-weight="400">V </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.pv2_current, 1) }}</tspan><tspan font-weight="400">A</tspan>
                </text>

                <text x="465" y="132" font-size="14">
                    <tspan font-weight="400">Hôm nay: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.pv_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
            </g>

            <!-- ===== KHỐI 2: AC COUPLE ===== -->
            <g>
                <g transform="translate(140, 105)">
                    <rect x="4" y="4" width="56" height="52" rx="8" fill="#ffffff" stroke="#0f172a" stroke-width="2" />
                    <line x1="12" y1="10" x2="52" y2="10" stroke="#0f172a" stroke-width="1.5" stroke-linecap="round" />
                    <circle cx="32" cy="34" r="18" stroke="#0f172a" stroke-width="1.5" fill="#f8fafc" />
                    <circle cx="12" cy="18" r="2" fill="#22c55e" />
                    <circle cx="18" cy="18" r="2" fill="#0284c7" />
                    <path d="M 18 34 Q 23 23, 28 34 T 38 34" class="icon-path" stroke-width="2" stroke="#0f172a" />
                    <path d="M 26 34 Q 31 45, 36 34 T 46 34" class="icon-path" stroke-width="1.8" stroke="#0284c7" />
                </g>

                <text x="220" y="128" font-size="18" font-weight="400">AC Couple</text>
                <text x="220" y="156" font-size="28">
                    <tspan font-weight="700">{{ fmt(values.accouple_power, 0) }}</tspan><tspan font-weight="400">W</tspan>
                </text>

                <text x="220" y="185" font-size="14">
                    <tspan font-weight="400">Hôm nay: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.accouple_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
            </g>

            <!-- ===== KHỐI 3: LƯỚI ĐIỆN ===== -->
            <g>
                <g transform="translate(15, 270)">
                    <line x1="4" y1="72" x2="66" y2="72" stroke="#0f172a" stroke-width="2" stroke-linecap="round" />
                    <path d="M 35 4 L 10 72 L 60 72 Z" class="icon-path" stroke-width="2" fill="#ffffff" />
                    <line x1="6" y1="20" x2="64" y2="20" class="icon-path" stroke-width="2" />
                    <line x1="12" y1="36" x2="58" y2="36" class="icon-path" stroke-width="2" />
                    <line x1="18" y1="52" x2="52" y2="52" class="icon-path" stroke-width="2" />
                    <line x1="9" y1="20" x2="9" y2="28" stroke="#0f172a" stroke-width="1.8" />
                    <line x1="61" y1="20" x2="61" y2="28" stroke="#0f172a" stroke-width="1.8" />
                    <line x1="15" y1="36" x2="15" y2="44" stroke="#0f172a" stroke-width="1.8" />
                    <line x1="55" y1="36" x2="55" y2="44" stroke="#0f172a" stroke-width="1.8" />
                    <line x1="27" y1="20" x2="43" y2="36" stroke="#0f172a" stroke-width="1" />
                    <line x1="43" y1="20" x2="27" y2="36" stroke="#0f172a" stroke-width="1" />
                    <line x1="22" y1="36" x2="48" y2="52" stroke="#0f172a" stroke-width="1" />
                    <line x1="48" y1="36" x2="22" y2="52" stroke="#0f172a" stroke-width="1" />
                    <line x1="16" y1="52" x2="54" y2="72" stroke="#0f172a" stroke-width="1" />
                    <line x1="54" y1="52" x2="16" y2="72" stroke="#0f172a" stroke-width="1" />
                    <line x1="35" y1="4" x2="35" y2="-2" stroke="#0f172a" stroke-width="2" stroke-linecap="round" />
                </g>

                <text x="15" y="365" font-size="14">
                    <tspan font-weight="700">{{ fmt(latest?.grid_voltage, 1) }}</tspan><tspan font-weight="400">Vac</tspan>
                </text>
                <text x="15" y="383" font-size="14">
                    <tspan font-weight="700">{{ fmt(latest?.grid_frequency, 1) }}</tspan><tspan font-weight="400">Hz</tspan>
                </text>

                <text x="98" y="290" font-size="18" font-weight="400">Lưới điện</text>
                <text x="98" y="318" font-size="28">
                    <tspan font-weight="700">{{ fmt(values.grid_power, 0) }}</tspan><tspan font-weight="400">W</tspan>
                </text>

                <text x="98" y="350" font-size="15" font-weight="400">Hôm nay</text>
                <text x="98" y="368" font-size="13">
                    <tspan font-weight="400">Lấy lưới: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.import_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
                <text x="98" y="385" font-size="13">
                    <tspan font-weight="400">Đẩy lưới: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.export_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
            </g>

            <!-- ===== KHỐI 4: TẢI SỬ DỤNG ===== -->
            <g>
                <g transform="translate(125, 475)">
                    <path d="M 2 28 L 35 2 L 68 28" fill="none" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M 10 25 L 10 56 L 60 56 L 60 25" fill="#ffffff" stroke="#0f172a" stroke-width="2" stroke-linejoin="round" />
                    <rect x="46" y="8" width="6" height="10" fill="#ffffff" stroke="#0f172a" stroke-width="1.5" />
                    <rect x="28" y="36" width="14" height="20" rx="1" class="icon-path" stroke-width="1.8" />
                    <circle cx="39" cy="46" r="1.2" fill="#0f172a" />
                    <rect x="16" y="32" width="8" height="9" rx="1" stroke="#0f172a" stroke-width="1.2" fill="#f8fafc" />
                    <line x1="20" y1="32" x2="20" y2="41" stroke="#0f172a" stroke-width="0.8" />
                    <rect x="46" y="32" width="8" height="9" rx="1" stroke="#0f172a" stroke-width="1.2" fill="#f8fafc" />
                    <line x1="50" y1="32" x2="50" y2="41" stroke="#0f172a" stroke-width="0.8" />
                    <path d="M 37 10 L 30 21 L 36 21 L 32 29 L 41 17 L 36 17 Z" fill="#eab308" stroke="#0f172a" stroke-width="0.8" />
                </g>

                <text x="220" y="498" font-size="18" font-weight="400">Tải sử dụng</text>
                <text x="220" y="526" font-size="28">
                    <tspan font-weight="700">{{ fmt(values.load_power, 0) }}</tspan><tspan font-weight="400">W</tspan>
                </text>

                <text x="220" y="556" font-size="14">
                    <tspan font-weight="400">Hôm nay: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.load_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
            </g>

            <!-- ===== KHỐI TRUNG TÂM: BIẾN TẦN ===== -->
            <g>
                <rect x="338" y="240" width="204" height="200" class="main-box" />
                <rect x="352" y="254" width="176" height="142" class="screen-box" />

                <text x="440" y="280" font-size="18" font-weight="400" text-anchor="middle">Biến tần</text>
                <text x="440" y="310" font-size="28" text-anchor="middle">
                    <tspan font-weight="700">{{ fmt(values.inverter_power, 0) }}</tspan><tspan font-weight="400">W</tspan>
                </text>

                <text x="364" y="345" font-size="13"><tspan font-weight="400">Bên trong:</tspan></text>
                <text x="516" y="345" font-size="13" text-anchor="end">
                    <tspan font-weight="700">{{ fmt(latest?.inner_temp, 1) }}</tspan><tspan font-weight="400">°C</tspan>
                </text>

                <text x="364" y="363" font-size="13"><tspan font-weight="400">Cảm biến 1:</tspan></text>
                <text x="516" y="363" font-size="13" text-anchor="end">
                    <tspan font-weight="700">{{ fmt(latest?.radiator1_temp, 1) }}</tspan><tspan font-weight="400">°C</tspan>
                </text>

                <text x="364" y="381" font-size="13"><tspan font-weight="400">Cảm biến 2:</tspan></text>
                <text x="516" y="381" font-size="13" text-anchor="end">
                    <tspan font-weight="700">{{ fmt(latest?.radiator2_temp, 1) }}</tspan><tspan font-weight="400">°C</tspan>
                </text>

                <!-- Nút trạng thái: màu theo chế độ hoạt động, tooltip là nhãn trạng thái -->
                <circle cx="520" cy="418" r="9" :fill="stateColor(latest?.state)" :stroke="stateColor(latest?.state)" stroke-width="1.5">
                    <title>{{ stateLabel(latest?.state) }}</title>
                </circle>
                <text x="465" y="422" font-size="11" fill="#475569" text-anchor="end">
                    <tspan font-weight="600">S/N: </tspan>
                    <tspan font-weight="700">{{ serial }}</tspan>
                </text>
            </g>

            <!-- ===== KHỐI 5: PIN LƯU TRỮ ===== -->
            <g>
                <g>
                    <rect x="628" y="260" width="24" height="8" rx="3" fill="#0f172a" />
                    <rect x="615" y="268" width="50" height="144" rx="8" ry="8" fill="#ffffff" stroke="#0f172a" stroke-width="2" />
                    <rect
                        x="619"
                        width="42"
                        rx="5"
                        fill="#22c55e"
                        :y="408 - batteryFill"
                        :height="batteryFill"
                    />
                    <text x="640" y="340" font-size="15" font-weight="700" text-anchor="middle" fill="#0f172a">
                        {{ fmt(latest?.battery_soc, 0) }}%
                    </text>
                </g>

                <text x="677" y="250" font-size="18" font-weight="400">Pin lưu trữ</text>
                <text x="677" y="285" font-size="28">
                    <tspan font-weight="700">{{ fmt(values.battery_power, 0) }}</tspan><tspan font-weight="400">W </tspan>
                </text>

                <text x="680" y="308" font-size="28">
                    <!-- Dòng pin = Công suất ÷ Điện áp, để luôn khớp với số W hiển thị -->
                    <tspan font-weight="700" font-size="18">{{ fmt(values.battery_current, 1) }}</tspan><tspan font-weight="400" font-size="18">A</tspan>
                </text>

                <text x="680" y="328" font-size="14">
                    <tspan font-weight="700">{{ fmt(latest?.battery_voltage, 1) }}</tspan><tspan font-weight="400">Vdc</tspan>
                </text>
                <text x="677" y="356" font-size="14">
                    <tspan font-weight="400">Nhiệt độ: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.battery_temp, 1) }}</tspan><tspan font-weight="400">°C</tspan>
                </text>
                <text x="677" y="374" font-size="14">
                    <tspan font-weight="400">DL: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.battery_count, 0) }}</tspan><tspan font-weight="400"> khối ~ </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.battery_capacity, 0) }}</tspan><tspan font-weight="400">Ah</tspan>
                </text>

                <text x="677" y="400" font-size="15" font-weight="900">Hôm nay</text>
                <text x="677" y="419" font-size="13">
                    <tspan font-weight="400">Sạc: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.charge_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
                <text x="677" y="436" font-size="13">
                    <tspan font-weight="400">Xả: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.discharge_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
            </g>

            <!-- ===== KHỐI 6: NGUỒN DỰ PHÒNG ===== -->
            <g>
                <g transform="translate(405, 535)">
                    <rect x="4" y="12" width="58" height="36" rx="5" class="icon-path" stroke-width="2" fill="#ffffff" />
                    <circle cx="23" cy="30" r="10" class="icon-path" stroke-width="1.8" fill="#f8fafc" />
                    <text x="23" y="34" font-size="12" font-weight="700" text-anchor="middle" fill="#0f172a">G</text>
                    <line x1="42" y1="18" x2="54" y2="18" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="42" y1="24" x2="54" y2="24" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="42" y1="30" x2="54" y2="30" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                    <line x1="42" y1="36" x2="54" y2="36" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M 12 12 L 12 5 L 19 5" stroke="#0f172a" stroke-width="2" stroke-linecap="round" fill="none" />
                    <path d="M 24 12 L 24 8 L 38 8 L 38 12" stroke="#0f172a" stroke-width="1.5" fill="none" stroke-linejoin="round" />
                    <rect x="8" y="48" width="12" height="4" rx="1" fill="#0f172a" />
                    <rect x="46" y="48" width="12" height="4" rx="1" fill="#0f172a" />
                </g>

                <text x="375" y="618" font-size="14">
                    <tspan font-weight="700">{{ fmt(latest?.eps_voltage, 1) }}</tspan><tspan font-weight="400">Vac</tspan>
                </text>
                <text x="375" y="636" font-size="14">
                    <tspan font-weight="700">{{ fmt(latest?.eps_frequency, 1) }}</tspan><tspan font-weight="400">Hz</tspan>
                </text>

                <text x="485" y="558" font-size="18" font-weight="400">Nguồn dự phòng</text>
                <text x="485" y="586" font-size="28">
                    <tspan font-weight="700">{{ fmt(values.eps_power, 0) }}</tspan><tspan font-weight="400">W</tspan>
                </text>

                <text x="485" y="628" font-size="14">
                    <tspan font-weight="400">Hôm nay: </tspan>
                    <tspan font-weight="700">{{ fmt(latest?.eps_energy_day, 1) }}</tspan><tspan font-weight="400">kWh</tspan>
                </text>
            </g>

            <!-- ===== RSSI · Cập nhật ===== -->
            <g>
                <g transform="translate(30, 600)" fill="none" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M 2 7 A 14 14 0 0 1 22 7" />
                    <path d="M 6 11 A 8 8 0 0 1 18 11" />
                    <circle cx="12" cy="17" r="1.6" fill="#0f172a" stroke="none" />
                </g>

                <text x="60" y="620" font-size="14" fill="#0f172a">
                    <tspan font-weight="700">{{ fmt(latest?.rssi, 0) }}</tspan><tspan font-weight="400">dBm</tspan>
                    <tspan font-weight="400" fill="#0f172a">  (</tspan><tspan font-weight="700" fill="#0f172a">{{ rssiLabel(latest?.rssi) }}</tspan><tspan font-weight="400" fill="#0f172a">)</tspan>
                </text>
                <text x="31" y="638" font-size="12" fill="#0f172a">
                    <tspan font-weight="400">Wifi thiết bị giám sát</tspan>
                </text>
            </g>

            <text x="30" y="669" font-size="14" fill="#475569">
                <tspan font-weight="400">Cập nhật: </tspan>
                <tspan font-weight="700">{{ timestamp ?? '—' }}</tspan>
            </text>
        </svg>
    </div>
</template>

<style scoped>
    /* ==========================================================
       Sơ đồ năng lượng realtime
       ----------------------------------------------------------
       Màu trong SVG gốc là presentation attribute (fill="#0f172a", …).
       CSS luôn thắng presentation attribute ⇒ các selector thuộc tính bên
       dưới đổi được toàn bộ sơ đồ theo theme mà KHÔNG phải sửa ~450 dòng
       markup SVG. (Cố tình viết tay, không dùng @apply — thuộc tính SVG
       không phải utility Tailwind.)
       ========================================================== */
    .diagram-container {
        position: relative;
        width: 100%;
        overflow: hidden;
    }

    .diagram-status {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 0.5rem 1rem;
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid var(--bord);
        font-size: 0.75rem;
        color: var(--muted);
    }

    .diagram-container {
        width: 100%;
        overflow-x: auto;
        /* Hide scrollbar for cleaner look if desired, but default is fine */
    }

    .responsive-svg {
        display: block;
        width: 100%;
        height: auto;
        max-height: 55vh;
    }


    .diagram-container text {
        font-family: var(--font-sans);
        fill: var(--diagram-ink);
    }

    /* Chữ nhỏ trong sơ đồ +2px cho dễ đọc (cỡ lớn giữ nguyên) */
    .diagram-container text[font-size='11'] {
        font-size: 13px;
    }

    .diagram-container text[font-size='12'] {
        font-size: 14px;
    }

    .diagram-container text[font-size='13'] {
        font-size: 15px;
    }

    .diagram-container text[font-size='14'] {
        font-size: 16px;
    }

    .diagram-container text[font-size='15'] {
        font-size: 17px;
    }

    .main-box {
        fill: var(--diagram-paper);
        stroke: var(--diagram-ink);
        stroke-width: 2;
        rx: 24px;
        ry: 24px;
    }

    .screen-box {
        fill: var(--diagram-screen);
        stroke: var(--diagram-ink);
        stroke-width: 1.5;
        rx: 12px;
        ry: 12px;
    }

    .wire-base {
        stroke: var(--diagram-ink);
        stroke-width: 2;
        fill: none;
    }

    .icon-path {
        fill: none;
        stroke: var(--diagram-ink);
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .particle {
        fill: #0284c7;
        filter: drop-shadow(0 0 2px #0369a1);
    }

    .particle-orange {
        fill: #ea580c;
        filter: drop-shadow(0 0 2px #c2410c);
    }

    .particle-purple {
        fill: #9333ea;
        filter: drop-shadow(0 0 2px #7e22ce);
    }

    .particle-green {
        fill: #16a34a;
        filter: drop-shadow(0 0 2px #15803d);
    }

    /* Hạt nằm trong pool: ẩn mặc định, engine bật khi có hạt chạy */
    .particle-slot {
        visibility: hidden;
    }

    /* Đổi màu cứng của SVG theo theme */
    .diagram-container [stroke='#0f172a'] {
        stroke: var(--diagram-ink);
    }

    .diagram-container [fill='#0f172a'] {
        fill: var(--diagram-ink);
    }

    .diagram-container [stroke='#ffffff'] {
        stroke: var(--diagram-paper);
    }

    .diagram-container [fill='#ffffff'] {
        fill: var(--diagram-paper);
    }

    .diagram-container [fill='#f8fafc'] {
        fill: var(--diagram-screen);
    }

    .diagram-container [stroke='#e2e8f0'] {
        stroke: var(--diagram-line);
    }
</style>    