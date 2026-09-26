<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :disabled="disabled"
        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'bg-brand' : 'bg-line-strong'"
        @click="emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition"
            :class="modelValue ? 'translate-x-[1.15rem]' : 'translate-x-[0.2rem]'"
        />
    </button>
</template>
