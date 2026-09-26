<template>
  <div class="flex flex-col h-full">
    <!-- Filters (Left aligned) -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <!-- Mode selection (Button group) -->
      <div class="flex items-center rounded-lg bg-surface border border-line p-1">
        <button
          @click="setViewMode('month')"
          :class="viewMode === 'month' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-ink'"
          class="px-3 py-1 text-sm font-medium rounded-md transition"
        >Tháng</button>
        <button
          @click="setViewMode('year')"
          :class="viewMode === 'year' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-ink'"
          class="px-3 py-1 text-sm font-medium rounded-md transition"
        >Năm</button>
        <button
          @click="setViewMode('all')"
          :class="viewMode === 'all' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-ink'"
          class="px-3 py-1 text-sm font-medium rounded-md transition"
        >Tất cả</button>
      </div>

      <div v-if="viewMode === 'month'" class="flex items-center gap-2">
        <button 
          @click="prevDate" 
          class="p-1 rounded bg-surface hover:bg-line border border-line text-muted hover:text-ink transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <input 
          id="month-picker" 
          type="month" 
          :max="currentMonth"
          v-model="selectedDate" 
          @change="fetchData"
          class="rounded bg-surface border border-line px-3 py-1 text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand outline-none transition"
        />
        <button 
          @click="nextDate" 
          :disabled="selectedDate >= currentMonth"
          class="p-1 rounded bg-surface hover:bg-line border border-line text-muted hover:text-ink transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>

      <div v-else-if="viewMode === 'year'" class="flex items-center gap-2">
        <button 
          @click="prevDate" 
          class="p-1 rounded bg-surface hover:bg-line border border-line text-muted hover:text-ink transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <input 
          id="year-picker" 
          type="number" 
          :max="currentYear"
          v-model="selectedDate" 
          @change="fetchData"
          class="rounded bg-surface border border-line px-3 py-1 text-sm  text-ink focus:border-brand focus:ring-1 focus:ring-brand outline-none transition w-24"
        />
        <button 
          @click="nextDate" 
          :disabled="selectedDate >= currentYear"
          class="p-1 rounded bg-surface hover:bg-line border border-line text-muted hover:text-ink transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>

    <!-- Chart -->
    <div class="h-[450px] w-full relative pt-6">
      <div class="absolute top-0 left-2 text-xs font-medium text-muted">kWh</div>
      
      <Bar v-if="hasData" :data="chartData" :options="chartOptions" />
      
      <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-surface/50 backdrop-blur-sm z-10 text-sm text-muted rounded-xl">
        <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Đang tải ...
      </div>
      
      <div v-else-if="!hasData" class="absolute inset-0 flex items-center justify-center text-sm text-muted">
        Không có dữ liệu.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Bar } from 'vue-chartjs'
import api from '../lib/api'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps({
  serial: {
    type: String,
    required: true
  }
})

const loading = ref(false)
const hasData = ref(false)
const rawData = ref({})

const viewMode = ref('month')

const now = new Date()
const currentMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
const currentYear = `${now.getFullYear()}`

const selectedDate = ref(currentMonth)

function setViewMode(mode) {
  if (viewMode.value !== mode) {
    viewMode.value = mode
    if (mode === 'month') {
      selectedDate.value = currentMonth
    } else if (mode === 'year') {
      selectedDate.value = currentYear
    }
    fetchData()
  }
}

function prevDate() {
  if (viewMode.value === 'month') {
    const [y, m] = selectedDate.value.split('-').map(Number)
    const d = new Date(y, m - 2, 1)
    selectedDate.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    fetchData()
  } else if (viewMode.value === 'year') {
    const y = parseInt(selectedDate.value, 10)
    selectedDate.value = `${y - 1}`
    fetchData()
  }
}

function nextDate() {
  if (viewMode.value === 'month') {
    if (selectedDate.value >= currentMonth) return
    const [y, m] = selectedDate.value.split('-').map(Number)
    const d = new Date(y, m, 1)
    selectedDate.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    fetchData()
  } else if (viewMode.value === 'year') {
    if (selectedDate.value >= currentYear) return
    const y = parseInt(selectedDate.value, 10)
    selectedDate.value = `${y + 1}`
    fetchData()
  }
}

