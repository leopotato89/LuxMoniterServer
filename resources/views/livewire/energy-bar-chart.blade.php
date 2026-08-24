@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;

    $periods = [
        'month' => 'Tháng',
        'year' => 'Năm',
        'all' => 'Tất cả',
    ];
@endphp

<div class="space-y-3">
    {{-- Điều khiển --}}
    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex items-center gap-5">
            @foreach ($periods as $value => $label)
                <label class="flex cursor-pointer items-center gap-2">
                    <x-filament::input.radio
                        name="energy-period"
                        value="{{ $value }}"
                        wire:model.live="period"
                    />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                </label>
            @endforeach
        </div>

        @if ($period === 'year')
            <x-filament::input.wrapper class="max-w-36">
                <x-filament::input.select wire:model.live="year">
                    @foreach ($yearOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @elseif ($period === 'month')
            <x-filament::input.wrapper class="max-w-44">
                <x-filament::input.index type="month" wire:model.live="month" />
            </x-filament::input.wrapper>
        @endif
    </div>

    {{-- Biểu đồ --}}
    @if ($chart['isEmpty'])
        <div class="flex items-center justify-center rounded-xl border border-dashed border-gray-300 p-10 text-sm text-gray-500">
            Không có dữ liệu sản lượng cho thiết bị này.
        </div>
    @else
        <div wire:key="energy-bar-{{ $period }}-{{ $year }}-{{ $month }}" x-load x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
            data-chart-type="bar" x-data="chart({
                        cachedData: @js(['datasets' => $chart['datasets'], 'labels' => $chart['labels']]),
                        options: @js($chart['options']),
                        type: 'bar',
                    })" {{
            (new FilamentComponentAttributeBag)->class([
                'fi-wi-chart-frame',
                'fi-wi-chart-canvas-ctn',
                'fi-wi-chart-frame-no-aspect-ratio',
            ])
        }}>
            <canvas x-ref="canvas" style="width: 100%; height: 340px"></canvas>

            <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
            <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
            <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
            <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
        </div>
    @endif
</div>
