<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { 
    IconBattery4, 
    IconPlug, 
    IconCpu, 
    IconArrowsLeftRight,
    IconSettings 
} from '@tabler/icons-vue';
import IconLeadAcidBattery from '../icons/IconLeadAcidBattery.vue';
import IconAcCoupling from '../icons/IconAcCoupling.vue';
import Button from '../ui/Button.vue';
import ToggleSwitch from '../ui/ToggleSwitch.vue';
import api, { firstError } from '../../lib/api';
import { useToastStore } from '../../stores/toast';

const iconMap = {
    IconBattery4,
    IconPlug,
    IconCpu,
    IconArrowsLeftRight,
    IconSettings,
    IconLeadAcidBattery,
    IconAcCoupling
};

const props = defineProps({
    serial: { type: String, required: true }
});

const toast = useToastStore();
const loading = ref(false);
const reading = ref(false);
const schema = ref([]);
const values = ref({});
const countdownSeconds = ref({});
const activeSectionIdx = ref(0);
const activeTabIdx = ref({});


let quickchargeInterval;

onMounted(async () => {
    await fetchSchema();
    readFromDevice();
    
    // Decrement quickcharge fields locally every second
    quickchargeInterval = setInterval(() => {
        for (const key in countdownSeconds.value) {
            if (countdownSeconds.value[key] > 0) {
                countdownSeconds.value[key]--;
                if (countdownSeconds.value[key] <= 0) {
                    values.value[key] = 0;
                }
            }
        }
    }, 1000);
});

onUnmounted(() => {
    if (quickchargeInterval) clearInterval(quickchargeInterval);
});

async function fetchSchema() {
    loading.value = true;
    try {
        const { data } = await api.get(`/devices/${props.serial}/settings/schema`);
        schema.value = data;
        
        // Init active tabs
        data.forEach((sec, idx) => {
            if (sec.tabs && sec.tabs.length > 0) {
                activeTabIdx.value[idx] = 0;
            }
        });
    } catch (e) {
        toast.error(firstError(e));
    } finally {
        loading.value = false;
    }
}

async function readFromDevice() {
    reading.value = true;
    try {
        const { data } = await api.post(`/devices/${props.serial}/settings/read`);
        const jobId = data.job_id;
        
        pollResult(jobId);
    } catch (e) {
        reading.value = false;
        toast.error(firstError(e));
    }
}

function pollResult(jobId) {
    let attempts = 0;
    const interval = setInterval(async () => {
        attempts++;
        try {
            const { data } = await api.get(`/devices/${props.serial}/settings/read/${jobId}`);
            if (data.status === 'success') {
                clearInterval(interval);
                values.value = { ...values.value, ...data.values };
                syncQuickChargeState();
                //toast.success('Đã đọc dữ liệu từ thiết bị');
                reading.value = false;
            } else if (data.status === 'error') {
                clearInterval(interval);
                toast.error(data.error || 'Lỗi khi đọc');
                reading.value = false;
            } else if (attempts > 15) {
                // timeout ~30s (15 attempts * 2s)
                clearInterval(interval);
                toast.error('Hết thời gian chờ thiết bị phản hồi (30s)');
                reading.value = false;
            }
        } catch (e) {
            clearInterval(interval);
            toast.error(firstError(e));
            reading.value = false;
        }
    }, 2000); // Tăng thời gian chờ lên 2s/lần để giảm số lượng request
}

// Logic to evaluate `show`, `showIf`, `showAc`, `showGen` conditions
function isFieldVisible(field) {
    if (!field) return false;
    
    // showIf: field is visible if `values[field.showIf]` is true
    if (field.showIf) {
        return !!values.value[field.showIf];
    }
    
    // show: check if `values.reg_120_b4` (Kiểu điều khiển xả) is in field.show array
    if (field.show !== undefined) {
        const dischargeMode = values.value['reg_120_b4'];
        return field.show.includes(dischargeMode);
    }
    
    // showAc: check if `values.reg_120_b1` (Kiểu sạc AC) is in field.showAc array
    if (field.showAc !== undefined) {
        const acMode = values.value['reg_120_b1'];
        return field.showAc.includes(acMode);
    }
    
    // showGen: check if `values.reg_120_b7` (AC Coupling mode) is in field.showGen array
    if (field.showGen !== undefined) {
        const genMode = values.value['reg_120_b7'];
        return field.showGen.includes(genMode);
    }
    
    return true;
}

