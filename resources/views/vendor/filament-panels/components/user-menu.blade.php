@props([
    'position' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Enums\UserMenuPosition;
    use Illuminate\Support\Arr;

    $user = filament()->auth()->user();

    $items = $this->getUserMenuItems();

    $itemsBeforeAndAfterThemeSwitcher = collect($items)
        ->groupBy(fn (Action $item): bool => $item->getSort() < 0, preserveKeys: true)
        ->all();
    $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
    $itemsAfterThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();

    $hasProfileHeader = $itemsBeforeThemeSwitcher->has('profile') &&
        blank(($item = Arr::first($itemsBeforeThemeSwitcher))->getUrl()) &&
        (! $item->hasAction());

    if ($itemsBeforeThemeSwitcher->has('profile')) {
        $itemsBeforeThemeSwitcher = $itemsBeforeThemeSwitcher->prepend($itemsBeforeThemeSwitcher->pull('profile'), 'profile');
    }

    $position ??= filament()->getUserMenuPosition();

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    :placement="($position === UserMenuPosition::Topbar) ? 'bottom-end' : 'top-end'"
    :teleport="$position === UserMenuPosition::Topbar"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu'])
    "
>
    <x-slot name="trigger">
        <button
            aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
            type="button"
            class="shrink-0"
        >
            <div class="flex items-center gap-x-3 md:h-14 h-10 md:px-4 px-1">
                <x-filament-panels::avatar.user :user="$user" loading="lazy" class="md:w-9 md:h-9 w-8 h-8"/>
                <div class="hidden md:flex flex-col text-left">
                    <p class="text-sm font-semibold leading-tight text-gray-900 dark:text-gray-100">
                        {{ $user->name }}
                    </p>
                    <p class="text-xs font-normal leading-tight mt-0.5 text-gray-500 dark:text-gray-400">
                        {{ $user->email }}
                    </p>
                </div>
                <x-filament::icon
                    :icon="\Filament\Support\Icons\Heroicon::ChevronDown"
                    class="hidden md:block w-4 h-4 text-gray-400"
                />
                {{--                <x-filament::icon--}}
                {{--                        :icon="\Filament\Support\Icons\Heroicon::ChevronDown"--}}
                {{--                        class="hidden md:block fi-sidebar-group-icon h-6 w-6 text-gray-400 dark:text-gray-500"--}}
                {{--                />--}}
            </div>
            {{--            <x-filament-panels::avatar.user :user="$user" />--}}
        </button>
        {{--        @if ($position === UserMenuPosition::Topbar)--}}
        {{--            <button--}}
        {{--                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"--}}
        {{--                type="button"--}}
        {{--                class="fi-user-menu-trigger"--}}
        {{--            >--}}
        {{--                <x-filament-panels::avatar.user :user="$user" loading="lazy" />--}}
        {{--            </button>--}}
        {{--        @else--}}
        {{--            <button--}}
        {{--                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"--}}
        {{--                type="button"--}}
        {{--                class="fi-user-menu-trigger"--}}
        {{--            >--}}
        {{--                <x-filament-panels::avatar.user :user="$user" loading="lazy" />--}}

        {{--                <span--}}
        {{--                    @if ($isSidebarCollapsibleOnDesktop)--}}
        {{--                        x-show="$store.sidebar.isOpen"--}}
        {{--                    @endif--}}
        {{--                    class="fi-user-menu-trigger-text"--}}
        {{--                >--}}
        {{--                    {{ filament()->getUserName($user) }}--}}
        {{--                </span>--}}

        {{--                {{--}}
        {{--                    \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronUp, alias: \Filament\View\PanelsIconAlias::USER_MENU_TOGGLE_BUTTON, attributes: new \Illuminate\View\ComponentAttributeBag([--}}
        {{--                        'x-show' => $isSidebarCollapsibleOnDesktop ? '$store.sidebar.isOpen' : null,--}}
        {{--                    ]))--}}
        {{--                }}--}}
        {{--            </button>--}}
        {{--        @endif--}}
    </x-slot>

    @if ($hasProfileHeader)
        @php
            $item = $itemsBeforeThemeSwitcher['profile'];
            $itemColor = $item->getColor();
            $itemIcon = $item->getIcon();

            unset($itemsBeforeThemeSwitcher['profile']);
        @endphp

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

        <x-filament::dropdown.header :color="$itemColor" :icon="$itemIcon">
            {{ $item->getLabel() }}
        </x-filament::dropdown.header>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
    @endif

    @if ($itemsBeforeThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsBeforeThemeSwitcher as $key => $item)
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif

    @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
        <x-filament::dropdown.list>
            <x-filament-panels::theme-switcher/>
        </x-filament::dropdown.list>
    @endif

    @if ($itemsAfterThemeSwitcher->isNotEmpty())

        <x-filament::dropdown.list>
            @foreach ($itemsAfterThemeSwitcher as $key => $item)
                {{--                @if ($key === 'logout' )--}}
                {{--                    @dd(session('web_app')===true)--}}
                {{--                @endif--}}
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @elseif ($key === 'logout')
                    {{ $item }}
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif
</x-filament::dropdown>

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
