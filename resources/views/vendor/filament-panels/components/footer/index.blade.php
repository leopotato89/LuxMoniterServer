@props([
    'actions' => [],
    'actionsAlignment' => null,
    'footing' => null,
    'subfooting' => null,
])

@php
    $visibleActions = array_filter($actions, fn($action) => $action->isVisible());
@endphp

@if ($visibleActions || filled($footing) || filled($subfooting))
    <footer
        {{
            $attributes->class([
                'fi-footer',
            ])
        }}
    >
        <div class="flex items-center flex-1 gap-2">
            @if (filled($footing))
                <div class="fi-footer-footing">
                    {{ $footing }}
                </div>
            @endif

            @if (filled($subfooting))
                <p class="fi-footer-subfooting">
                    {{ $subfooting }}
                </p>
            @endif
        </div>

        @if ($visibleActions)
            <div class="fi-footer-actions-ctn">
                <x-filament::actions
                    :actions="$visibleActions"
                    :alignment="$actionsAlignment"
                />
            </div>
        @endif
    </footer>
@endif
