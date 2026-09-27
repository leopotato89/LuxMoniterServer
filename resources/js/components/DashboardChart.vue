<script setup>
import { Line } from 'vue-chartjs'
import { 
    Chart as ChartJS, 
    CategoryScale, 
    LinearScale, 
    PointElement, 
    LineElement, 
    Title, 
    Tooltip, 
    Legend, 
    Filler 
} from 'chart.js'
import { ref, onMounted, onUnmounted } from 'vue'
import api from '../lib/api'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps({
  serial: {
    type: String,
    required: true
  },
  params: {
    type: Object,
    default: () => ({ start: '-24h', window: '15m' })
  }
})

const loading = ref(true)
const chartData = ref({ labels: [], datasets: [] })

const externalTooltipHandler = (context) => {
  const {chart, tooltip} = context;
  let tooltipEl = document.getElementById('chartjs-tooltip-custom');

  if (!tooltipEl) {
    tooltipEl = document.createElement('div');
    tooltipEl.id = 'chartjs-tooltip-custom';
    tooltipEl.classList.add('chartjs-tooltip');
    tooltipEl.style.background = 'rgba(255, 255, 255, 0.95)';
    tooltipEl.style.borderRadius = '8px';
    tooltipEl.style.color = '#334155';
    tooltipEl.style.opacity = 1;
    tooltipEl.style.pointerEvents = 'none';
    tooltipEl.style.position = 'absolute';
    // Offset slightly so it doesn't cover the cursor
    tooltipEl.style.transform = 'translate(-50%, 15px)';
    tooltipEl.style.transition = 'all .1s ease';
    tooltipEl.style.boxShadow = '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)';
    tooltipEl.style.border = '1px solid #e2e8f0';
    tooltipEl.style.zIndex = 100000;
    tooltipEl.style.whiteSpace = 'nowrap';
    
    const table = document.createElement('table');
    table.style.margin = '0px';
    table.style.borderSpacing = '0px';
    tooltipEl.appendChild(table);
    document.body.appendChild(tooltipEl);
  }

  if (tooltip.opacity === 0) {
    tooltipEl.style.opacity = 0;
    return;
  }

  // Update dynamic dark mode styles on each hover
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
    const titleLines = tooltip.title || [];
    const bodyLines = tooltip.body.map(b => b.lines);

    let innerHtml = '<thead style="border-bottom: 1px solid #e2e8f0;">';
    titleLines.forEach(title => {
      innerHtml += '<tr><th style="text-align: left; padding: 6px 8px; font-size: 13px;">' + title + '</th></tr>';
    });
    innerHtml += '</thead><tbody style="font-size: 13px;">';

    bodyLines.forEach((body, i) => {
      const colors = tooltip.labelColors[i];
      let style = 'background:' + colors.backgroundColor;
      style += '; border-color:' + colors.borderColor;
      style += '; border-width: 2px';
      const span = '<span style="' + style + '; display:inline-block; width:10px; height:10px; margin-right:6px; border-radius:2px;"></span>';
      innerHtml += '<tr><td style="padding: 4px 8px;">' + span + body + '</td></tr>';
    });
    innerHtml += '</tbody>';

    const tableRoot = tooltipEl.querySelector('table');
    tableRoot.innerHTML = innerHtml;
  }

  const rect = chart.canvas.getBoundingClientRect();
  tooltipEl.style.opacity = 1;
  
  let leftPos = rect.left + window.scrollX + tooltip.caretX;
  
  // shift transform if too close to right/left edge
  const tooltipWidth = tooltipEl.offsetWidth || 150;
  if (leftPos + (tooltipWidth / 2) > document.body.clientWidth - 10) {
      tooltipEl.style.transform = 'translate(-100%, 15px)';
  } else if (leftPos - (tooltipWidth / 2) < 10) {
      tooltipEl.style.transform = 'translate(0%, 15px)';
  } else {
      tooltipEl.style.transform = 'translate(-50%, 15px)';
  }
  
  tooltipEl.style.left = leftPos + 'px';
  tooltipEl.style.top = rect.top + window.scrollY + tooltip.caretY + 'px';
};