const fetchData = async () => {
  loading.value = true
  hasData.value = false
  try {
    const res = await api.get(`/devices/${props.serial}/energy`, {
      params: { type: viewMode.value, date: viewMode.value === 'all' ? null : selectedDate.value }
    })
    
    rawData.value = res.data.data || {}
    // If rawData is not empty array/object
    hasData.value = Object.keys(rawData.value).length > 0
  } catch (error) {
    console.error(error)
    hasData.value = false
  } finally {
    loading.value = false
  }
}

const seriesMeta = {
  pv: { label: 'Sản lượng', color: '#f59e0b' },
  charge: { label: 'Sạc pin', color: '#8b5cf6' },
  discharge: { label: 'Xả pin', color: '#6366f1' },
  load: { label: 'Tải sử dụng', color: '#3b82f6' },
  import: { label: 'Lấy lưới', color: '#ef4444' },
  export: { label: 'Đẩy lưới', color: '#10b981' },
}

const getDaysInMonth = (year, month) => {
  return new Date(year, month, 0).getDate()
}

const chartData = computed(() => {
  if (!hasData.value) return { labels: [], datasets: [] }

  const labels = []
  const values = {
    pv: [],
    rawPv: [],
    rawAccouple: [],
    charge: [],
    discharge: [],
    load: [],
    import: [],
    export: [],
  }

  if (viewMode.value === 'month') {
    const [yearStr, monthStr] = selectedDate.value.split('-')
    const year = parseInt(yearStr, 10)
    const month = parseInt(monthStr, 10)
    const daysInMonth = getDaysInMonth(year, month)

    for (let d = 1; d <= daysInMonth; d++) {
      labels.push(d.toString())
      
      const dateKey = `${yearStr}-${monthStr}-${String(d).padStart(2, '0')}`
      const row = rawData.value[dateKey]
      
      values.pv.push(row ? ((row.pv || 0) + (row.accouple || 0)) : 0)
      values.rawPv.push(row ? (row.pv || 0) : 0)
      values.rawAccouple.push(row ? (row.accouple || 0) : 0)
      values.charge.push(row ? (row.charge || 0) : 0)
      values.discharge.push(row ? (row.discharge || 0) : 0)
      values.load.push(row ? (row.load || 0) : 0)
      values.import.push(row ? (row.import || 0) : 0)
      values.export.push(row ? (row.export || 0) : 0)
    }
  } else if (viewMode.value === 'year') {
    const yearStr = selectedDate.value
    for (let m = 1; m <= 12; m++) {
      labels.push(`T${m}`)
      
      const dateKey = `${yearStr}-${String(m).padStart(2, '0')}`
      const row = rawData.value[dateKey]
      
      values.pv.push(row ? ((row.pv || 0) + (row.accouple || 0)) : 0)
      values.rawPv.push(row ? (row.pv || 0) : 0)
      values.rawAccouple.push(row ? (row.accouple || 0) : 0)
      values.charge.push(row ? (row.charge || 0) : 0)
      values.discharge.push(row ? (row.discharge || 0) : 0)
      values.load.push(row ? (row.load || 0) : 0)
      values.import.push(row ? (row.import || 0) : 0)
      values.export.push(row ? (row.export || 0) : 0)
    }
  } else if (viewMode.value === 'all') {
    const years = Object.keys(rawData.value).sort()
    for (const yearStr of years) {
      labels.push(yearStr)
      const row = rawData.value[yearStr]
      
      values.pv.push(row ? ((row.pv || 0) + (row.accouple || 0)) : 0)
      values.rawPv.push(row ? (row.pv || 0) : 0)
      values.rawAccouple.push(row ? (row.accouple || 0) : 0)
      values.charge.push(row ? (row.charge || 0) : 0)
      values.discharge.push(row ? (row.discharge || 0) : 0)
      values.load.push(row ? (row.load || 0) : 0)
      values.import.push(row ? (row.import || 0) : 0)
      values.export.push(row ? (row.export || 0) : 0)
    }
  }

  const datasets = Object.keys(seriesMeta).map(k => {
    const ds = {
      label: seriesMeta[k].label,
      data: values[k].map(v => Number(v.toFixed(2))),
      backgroundColor: seriesMeta[k].color,
      borderColor: seriesMeta[k].color,
      borderWidth: 1,
      borderRadius: 2,
      maxBarThickness: 40,
    }
    if (k === 'pv') {
      ds.rawPv = values.rawPv.map(v => Number(v.toFixed(2)))
      ds.rawAccouple = values.rawAccouple.map(v => Number(v.toFixed(2)))
    }
    return ds
  })

  return {
    labels,
    datasets,
  }
})

