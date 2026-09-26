<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import api from '../lib/api'
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js'

ChartJS.register(ArcElement, Tooltip)

const props = defineProps({
    serial: { type: String, required: true },
    date: { type: String, required: true }
})

const loading = ref(true)
const kpis = ref({
    pv_energy: 0,
    export_energy: 0,
    import_energy: 0,
    consume_energy: 0,
    charge_energy: 0,
    discharge_energy: 0
})

async function fetchKpis() {
    loading.value = true
    try {
        const { data } = await api.get(`/devices/${props.serial}/daily`, {
            params: { date: props.date }
        })
        
        const result = data.data || {}
        kpis.value = {
            pv_energy: (result.pv_energy_day || 0) + (result.accouple_energy_day || 0),
            export_energy: result.export_energy_day || 0,
            import_energy: result.import_energy_day || 0,
            consume_energy: result.consume_energy_day || result.load_energy_day || 0,
            charge_energy: result.charge_energy_day || 0,
            discharge_energy: result.discharge_energy_day || 0
        }
    } catch (e) {
        console.error('Failed to fetch KPIs', e)
    } finally {
        loading.value = false
    }
}

watch(() => props.date, fetchKpis)
onMounted(fetchKpis)

const formatNumber = (val) => Number(val).toFixed(1)

const pvPieData = computed(() => {
    // Sản lượng phân bổ: Cấp tải, Sạc pin, Đẩy lưới
    let charge = kpis.value.charge_energy
    let exportGrid = kpis.value.export_energy
    let load = Math.max(0, kpis.value.pv_energy - charge - exportGrid)
    // Nếu pv_energy = 0 thì pie data cũng là 0
    if (kpis.value.pv_energy <= 0.1) return null

    return {
        labels: ['Cấp tải', 'Sạc pin', 'Đẩy lưới'],
        datasets: [{
            data: [load, charge, exportGrid],
            backgroundColor: ['#3b82f6', '#10b981', '#f43f5e'],
            borderWidth: 0
        }]
    }
})

const consumePieData = computed(() => {
    // Nguồn cấp tải: Xả pin, PV, Lưới
    let discharge = kpis.value.discharge_energy
    let importGrid = kpis.value.import_energy
    let pv = Math.max(0, kpis.value.consume_energy - importGrid - discharge)
    if (kpis.value.consume_energy <= 0.1) return null

    return {
        labels: ['Trực tiếp', 'Xả pin', 'Kéo lưới'],
        datasets: [{
            data: [pv, discharge, importGrid],
            backgroundColor: ['#f59e0b', '#0ea5e9', '#a855f7'],
            borderWidth: 0
        }]
    }
})

const externalPieTooltipHandler = (context) => {
    const {chart, tooltip} = context;
    let tooltipEl = document.getElementById('pie-tooltip-custom');

    if (!tooltipEl) {
        tooltipEl = document.createElement('div');
        tooltipEl.id = 'pie-tooltip-custom';
        tooltipEl.classList.add('pie-tooltip');
        tooltipEl.style.background = 'rgba(255, 255, 255, 0.95)';
        tooltipEl.style.borderRadius = '6px';
        tooltipEl.style.color = '#334155';
        tooltipEl.style.opacity = 1;
        tooltipEl.style.pointerEvents = 'none';
        tooltipEl.style.position = 'absolute';
        tooltipEl.style.transform = 'translate(-50%, -100%)';
        tooltipEl.style.marginTop = '-5px';
        tooltipEl.style.transition = 'all .1s ease';
        tooltipEl.style.boxShadow = '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)';
        tooltipEl.style.border = '1px solid #e2e8f0';
        tooltipEl.style.zIndex = 100000;
        tooltipEl.style.fontSize = '12px';
        tooltipEl.style.whiteSpace = 'nowrap';
        tooltipEl.style.padding = '4px 8px';
        
        document.body.appendChild(tooltipEl);
    }

    if (tooltip.opacity === 0) {
        tooltipEl.style.opacity = 0;
        return;
    }

    if (document.documentElement.classList.contains('dark')) {
        tooltipEl.style.background = 'rgba(31, 41, 55, 0.95)';
        tooltipEl.style.color = '#f3f4f6';
        tooltipEl.style.border = '1px solid rgba(255,255,255,0.05)';
    } else {
        tooltipEl.style.background = 'rgba(255, 255, 255, 0.95)';
        tooltipEl.style.color = '#334155';
        tooltipEl.style.border = '1px solid #e2e8f0';
    }

    if (tooltip.body) {
        const bodyLines = tooltip.body.map(b => b.lines);
        const colors = tooltip.labelColors[0];
        let style = 'background:' + colors.backgroundColor;
        style += '; border-color:' + colors.borderColor;
        style += '; border-width: 0px';
        const span = '<span style="' + style + '; display:inline-block; width:8px; height:8px; margin-right:6px; border-radius:2px;"></span>';
        
        tooltipEl.innerHTML = span + bodyLines[0];
    }

    const rect = chart.canvas.getBoundingClientRect();
    tooltipEl.style.opacity = 1;
    
    let leftPos = rect.left + window.scrollX + tooltip.caretX;
    
    tooltipEl.style.left = leftPos + 'px';
    tooltipEl.style.top = rect.top + window.scrollY + tooltip.caretY + 'px';
};

const pieOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '0%', // Pie, not doughnut
    plugins: {
        legend: { display: false },
        tooltip: {
            enabled: false,
            external: externalPieTooltipHandler,
            callbacks: {
                label: function(context) {
                    let label = context.label || '';
                    if (label) label += ': ';
                    if (context.parsed !== null) {
                        label += context.parsed.toFixed(1) + ' kWh';
                    }
                    return label;
                }
            }
        }
    }
}
</script>

<template>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <!-- Block 1: Sản lượng + Pie -->
        <div class="rounded-xl border border-line bg-surface p-3 flex justify-between items-center shadow-sm">
            <div class="flex flex-col">
                <span class="text-xs text-muted mb-1 font-medium">Sản lượng</span>
                <div class="flex items-end gap-1">
                    <span class="text-xl font-bold text-amber-500 leading-none">{{ loading ? '--' : formatNumber(kpis.pv_energy) }}</span>
                    <span class="text-xs text-muted pb-0.5">kWh</span>
                </div>
            </div>
            <div v-if="!loading && pvPieData" class="w-12 h-12">
                <Pie :data="pvPieData" :options="pieOptions" />
            </div>
        </div>

        <!-- Block 2: Tiêu thụ + Pie -->
        <div class="rounded-xl border border-line bg-surface p-3 flex justify-between items-center shadow-sm">
            <div class="flex flex-col">
                <span class="text-xs text-muted mb-1 font-medium">Tiêu thụ</span>
                <div class="flex items-end gap-1">
                    <span class="text-xl font-bold text-blue-500 leading-none">{{ loading ? '--' : formatNumber(kpis.consume_energy) }}</span>
                    <span class="text-xs text-muted pb-0.5">kWh</span>
                </div>
            </div>
            <div v-if="!loading && consumePieData" class="w-12 h-12">
                <Pie :data="consumePieData" :options="pieOptions" />
            </div>
        </div>

        <!-- Block 3: Lưới (Nhập / Đẩy) -->
        <div class="rounded-xl border border-line bg-surface p-3 flex flex-col justify-center shadow-sm">
            <span class="text-xs text-muted mb-1 font-medium">Lưới</span>
            <div class="flex justify-between items-end mt-1">
                <div class="flex flex-col">
                    <span class="text-[10px] text-muted">Đẩy</span>
                    <span class="text-base font-semibold text-rose-500 leading-none">{{ loading ? '--' : formatNumber(kpis.export_energy) }} <span class="text-[10px] font-normal text-muted">kWh</span></span>
                </div>
                <div class="flex flex-col text-right">
                    <span class="text-[10px] text-muted">Nhập</span>
                    <span class="text-base font-semibold text-purple-500 leading-none">{{ loading ? '--' : formatNumber(kpis.import_energy) }} <span class="text-[10px] font-normal text-muted">kWh</span></span>
                </div>
            </div>
        </div>

        <!-- Block 4: Pin (Sạc / Xả) -->
        <div class="rounded-xl border border-line bg-surface p-3 flex flex-col justify-center shadow-sm">
            <span class="text-xs text-muted mb-1 font-medium">Pin</span>
            <div class="flex justify-between items-end mt-1">
                <div class="flex flex-col">
                    <span class="text-[10px] text-muted">Sạc</span>
                    <span class="text-base font-semibold text-emerald-500 leading-none">{{ loading ? '--' : formatNumber(kpis.charge_energy) }} <span class="text-[10px] font-normal text-muted">kWh</span></span>
                </div>
                <div class="flex flex-col text-right">
                    <span class="text-[10px] text-muted">Xả</span>
                    <span class="text-base font-semibold text-sky-500 leading-none">{{ loading ? '--' : formatNumber(kpis.discharge_energy) }} <span class="text-[10px] font-normal text-muted">kWh</span></span>
                </div>
            </div>
        </div>
    </div>
</template>
