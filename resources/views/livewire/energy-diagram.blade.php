@php
    $latest = $latest ?? [];
    $serial = $serial ?? null;
@endphp

@once
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    .diagram-container {
        position: relative;
        background-color: #ffffff;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        border-radius: 12px;
        overflow: hidden;
        width: 100%;
        /*max-height: 80rem;*/
        /*max-width: 1080px;*/
        padding: 2px;
        box-sizing: border-box;
        border: 1px solid #e5e7eb;
    }

    .responsive-svg {
        width: 100%;
        height: auto;
        display: block;
        max-height: 60vh;
    }

    .grid-background {
        background-size: 20px 20px;
        background-image:
            linear-gradient(to right, #e2e8f0 1px, transparent 1px),
            linear-gradient(to bottom, #e2e8f0 1px, transparent 1px);
    }

    .diagram-container text {
        font-family: 'Inter', -apple-system, sans-serif;
        fill: #0f172a;
    }

    .main-box { fill: #ffffff; stroke: #0f172a; stroke-width: 2; rx: 24px; ry: 24px; }
    .screen-box { fill: #f8fafc; stroke: #0f172a; stroke-width: 1.5; rx: 12px; ry: 12px; }
    .wire-base { stroke: #0f172a; stroke-width: 2; fill: none; }
    .junction-dot { fill: #0f172a; }
    .particle { fill: #0284c7; filter: drop-shadow(0px 0px 2px #0369a1); }
    .particle-orange { fill: #ea580c; filter: drop-shadow(0px 0px 2px #c2410c); }
    .particle-purple { fill: #9333ea; filter: drop-shadow(0px 0px 2px #7e22ce); }
    .particle-green { fill: #16a34a; filter: drop-shadow(0px 0px 2px #15803d); }
    .icon-path { fill: none; stroke: #0f172a; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
</style>
@endonce

<div class="diagram-container">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1020 700" class="grid-background responsive-svg">

        <defs>
            <path id="path-pv-inverter" d="M 560,140 L 560,238" />
            <path id="path-ac-j" d="M 350,200 L 350,340" />
            <path id="path-grid-j" :d="(latest?.export_power ?? 0) > 0 ? 'M 350,340 L 215,340' : 'M 215,340 L 350,340'" />
            <!-- Hướng dây biến tần: inverter đang PHÁT (inverter_power > 0) → ĐI RA (458→350); ngược lại (nạp ắc quy từ lưới/AC) → ĐI VÀO (350→458) -->
            <path id="path-inv-j" :d="(latest?.inverter_power ?? 0) > 0 ? 'M 458,340 L 350,340' : 'M 350,340 L 458,340'" />
            <path id="path-j-load" d="M 350,340 L 350,470" />
            <!-- Hướng dây pin: SẠC đi VÀO pin (662→738), XẢ đi RA khỏi pin (738→662). Dựa theo công suất thực, không phải state. -->
            <path id="path-inverter-battery" :d="(latest?.charge_power ?? 0) > (latest?.discharge_power ?? 0) ? 'M 662,340 L 738,340' : 'M 738,340 L 662,340'" />
            <path id="path-inverter-backup" d="M 560,442 L 560,543" />
        </defs>

        <!-- ===== DÂY KẾT NỐI (TĨNH) ===== -->
        <line x1="560" y1="140" x2="560" y2="238" class="wire-base" />
        <line x1="350" y1="200" x2="350" y2="470" class="wire-base" />
        <line x1="215" y1="340" x2="458" y2="340" class="wire-base" />
        <circle cx="350" cy="340" r="4.5" class="junction-dot" />
        <line x1="662" y1="340" x2="738" y2="340" class="wire-base" />
        <line x1="560" y1="442" x2="560" y2="543" class="wire-base" />

        <!-- ===== HẠT Ở NGÃ 4 (AC, Lưới, Biến tần → VÀO ngã 4 đồng bộ; Tải ← RA khỏi ngã 4) ===== -->
        <circle r="3.5" class="particle" x-show="(latest?.accouple_power ?? 0) > 0"><animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#path-ac-j" /></animateMotion></circle>

        <circle r="3.5" class="particle" x-show="((latest?.export_power ?? 0) + (latest?.import_power ?? 0)) > 0"><animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#path-grid-j" /></animateMotion></circle>

        <circle r="3.5" class="particle" x-show="!latest?.eps_load_show && (((latest?.inverter_power ?? 0) > 0) || (((latest?.charge_power ?? 0) > 0) && (((latest?.import_power ?? 0) + (latest?.accouple_power ?? 0)) > 0)))"><animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#path-inv-j" /></animateMotion></circle>

        <circle r="3.5" class="particle" x-show="((latest?.eps_load_show ? 0 : latest?.load_power) ?? 0) > 0"><animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#path-j-load" /></animateMotion></circle>

        <!-- ===== CÁC HẠT KHÁC ===== -->
        <circle r="4" class="particle-orange" x-show="(latest?.pv_power ?? 0) > 0"><animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#path-pv-inverter" /></animateMotion></circle>

        <circle r="4" class="particle-green" x-show="((latest?.charge_power ?? 0) + (latest?.discharge_power ?? 0)) > 0"><animateMotion dur="1.2s" repeatCount="indefinite"><mpath href="#path-inverter-battery" /></animateMotion></circle>

        <circle r="4" class="particle-purple" x-show="(latest?.eps_load_show && (latest?.eps_power ?? 0) > 0)"><animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#path-inverter-backup" /></animateMotion></circle>


        <!-- ===== KHỐI 1: TẤM PIN ===== -->
        <g id="tam-pin">
            <g transform="translate(450, 42)">
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
                    <path d="M 8 -1 L 8 -3 M 8 17 L 8 19 M -1 8 L -3 8 M 17 8 L 19 8 M 1.5 1.5 L -0.5 -0.5 M 14.5 14.5 L 16.5 16.5 M 1.5 14.5 L -0.5 16.5 M 14.5 1.5 L 16.5 -0.5" stroke="#ea580c" stroke-width="1.8" stroke-linecap="round" />
                </g>
            </g>

            <text x="560" y="73" font-size="18" font-weight="400">Tấm pin</text>
            <text x="560" y="103" font-size="28">
                <tspan font-weight="800" x-text="fmt(latest?.pv_power, 0)">0</tspan><tspan font-weight="400">W</tspan>
            </text>

            <text x="680" y="73" font-size="13">
                <tspan font-weight="400">PV1: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.pv1_power, 0)">0</tspan><tspan font-weight="400">W </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.pv1_voltage, 1)">0</tspan><tspan font-weight="400">V </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.pv1_current, 1)">0</tspan><tspan font-weight="400">A</tspan>
            </text>
            <text x="680" y="93" font-size="13">
                <tspan font-weight="400">PV2: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.pv2_power, 0)">0</tspan><tspan font-weight="400">W </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.pv2_voltage, 1)">0</tspan><tspan font-weight="400">V </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.pv2_current, 1)">0</tspan><tspan font-weight="400">A</tspan>
            </text>

            <text x="560" y="132" font-size="14">
                <tspan font-weight="400">Hôm nay: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.pv_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
        </g>


        <!-- ===== KHỐI 2: AC COUPLE ===== -->
        <g id="acouple-top">
            <g transform="translate(235, 105)">
                <rect x="4" y="4" width="56" height="52" rx="8" fill="#ffffff" stroke="#0f172a" stroke-width="2" />
                <line x1="12" y1="10" x2="52" y2="10" stroke="#0f172a" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="32" cy="34" r="18" stroke="#0f172a" stroke-width="1.5" fill="#f8fafc" />
                <circle cx="12" cy="18" r="2" fill="#22c55e" />
                <circle cx="18" cy="18" r="2" fill="#0284c7" />
                <path d="M 18 34 Q 23 23, 28 34 T 38 34" class="icon-path" stroke-width="2" stroke="#0f172a" />
                <path d="M 26 34 Q 31 45, 36 34 T 46 34" class="icon-path" stroke-width="1.8" stroke="#0284c7" />
            </g>

            <text x="315" y="128" font-size="18" font-weight="400">AC Couple</text>
            <text x="315" y="156" font-size="28">
                <tspan font-weight="800" x-text="fmt(latest?.accouple_power, 0)">0</tspan><tspan font-weight="400">W</tspan>
            </text>

            <text x="315" y="185" font-size="14">
                <tspan font-weight="400">Hôm nay: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.accouple_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
        </g>


        <!-- ===== KHỐI 3: LƯỚI ĐIỆN ===== -->
        <g id="luoi-dien">
            <g transform="translate(25, 270)">
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

            <text x="25" y="365" font-size="14">
                <tspan font-weight="700" x-text="fmt(latest?.gen_voltage, 1)">0</tspan><tspan font-weight="400">Vac</tspan>
            </text>
            <text x="25" y="383" font-size="14">
                <tspan font-weight="700" x-text="fmt(latest?.gen_frequency, 1)">0</tspan><tspan font-weight="400">Hz</tspan>
            </text>

            <text x="115" y="290" font-size="18" font-weight="400">Lưới điện</text>
            <text x="115" y="318" font-size="28">
                <tspan font-weight="800" x-text="fmt(Math.max(latest?.export_power ?? 0, latest?.import_power ?? 0), 0)">0</tspan><tspan font-weight="400">W</tspan>
            </text>

            <text x="115" y="350" font-size="15" font-weight="400">Hôm nay</text>
            <text x="115" y="368" font-size="13">
                <tspan font-weight="400">Lấy lưới: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.import_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
            <text x="115" y="385" font-size="13">
                <tspan font-weight="400">Đẩy lưới: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.export_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
        </g>


        <!-- ===== KHỐI 4: TẢI SỬ DỤNG ===== -->
        <g id="tai-su-dung">
            <g transform="translate(220, 475)">
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

            <text x="315" y="498" font-size="18" font-weight="400">Tải sử dụng</text>
            <text x="315" y="526" font-size="28">
                <tspan font-weight="800" x-text="fmt((latest?.eps_load_show ? 0 : latest?.load_power) ?? 0, 0)">0</tspan><tspan font-weight="400">W</tspan>
            </text>

            <text x="315" y="556" font-size="14">
                <tspan font-weight="400">Hôm nay: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.load_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
        </g>


        <!-- ===== KHỐI TRUNG TÂM: BIẾN TẦN ===== -->
        <g id="bien-tan">
            <rect x="458" y="240" width="204" height="200" class="main-box" />
            <rect x="472" y="254" width="176" height="142" class="screen-box" />

            <text x="560" y="280" font-size="18" font-weight="400" text-anchor="middle">Biến tần</text>
            <text x="560" y="310" font-size="28" text-anchor="middle">
                <tspan font-weight="800" x-text="fmt(latest?.inverter_power, 0)">0</tspan><tspan font-weight="400">W</tspan>
            </text>

            <text x="484" y="345" font-size="13"><tspan font-weight="400">Bên trong:</tspan></text>
            <text x="636" y="345" font-size="13" text-anchor="end">
                <tspan font-weight="700" x-text="fmt(latest?.inner_temp, 1)">0</tspan><tspan font-weight="400">°C</tspan>
            </text>

            <text x="484" y="363" font-size="13"><tspan font-weight="400">Cảm biến 1:</tspan></text>
            <text x="636" y="363" font-size="13" text-anchor="end">
                <tspan font-weight="700" x-text="fmt(latest?.radiator1_temp, 1)">0</tspan><tspan font-weight="400">°C</tspan>
            </text>

            <text x="484" y="381" font-size="13"><tspan font-weight="400">Cảm biến 2:</tspan></text>
            <text x="636" y="381" font-size="13" text-anchor="end">
                <tspan font-weight="700" x-text="fmt(latest?.radiator2_temp, 1)">0</tspan><tspan font-weight="400">°C</tspan>
            </text>

            <!-- Nút trạng thái: màu theo chế độ hoạt động, tooltip hiển thị nhãn trạng thái -->
            <circle cx="632" cy="416" r="9" :fill="stateColor(latest?.state)" :stroke="stateColor(latest?.state)" stroke-width="1.5">
                <title x-text="stateLabel(latest?.state)">—</title>
            </circle>
            <text x="570" y="422" font-size="11" fill="#475569" text-anchor="end">
                <tspan font-weight="600">S/N: </tspan>
                <tspan font-weight="700">{{ $serial }}</tspan>
            </text>
        </g>


        <!-- ===== KHỐI 5: PIN LƯU TRỮ ===== -->
        <g id="pin-luu-tru">
            <g id="battery-graphic">
                <rect x="758" y="260" width="24" height="8" rx="3" fill="#0f172a" />
                <rect x="740" y="268" width="60" height="144" rx="8" ry="8" fill="#ffffff" stroke="#0f172a" stroke-width="2" />
                <!-- Phần màu: chiều cao tỷ lệ với % pin (mọc lên từ đáy y=408, tối đa 136px) -->
                <rect x="744" width="52" rx="5" fill="#22c55e"
                    :y="408 - 136 * Math.min(100, Math.max(0, latest?.battery_soc ?? 0)) / 100"
                    :height="136 * Math.min(100, Math.max(0, latest?.battery_soc ?? 0)) / 100" />
                <text x="770" y="340" font-size="15" font-weight="800" text-anchor="middle" fill="#0f172a" x-text="fmt(latest?.battery_soc, 0) + '%'">0%</text>
            </g>

            <text x="820" y="280" font-size="18" font-weight="400">Pin lưu trữ</text>
            <text x="820" y="308" font-size="28">
                <tspan font-weight="800" x-text="fmt(Math.max(latest?.charge_power ?? 0, latest?.discharge_power ?? 0), 0)">0</tspan><tspan font-weight="400">W </tspan>
                <!-- Dòng pin = Công suất ÷ Điện áp, để luôn khớp với số W hiển thị (raw battery_current bị thiếu hệ số ×10) -->
                <tspan font-weight="700" font-size="18" x-text="fmt(Math.max(latest?.charge_power ?? 0, latest?.discharge_power ?? 0) / (latest?.battery_voltage || 1), 1)">0</tspan><tspan font-weight="400" font-size="18">A</tspan>
            </text>

            <text x="820" y="338" font-size="14">
                <tspan font-weight="700" x-text="fmt(latest?.battery_voltage, 1)">0</tspan><tspan font-weight="400">Vdc</tspan>
            </text>
            <text x="820" y="356" font-size="14">
                <tspan font-weight="400">Nhiệt độ: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.battery_temp, 1)">0</tspan><tspan font-weight="400">°C</tspan>
            </text>
            <text x="820" y="374" font-size="14">
                <tspan font-weight="400">Dung lượng: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.battery_count, 0)">2</tspan><tspan font-weight="400"> khối ~ </tspan>
                <tspan font-weight="700">123</tspan><tspan font-weight="400">Ah</tspan>
            </text>

            <text x="820" y="400" font-size="15" font-weight="900">Hôm nay</text>
            <text x="820" y="419" font-size="13">
                <tspan font-weight="400">Sạc: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.charge_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
            <text x="820" y="436" font-size="13">
                <tspan font-weight="400">Xả: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.discharge_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
        </g>


        <!-- ===== KHỐI 6: NGUỒN DỰ PHÒNG ===== -->
        <g id="nguon-du-phong">
            <g transform="translate(470, 535)">
                <rect x="4" y="12" width="58" height="36" rx="5" class="icon-path" stroke-width="2" fill="#ffffff" />
                <circle cx="23" cy="30" r="10" class="icon-path" stroke-width="1.8" fill="#f8fafc" />
                <text x="23" y="34" font-size="12" font-weight="800" text-anchor="middle" fill="#0f172a">G</text>
                <line x1="42" y1="18" x2="54" y2="18" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                <line x1="42" y1="24" x2="54" y2="24" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                <line x1="42" y1="30" x2="54" y2="30" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                <line x1="42" y1="36" x2="54" y2="36" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                <path d="M 12 12 L 12 5 L 19 5" stroke="#0f172a" stroke-width="2" stroke-linecap="round" fill="none" />
                <path d="M 24 12 L 24 8 L 38 8 L 38 12" stroke="#0f172a" stroke-width="1.5" fill="none" stroke-linejoin="round" />
                <rect x="8" y="48" width="12" height="4" rx="1" fill="#0f172a" />
                <rect x="46" y="48" width="12" height="4" rx="1" fill="#0f172a" />
            </g>

            <text x="470" y="618" font-size="14">
                <tspan font-weight="700" x-text="fmt(latest?.eps_voltage, 1)">0</tspan><tspan font-weight="400">Vac</tspan>
            </text>
            <text x="470" y="636" font-size="14">
                <tspan font-weight="700" x-text="fmt(latest?.eps_frequency, 1)">0</tspan><tspan font-weight="400">Hz</tspan>
            </text>

            <text x="580" y="558" font-size="18" font-weight="400">Nguồn dự phòng</text>
            <text x="580" y="586" font-size="28">
                <tspan font-weight="800" x-text="fmt((latest?.eps_load_show ? latest?.eps_power : 0) ?? 0, 0)">0</tspan><tspan font-weight="400">W</tspan>
            </text>

            <text x="580" y="628" font-size="14">
                <tspan font-weight="400">Hôm nay: </tspan>
                <tspan font-weight="700" x-text="fmt(latest?.eps_energy_day, 1)">0</tspan><tspan font-weight="400">kWh</tspan>
            </text>
        </g>

        <!-- ===== RSSI (trên Cập nhật) · Cập nhật · uptime — căn thẳng lề trái ===== -->
        <g id="rssi-display">
            <g transform="translate(30, 600)" fill="none" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M 2 7 A 14 14 0 0 1 22 7" />
                <path d="M 6 11 A 8 8 0 0 1 18 11" />
                <circle cx="12" cy="17" r="1.6" fill="#0f172a" stroke="none" />
            </g>

            <text x="60" y="620" font-size="14" fill="#0f172a">
                <tspan font-weight="700" x-text="fmt(latest?.rssi, 0)">—</tspan><tspan font-weight="400">dBm</tspan>
                <tspan font-weight="400" fill="#475569">(</tspan><tspan font-weight="700" fill="#475569" x-text="rssiLabel(latest?.rssi)">—</tspan><tspan font-weight="400" fill="#475569">)</tspan>
            </text>
            <text x="33" y="634" font-size="12" fill="#0f172a">
                <tspan font-weight="400">Wifi thiết bị giám sát</tspan>
            </text>
        </g>

        <text x="30" y="662" font-size="14" fill="#475569">
            <tspan font-weight="400">Cập nhật: </tspan>
            <tspan font-weight="700" x-text="timestamp">—</tspan>
        </text>
        {{-- <text x="30" y="680" font-size="14" fill="#475569">
            <tspan font-weight="400">Hệ thống: </tspan>
            <tspan font-weight="700" x-text="fmtUptime(latest?.uptime)">—</tspan>
        </text> --}}

    </svg>
</div>