// External Tooltip logic from earlier to prevent cutoff
const getOrCreateTooltip = (chart) => {
  let tooltipEl = document.getElementById('chartjs-bar-tooltip');
  if (!tooltipEl) {
    tooltipEl = document.createElement('div');
    tooltipEl.id = 'chartjs-bar-tooltip';
    tooltipEl.style.background = 'rgba(255, 255, 255, 0.95)';
    tooltipEl.style.backdropFilter = 'blur(10px)';
    tooltipEl.style.borderRadius = '12px';
    tooltipEl.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)';
    tooltipEl.style.color = '#1f2937';
    tooltipEl.style.opacity = 1;
    tooltipEl.style.pointerEvents = 'none';
    tooltipEl.style.position = 'absolute';
    tooltipEl.style.transform = 'translate(-50%, 10px)';
    tooltipEl.style.transition = 'all .1s ease';
    tooltipEl.style.zIndex = '9999';
    tooltipEl.style.border = '1px solid rgba(0,0,0,0.05)';
    tooltipEl.style.minWidth = '200px';
    
    // Check dark mode
    if (document.documentElement.classList.contains('dark')) {
      tooltipEl.style.background = 'rgba(31, 41, 55, 0.95)';
      tooltipEl.style.color = '#f3f4f6';
      tooltipEl.style.border = '1px solid rgba(255,255,255,0.05)';
    }

    const table = document.createElement('table');
    table.style.margin = '0px';
    table.style.width = '100%';
    tooltipEl.appendChild(table);
    document.body.appendChild(tooltipEl);
  }
  return tooltipEl;
};