const activeSection = computed(() => {
    return schema.value[activeSectionIdx.value] || null;
});

const currentItems = computed(() => {
    if (!activeSection.value) return [];
    
    if (activeSection.value.tabs) {
        const tabIdx = activeTabIdx.value[activeSectionIdx.value] || 0;
        return activeSection.value.tabs[tabIdx].items.filter(isFieldVisible);
    }
    
    return (activeSection.value.items || []).filter(isFieldVisible);
});

async function saveField(field, newValue) {
    try {
        await api.put(`/devices/${props.serial}/settings/field`, {
            key: field.key,
            value: newValue
        });
        toast.success(`Đã lưu: ${field.label}`);
        values.value[field.key] = newValue;
        if (field.type === 'quickcharge') {
            countdownSeconds.value[field.key] = newValue * 60;
        }
    } catch (e) {
        toast.error(firstError(e));
        // Reset field value? We'll let it be for now or bind it to original.
    }
}

function saveInputFieldValue(field) {
    const el = document.getElementById(`input-${field.key}`);
    if (el) {
        saveField(field, parseFloat(el.value));
    }
}

function startQuickCharge(field) {
    const el = document.getElementById(`input-${field.key}`);
    if (el) {
        const val = parseInt(el.value, 10);
        if (val > 0) {
            saveField(field, val);
        }
    }
}

function stopQuickCharge(field) {
    const el = document.getElementById(`input-${field.key}`);
    if (el) el.value = '0';
    saveField(field, 0);
}

function syncQuickChargeState() {
    schema.value.forEach(sec => {
        const items = sec.tabs ? sec.tabs.flatMap(t => t.items) : sec.items;
        items.forEach(field => {
            if (field.type === 'quickcharge') {
                const val = values.value[field.key];
                if (typeof val === 'number' && val > 0) {
                    const currentLocalMins = Math.ceil((countdownSeconds.value[field.key] || 0) / 60);
                    // Chỉ ghi đè số giây nếu số phút từ MQTT lệch với số phút đang đếm ở local
                    if (val !== currentLocalMins) {
                        countdownSeconds.value[field.key] = val * 60;
                    }
                } else {
                    countdownSeconds.value[field.key] = 0;
                }
            }
        });
    });
}

