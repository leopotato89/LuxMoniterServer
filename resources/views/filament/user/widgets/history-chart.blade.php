@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Widgets\View\Components\ChartWidgetComponent;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
    $hasMaxHeight = filled($maxHeight) && $maxHeight !== '100%';
    $isEmpty = $this->isEmpty();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :heading="$heading" :description="$deviceSerial ? 'Dữ liệu từ InfluxDB' : null">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            {{ $this->form }}

            <div class="flex items-center gap-1">
                <x-filament::button icon="heroicon-m-chevron-left" size="sm" color="gray" wire:click="previousDay"
                    aria-label="Ngày trước" />
                <x-filament::button icon="heroicon-m-chevron-right" size="sm" color="gray" wire:click="nextDay"
                    aria-label="Ngày sau" />
            </div>
        </div>

        <div @if ($pollingInterval = $this->getPollingInterval()) wire:poll.{{ $pollingInterval }}="updateChartData"
        @endif @if ($isEmpty) style="display: none" @endif>
            <div x-load x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}" wire:ignore
                data-chart-type="{{ $type }}" x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })" {{
    (new FilamentComponentAttributeBag)
        ->color(ChartWidgetComponent::class, $color)
        ->class([
            'fi-wi-chart-frame',
            'fi-wi-chart-canvas-ctn',
            'fi-wi-chart-frame-no-aspect-ratio' => $hasMaxHeight,
        ])
                }}>
                <canvas x-ref="canvas" @style([
                    'width: 100%',
                    'height: 100%; max-height: 100%' => !$hasMaxHeight,
                    ('max-height: ' . e($maxHeight)) => $hasMaxHeight,
                ])></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>