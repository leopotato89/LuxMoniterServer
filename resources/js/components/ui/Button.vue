<script setup>
const props = defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
});

const variants = {
    primary: 'bg-brand text-white shadow-brand hover:bg-brand-strong',
    secondary: 'border border-line bg-surface text-ink hover:bg-surface-alt',
    danger: 'bg-danger text-white hover:brightness-95',
    ghost: 'text-muted hover:text-ink',
};

const sizes = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
};
</script>

<template>
    <!-- Listener của cha tự rơi xuống <button> gốc nên không cần khai báo emits. -->
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-2 rounded-field font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
        :class="[variants[variant] ?? variants.primary, sizes[size] ?? sizes.md]"
        @click="submit"
    >
        <slot />
    </button>
</template>
