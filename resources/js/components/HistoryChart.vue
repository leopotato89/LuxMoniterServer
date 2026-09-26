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
import { ref, onMounted, watch } from 'vue'
import api from '../lib/api'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps({
  serial: {
    type: String,
    required: true
  },
  field: {
    type: String,
    required: true
  },
  label: {
    type: String,
    default: 'Công suất'
  },
  color: {
    type: String,
    default: '#10b981' // brand color (emerald-500)
  },
  params: {
    type: Object,
    default: () => ({ start: '-24h', window: '10m' })
  }
})

const loading = ref(true)
const chartData = ref({ labels: [], datasets: [] })
const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: function(context) {
            let label = context.dataset.label || '';
            if (label) {
                label += ': ';
            }
            if (context.parsed.y !== null) {
                label += context.parsed.y.toFixed(1);
            }
            return label;
        }
      }
    }
  },
  scales: {
    x: {
      grid: { display: false, drawBorder: false },
      ticks: { maxTicksLimit: 10 }
    },
    y: {
      grid: { color: '#e5e7eb', borderDash: [5, 5] },
      beginAtZero: true
    }
  }
})

async function fetchData() {
    loading.value = true;
    try {
        const { data } = await api.get(`/devices/${props.serial}/history`, {
            params: { field: props.field, ...props.params }
        });
        
        // Dữ liệu Influx trả về mảng { time, value }
        const labels = data.data.map(d => {
            const date = new Date(d.time);
            return date.getHours() + ':' + String(date.getMinutes()).padStart(2, '0');
        });
        const values = data.data.map(d => d.value);

        chartData.value = {
            labels,
            datasets: [{
                label: props.label,
                data: values,
                borderColor: props.color,
                backgroundColor: props.color + '22', // Thêm độ trong suốt cho màu nền
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                borderWidth: 2,
            }]
        }
    } catch (e) {
        console.error('Lỗi tải dữ liệu biểu đồ:', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchData();
});

watch(() => props.field, () => {
    fetchData();
});

watch(() => props.params, () => {
    fetchData();
}, { deep: true });
</script>

<template>
  <div class="h-[300px] w-full relative">
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-surface/50 backdrop-blur-sm z-10 text-sm text-muted">
        <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Đang tải dữ liệu...
    </div>
    <Line v-if="chartData.labels.length > 0" :data="chartData" :options="chartOptions" />
    <div v-else-if="!loading" class="absolute inset-0 flex items-center justify-center text-sm text-muted">
        Không có dữ liệu trong 24h qua.
    </div>
  </div>
</template>
