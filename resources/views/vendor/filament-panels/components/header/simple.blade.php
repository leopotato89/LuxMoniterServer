@props([
    'heading' => null,
    'logo' => true,
    'subheading' => null,
])

<header class="fi-simple-header">
    @if ($logo)
        <x-filament-panels::logo />
    @endif

    @if (filled($heading))
        <h1 class="fi-simple-header-heading text-white">
            {{ $heading }}
        </h1>
    @endif

    @if (filled($subheading))
        <p class="fi-simple-header-subheading text-gray-300">
            {{ $subheading }}
        </p>
    @endif
</header>
