<x-filament-widgets::widget class="fi-wi-realtime" wire:poll.3s>
    <x-filament::section
        :heading="$this->getDeviceName() ? 'Dữ liệu thời gian thực — '.$this->getDeviceName() : 'Dữ liệu thời gian thực'"
        :description="$deviceSerial ? 'Cập nhật mỗi 3 giây (từ Redis)' : 'Chưa chọn thiết bị'"
    >
        <x-slot name="afterHeader">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::input.wrapper inline-prefix class="min-w-56">
                    <x-filament::input.select wire:model.live="deviceSerial">
                        @foreach ($this->getDevices() as $device)
                            <option value="{{ $device->serial }}">{{ $device->name }} ({{ $device->serial }})</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                @php $online = $this->isOnline(); @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $online ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700' }}">
                    <span class="h-2 w-2 rounded-full {{ $online ? 'bg-success-500' : 'bg-danger-500' }}"></span>
                    {{ $online ? 'Online' : 'Offline' }}
                </span>
            </div>
        </x-slot>

        @php $latest = $this->getLatest(); @endphp

        @if (! $latest)
            <div class="flex items-center justify-center rounded-xl border border-dashed border-gray-300 p-10 text-sm text-gray-500">
                Không có dữ liệu telemetry cho thiết bị này.
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <x-dashboard.stat label="SOC" :value="$latest['battery_soc'] ?? '—'" suffix="%" />
                <x-dashboard.stat label="Điện áp pin" :value="$latest['battery_voltage'] ?? '—'" suffix="V" />
                <x-dashboard.stat label="Công suất sạc" :value="$latest['charge_power'] ?? '—'" suffix="W" />
                <x-dashboard.stat label="Công suất xả" :value="$latest['discharge_power'] ?? '—'" suffix="W" />
                <x-dashboard.stat label="PV (sản lượng)" :value="$latest['pv_power'] ?? '—'" suffix="W" />
                <x-dashboard.stat label="Tải sử dụng" :value="$latest['load_power'] ?? '—'" suffix="W" />
                <x-dashboard.stat label="Xuất lưới" :value="$latest['export_power'] ?? '—'" suffix="W" />
                <x-dashboard.stat label="Nhập lưới" :value="$latest['import_power'] ?? '—'" suffix="W" />
                <x-dashboard.stat label="Công suất biến tần" :value="$latest['inverter_power'] ?? '—'" suffix="W" />
                <x-dashboard.stat label="Nhiệt độ trong" :value="$latest['inner_temp'] ?? '—'" suffix="°C" />
                <x-dashboard.stat label="RSSI" :value="$latest['rssi'] ?? '—'" suffix="dBm" />
                <x-dashboard.stat label="Uptime" :value="$latest['uptime'] ?? '—'" suffix="s" />
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