function formatCountdown(totalSeconds) {
    if (!totalSeconds || totalSeconds <= 0) return '00:00';
    const m = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
    const s = (totalSeconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

</script>

<template>
    <div v-if="loading" class="text-sm text-muted p-4">Đang tải schema...</div>
    <div v-else-if="schema.length > 0" class="flex flex-row gap-4 md:gap-6 p-4">
        
        <!-- Sidebar Sections -->
        <div class="w-14 md:w-64 shrink-0 border-r border-line pr-2 md:pr-4 flex flex-col justify-between">
            <nav class="flex flex-col gap-2">
                <button
                    v-for="(sec, idx) in schema"
                    :key="idx"
                    @click="activeSectionIdx = idx"
                    class="flex items-center gap-3 p-2 md:px-3 md:py-2 rounded text-sm font-medium transition-colors"
                    :class="activeSectionIdx === idx ? 'bg-surface text-brand' : 'text-ink hover:bg-black/5 dark:hover:bg-white/5'"
                    :title="sec.title"
                >
                    <component :is="iconMap[sec.icon] || iconMap['IconSettings']" class="w-5 h-5 shrink-0" />
                    <span class="hidden md:inline whitespace-nowrap">{{ sec.title }}</span>
                </button>
                
            </nav>
            

        </div>
        
        <!-- Main Content -->
        <div class="flex-1 min-w-0">
            <div v-if="activeSection">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-semibold text-ink">{{ activeSection.title }}</h3>
                    
                    <Button 
                        @click="readFromDevice"
                        :disabled="reading"
                        title="Đọc lại dữ liệu"
                    >
                        <svg v-if="reading" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <svg v-else class="h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        <span class="hidden md:inline text-sm font-medium whitespace-nowrap" v-if="reading">Đang đọc dữ liệu...</span>
                        <span class="hidden md:inline text-sm font-medium whitespace-nowrap" v-else>Đọc lại dữ liệu</span>
                    </Button>
                </div>
                
                <div v-if="activeSection.tabs" class="flex gap-4 border-b border-line mb-6 overflow-x-auto hide-scrollbar">
                    <button
                        v-for="(tab, tabIdx) in activeSection.tabs"
                        :key="tabIdx"
                        @click="activeTabIdx[activeSectionIdx] = tabIdx"
                        class="pb-2 text-sm font-medium transition-colors border-b-2 outline-none whitespace-nowrap"
                        :class="(activeTabIdx[activeSectionIdx] || 0) === tabIdx ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
                    >
                        {{ tab.name }}
                    </button>
                </div>
                
                <div class="grid gap-2">
                    <div v-for="field in currentItems" :key="field.key" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 bg-surface rounded-lg border border-line">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-ink flex items-center gap-2">
                                <span>{{ field.label }}</span>
                                <div v-if="field.type === 'quickcharge' && values[field.key] > 0" class="inline-flex items-center font-mono text-brand font-bold bg-brand/5 dark:bg-brand/10 border border-brand/20 rounded px-1.5 py-0.5 text-xs">
                                    Còn lại: {{ formatCountdown(countdownSeconds[field.key]) }}
                                </div>
                            </label>
                            <span v-if="field.unit" class="text-xs text-muted">Đơn vị: {{ field.unit }}</span>
                            <span v-if="field.min !== undefined && field.max !== undefined" class="text-xs text-muted ml-2">({{ field.min }} - {{ field.max }})</span>
                        </div>
                        
                        <div class="w-full sm:w-48 shrink-0 flex gap-2">
                            <template v-if="field.type === 'switch'">
                                <ToggleSwitch 
                                    :model-value="!!values[field.key]"
                                    :disabled="reading"
                                    @update:model-value="saveField(field, $event)"
                                />
                            </template>
                            
                            <template v-else-if="field.type === 'select'">
                                <select 
                                    :value="values[field.key]"
                                    :disabled="reading"
                                    @change="saveField(field, parseInt($event.target.value))"
                                    class="field"
                                >
                                    <option v-for="(optLabel, optVal) in field.options" :key="optVal" :value="parseInt(optVal)">
                                        {{ optLabel }}
                                    </option>
                                </select>
                            </template>
                            
                            <template v-else-if="field.type === 'time'">
                                <input 
                                    type="time" 
                                    :value="values[field.key]"
                                    :disabled="reading"
                                    @change="saveField(field, $event.target.value)"
                                    class="field"
                                >
                            </template>
                            
                            <template v-else-if="field.type === 'quickcharge'">
                                <div class="flex items-center justify-end gap-2 w-full">
                                    <input 
                                        v-if="!values[field.key]"
                                        :id="`input-${field.key}`"
                                        type="number" 
                                        :value="values[field.key]"
                                        :min="0"
                                        :disabled="reading"
                                        class="field"
                                        placeholder="Số phút"
                                    >
                                    <Button 
                                        v-if="values[field.key] > 0"
                                        variant="danger"
                                        @click="stopQuickCharge(field)"
                                        :disabled="reading"
                                    >
                                        Dừng
                                    </Button>
                                    <Button 
                                        v-else
                                        variant="secondary"
                                        @click="startQuickCharge(field)"
                                        :disabled="reading"
                                    >
                                        OK
                                    </Button>
                                </div>
                            </template>
                            
                            <template v-else>
                                <!-- number -->
                                <div class="flex items-center gap-2 w-full">
                                    <input 
                                        :id="`input-${field.key}`"
                                        type="number" 
                                        :value="values[field.key]"
                                        :min="field.min"
                                        :max="field.max"
                                        :disabled="reading"
                                        :step="field.type === 'number' && (field.scale || 1) > 1 ? 1 / field.scale : 1"
                                        @keyup.enter="saveInputFieldValue(field)"
                                        class="field"
                                    >
                                    <Button 
                                        @click="saveInputFieldValue(field)"
                                        :disabled="reading"
                                        class="shrink-0"
                                    >OK</Button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
