@php
    use App\Services\InfluxService;
    use Illuminate\Support\Carbon;

    /** @var \App\Models\Device|null $record */
    $serial = $record?->serial;
    $date = $get('date') ?? now()->format('Y-m-d');

    // Field từ bản ghi cuối cùng của ngày được chọn (giá trị tích lũy trong ngày, kWh)
    $fields = $serial
        ? app(InfluxService::class)->daily($serial, $date)
        : [];

    $fmt = fn (mixed $v): string => is_numeric($v)
        ? number_format((float) $v, 1)
        : '—';

    $pvTotal = $fields['pv_energy_day'] ?? null;
    $acCouple = $fields['accouple_energy_day'] ?? null;
    $load = $fields['load_energy_day'] ?? null;
    $export = $fields['export_energy_day'] ?? null;
    $import = $fields['import_energy_day'] ?? null;
    $charge = $fields['charge_energy_day'] ?? null;
    $discharge = $fields['discharge_energy_day'] ?? null;
    $soc = $fields['battery_soc'] ?? null;

    $generation = collect([$pvTotal, $acCouple])->filter(fn ($v) => is_numeric($v))->sum();
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    {{-- Sản lượng --}}
    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-2">
            <div
                class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                <x-filament::icon icon="heroicon-o-sun" class="h-4 w-4"/>
            </div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Sản lượng</span>
        </div>
        <div class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
            {{ $fmt($generation) }} <span class="text-sm font-normal text-gray-400">kWh</span>
        </div>
        <div class="mt-2 space-y-1 border-t border-gray-100 pt-2 text-sm dark:border-gray-800">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">PV</span>
                <span class="font-medium">{{ $fmt($pvTotal) }} kWh</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">AC Couple</span>
                <span class="font-medium">{{ $fmt($acCouple) }} kWh</span>
            </div>
        </div>
    </div>

    {{-- Tiêu thụ --}}
    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-2">
            <div
                class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                <x-filament::icon icon="heroicon-o-bolt" class="h-4 w-4"/>
            </div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Tiêu thụ</span>
        </div>
        <div class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
            {{ $fmt($load) }} <span class="text-sm font-normal text-gray-400">kWh</span>
        </div>
        <div class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-400 dark:border-gray-800">
            Tổng điện năng tiêu thụ trong ngày
        </div>
    </div>

    {{-- Điện lưới --}}
    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-2">
            <div
                class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4 w-4"/>
            </div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Điện lưới</span>
        </div>
        <div class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Đẩy lưới</span>
                <span class="font-medium">{{ $fmt($export) }} kWh</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Lấy lưới</span>
                <span class="font-medium">{{ $fmt($import) }} kWh</span>
            </div>
        </div>
        <div class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-400 dark:border-gray-800">
            Trao đổi điện năng với lưới
        </div>
    </div>

    {{-- Pin --}}
    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-2">
            <div
                class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                <x-filament::icon icon="heroicon-o-battery-100" class="h-4 w-4"/>
            </div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Pin</span>
        </div>
        <div class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Sạc</span>
                <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ $fmt($charge) }} kWh</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 dark:text-gray-400">Xả</span>
                <span class="font-medium text-amber-600 dark:text-amber-400">{{ $fmt($discharge) }} kWh</span>
            </div>
        </div>
        <div class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-400 dark:border-gray-800">
            Năng lượng nạp/xả trong ngày
        </div>
    </div>
</div>