const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: { 
      position: 'bottom',
      labels: { usePointStyle: true, boxWidth: 8, padding: 20 }
    },
    tooltip: {
      enabled: false,
      position: 'nearest',
      external: externalTooltipHandler,
      callbacks: {
        label: function(context) {
            let label = context.dataset.label || '';
            if (label) label += ': ';
            if (context.parsed.y !== null) {
                if (context.dataset.yAxisID === 'y1') {
                    label += context.parsed.y.toFixed(1) + '%';
                } else {
                    label += (context.parsed.y / 1000).toFixed(2) + ' kW';
                }
            }
            return label;
        }
      }
    }
  },
  scales: {
    x: {
      grid: { display: false, drawBorder: false },
      ticks: { maxTicksLimit: 12 }
    },
    y: {
      type: 'linear',
      display: true,
      position: 'left',
      title: { display: false },
      grid: { color: '#e5e7eb', borderDash: [5, 5] },
      ticks: {
          callback: function(value) { return value / 1000; }
      }
    },
    y1: {
      type: 'linear',
      display: true,
      position: 'right',
      title: { display: false },
      min: 0,
      max: 100,
      grid: { display: false },
    }
  }
})

async function fetchData() {
    loading.value = true;
    try {
        const { data } = await api.get(`/devices/${props.serial}/dashboard`, {
            params: props.params
        });
        
        const res = data.data; 

        if (!res.labels) {
            loading.value = false;
            return;
        }

        // Chuyển labels từ chuỗi ISO sang giờ:phút
        const labels = res.labels.map(t => {
            const date = new Date(t);
            return date.getHours() + ':' + String(date.getMinutes()).padStart(2, '0');
        });

        chartData.value = {
            labels,
            datasets: [
                {
                    label: 'Sản lượng',
                    data: res.series.pv,
                    borderColor: '#f59e0b', // amber-500
                    backgroundColor: '#f59e0b22',
                    yAxisID: 'y',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1,
                },
                {
                    label: 'Tải',
                    data: res.series.load,
                    borderColor: '#f43f5e', // rose-500
                    yAxisID: 'y',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1,
                },
                {
                    label: 'Điện lưới',
                    data: res.series.grid_net,
                    borderColor: '#3b82f6', // blue-500
                    yAxisID: 'y',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1,
                },
                {
                    label: 'Pin',
                    data: res.series.charge_net,
                    borderColor: '#8b5cf6', // violet-500
                    yAxisID: 'y',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1,
                },
                {
                    label: 'SOC',
                    data: res.series.soc,
                    borderColor: '#10b981', // emerald-500
                    yAxisID: 'y1',
                    fill: false,
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1,
                }
            ]
        }
    } catch (e) {
        console.error('Lỗi tải dữ liệu dashboard:', e);
    } finally {
        loading.value = false;
    }
}

const handleOutsideClick = (e) => {
    if (e.target.tagName !== 'CANVAS') {
        const tooltipEl = document.getElementById('chartjs-tooltip-custom');
        if (tooltipEl) tooltipEl.style.opacity = 0;
    }
}

onMounted(() => {
    document.addEventListener('touchstart', handleOutsideClick);
    document.addEventListener('mousedown', handleOutsideClick);
    fetchData();
});

onUnmounted(() => {
    document.removeEventListener('touchstart', handleOutsideClick);
    document.removeEventListener('mousedown', handleOutsideClick);
    const tooltipEl = document.getElementById('chartjs-tooltip-custom');
    if (tooltipEl) tooltipEl.style.opacity = 0;
});

import { watch } from 'vue';
watch(() => props.params, () => {
    fetchData();
}, { deep: true });
</script>

<template>
  <div class="h-[400px] w-full relative pt-6">
    <!-- Nhãn trục đặt lên góc trên để tránh chiếm khoảng không 2 bên -->
    <div class="absolute top-0 left-2 text-xs font-medium text-muted">kW</div>
    <div class="absolute top-0 right-2 text-xs font-medium text-muted">%</div>
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-surface/50 backdrop-blur-sm z-10 text-sm text-muted rounded-xl">
        <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Đang tải biểu đồ...
    </div>
    <Line v-if="chartData.labels && chartData.labels.length > 0" :data="chartData" :options="chartOptions" />
    <div v-else-if="!loading" class="absolute inset-0 flex items-center justify-center text-sm text-muted">
        Không có dữ liệu.
    </div>
  </div>
</template>
