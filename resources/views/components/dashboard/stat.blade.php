@props([
    'label' => '',
    'value' => '—',
    'suffix' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>
    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
    <div class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
        {{ $value }}@if ($suffix)<span class="ml-0.5 text-sm font-medium text-gray-400">{{ $suffix }}</span>@endif
    </div>
</div>
