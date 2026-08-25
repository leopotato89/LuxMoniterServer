@props([
    'heading' => null,
    'subheading' => null,
])

@php
    $heading ??= $this->getHeading();
    $subheading ??= $this->getSubHeading();
    $hasLogo = $this->hasLogo();
@endphp

<div {{ $attributes->class(['fi-simple-page']) }}>
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}
    <div class="fi-simple-page-content">

        <x-filament-panels::header.simple
            :logo="false"
            heading="{{config('app.name')}}"
            subheading="{{config('app.subtitle')}}"
        />
        <div
            class="bg-white px-6 py-4 rounded-2xl gap-8 grid auto-cols-fr gap-y-8  shadow-sm ring-1 ring-gray-950/5 mx-10">
            <div class="fi-simple-page-header">
                @if (filled($heading))
                    <h1 class="fi-simple-header-heading text-left">
                        {{ $heading }}
                    </h1>
                @endif
                @if (filled($subheading))
                    <p class="fi-simple-header-subheading text-left text-gray-500 mt-0">
                        {{ $subheading }}
                    </p>
                @endif
            </div>

            {{ $slot }}
        </div>
    </div>

    @if (! $this instanceof \Filament\Tables\Contracts\HasTable)
        <x-filament-actions::modals/>
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