const externalTooltipHandler = (context) => {
  const { chart, tooltip } = context;
  const tooltipEl = getOrCreateTooltip(chart);

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
      tooltipEl.style.color = '#1f2937';
      tooltipEl.style.border = '1px solid rgba(0,0,0,0.05)';
  }

  if (tooltip.body) {
    const titleLines = tooltip.title || [];
    const bodyLines = tooltip.body.map(b => b.lines);

    const tableHead = document.createElement('thead');
    titleLines.forEach(title => {
      const tr = document.createElement('tr');
      tr.style.borderWidth = '0';
      
      const th = document.createElement('th');
      th.style.borderWidth = '0';
      th.style.textAlign = 'left';
      th.style.padding = '8px 12px';
      th.style.borderBottom = '1px solid rgba(0,0,0,0.05)';
      th.style.fontSize = '14px';
      th.style.fontWeight = '600';
      if (document.documentElement.classList.contains('dark')) {
        th.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
      }
      
      const text = document.createTextNode('Ngày ' + title);
      th.appendChild(text);
      tr.appendChild(th);
      tableHead.appendChild(tr);
    });

    const tableBody = document.createElement('tbody');
    bodyLines.forEach((body, i) => {
      const colors = tooltip.labelColors[i];
      const val = body[0].split(': ')[1];
      const labelText = body[0].split(': ')[0];
      
      const tr = document.createElement('tr');
      tr.style.backgroundColor = 'inherit';
      tr.style.borderWidth = '0';

      const td = document.createElement('td');
      td.style.borderWidth = '0';
      td.style.padding = '6px 12px';
      td.style.display = 'flex';
      td.style.alignItems = 'center';
      td.style.justifyContent = 'space-between';
      td.style.fontSize = '13px';

      const labelContainer = document.createElement('div');
      labelContainer.style.display = 'flex';
      labelContainer.style.alignItems = 'center';
      labelContainer.style.gap = '8px';

      const colorSquare = document.createElement('span');
      colorSquare.style.background = colors.backgroundColor;
      colorSquare.style.borderColor = colors.borderColor;
      colorSquare.style.borderWidth = '2px';
      colorSquare.style.width = '10px';
      colorSquare.style.height = '10px';
      colorSquare.style.borderRadius = '2px';
      colorSquare.style.display = 'inline-block';

      const labelSpan = document.createElement('span');
      labelSpan.innerText = labelText;

      labelContainer.appendChild(colorSquare);
      labelContainer.appendChild(labelSpan);

      const valContainer = document.createElement('div');
      valContainer.style.display = 'flex';
      valContainer.style.flexDirection = 'column';
      valContainer.style.alignItems = 'flex-end';

      const valSpan = document.createElement('span');
      valSpan.style.fontWeight = '600';
      valSpan.innerText = val;
      valContainer.appendChild(valSpan);

      // Nếu là Sản lượng (pv) thì hiển thị thêm chi tiết pv + accouple
      const datasetIndex = tooltip.dataPoints[i].datasetIndex;
      const dataIndex = tooltip.dataPoints[i].dataIndex;
      const dataset = chart.data.datasets[datasetIndex];
      
      if (dataset.rawPv !== undefined && dataset.rawAccouple !== undefined) {
          const rawPv = dataset.rawPv[dataIndex];
          const rawAccouple = dataset.rawAccouple[dataIndex];
          if (rawAccouple > 0) {
              const detailSpan = document.createElement('span');
              detailSpan.style.fontSize = '11px';
              detailSpan.style.color = document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280';
              detailSpan.innerText = `(PV: ${rawPv} + AC: ${rawAccouple})`;
              valContainer.appendChild(detailSpan);
          }
      }

      td.appendChild(labelContainer);
      td.appendChild(valContainer);
      tr.appendChild(td);
      tableBody.appendChild(tr);
    });

    const tableRoot = tooltipEl.querySelector('table');
    while (tableRoot.firstChild) {
      tableRoot.firstChild.remove();
    }
    tableRoot.appendChild(tableHead);
    tableRoot.appendChild(tableBody);
  }

  const { offsetLeft: positionX, offsetTop: positionY } = chart.canvas;
  const canvasRect = chart.canvas.getBoundingClientRect();
  
  // Calculate tooltip position
  let left = canvasRect.left + window.scrollX + tooltip.caretX;
  let top = canvasRect.top + window.scrollY + tooltip.caretY;
  
  // Apply changes temporarily to get dimensions
  tooltipEl.style.opacity = 1;
  tooltipEl.style.left = left + 'px';
  tooltipEl.style.top = top + 'px';
  
  // Prevent overflow on right edge
  const tooltipWidth = tooltipEl.offsetWidth || 200;
  if (left + (tooltipWidth / 2) > document.body.clientWidth - 10) {
    tooltipEl.style.transform = 'translate(-100%, 10px)';
  } else if (left - (tooltipWidth / 2) < 10) {
    tooltipEl.style.transform = 'translate(0%, 10px)';
  } else {
    tooltipEl.style.transform = 'translate(-50%, 10px)';
  }
};

const chartOptions = computed(() => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index',
      intersect: false,
    },
    scales: {
      x: {
        grid: {
          display: false,
        },
        ticks: {
          font: { family: "'Inter', sans-serif" },
          color: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280'
        },
      },
      y: {
        beginAtZero: true,
        grid: {
          color: document.documentElement.classList.contains('dark') ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)',
          drawBorder: false,
        },
        ticks: {
          padding: 8,
          font: { family: "'Inter', sans-serif" },
          color: document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280'
        },
      },
    },
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          usePointStyle: true,
          boxWidth: 8,
          padding: 20,
          font: {
            family: "'Inter', sans-serif",
            size: 13,
          },
          color: document.documentElement.classList.contains('dark') ? '#d1d5db' : '#374151'
        },
      },
      tooltip: {
        enabled: false,
        external: externalTooltipHandler,
      },
    },
  }
})

onMounted(() => {
  fetchData()
})
</script>
