@once
@push('scripts')
<script>
    function fmt(v, dec) {
        if (v === null || v === undefined || v === '') return '—';
        var n = Number(v);
        return isNaN(n) ? '—' : n.toLocaleString('en-US', { maximumFractionDigits: dec, minimumFractionDigits: 0 });
    }
    function fmtUptime(s) {
        s = Number(s);
        if (isNaN(s) || s < 0) return '—';
        var y = Math.floor(s / 31536000); // 365 ngày/năm
        var rem = s % 31536000;
        var d = Math.floor(rem / 86400);
        rem = rem % 86400;
        var h = Math.floor(rem / 3600);
        var m = Math.floor((rem % 3600) / 60);
        var parts = [];
        if (y > 0) parts.push(y + ' năm');
        if (d > 0) parts.push(d + ' ngày');
        if (h > 0 || m > 0) parts.push((h > 0 ? h + 'h' : '') + (m > 0 ? m + 'p' : ''));
        if (parts.length === 0) parts.push('0p');
        return parts.join(' ');
    }
    function rssiLabel(v) {
        if (v === null || v === undefined) return '—';
        if (v >= -50) return 'Rất tốt';
        if (v >= -60) return 'Tốt';
        if (v >= -67) return 'Khá';
        if (v >= -75) return 'Trung bình';
        return 'Yếu';
    }
    function stateColor(s) {
        if (s === 1) return '#ef4444'; // Fault
        if (s === 0) return '#9ca3af'; // Standby
        if (s === 2) return '#f59e0b'; // Programming
        return '#22c55e';              // Đang hoạt động
    }
    function stateLabel(s) {
        var map = {
            0: 'Standby', 1: 'Fault', 2: 'Programming',
            4: 'PV nối lưới', 8: 'PV sạc ắc quy', 12: 'PV sạc + nối lưới',
            16: 'Ắc quy xả ra lưới', 20: 'PV + Ắc quy xả ra lưới',
            32: 'Sạc AC từ lưới', 40: 'PV + AC cùng sạc',
            64: 'Ắc quy chạy off-grid', 96: 'Off-grid + sạc ắc quy',
            128: 'PV chạy off-grid', 192: 'PV + Ắc quy off-grid', 136: 'PV sạc + off-grid'
        };
        return map[s] || 'Không xác định';
    }
    function deviceRealtime(config) {
        return {
            latest: config.initialLatest || {},
            online: !!config.initialOnline,
            timestamp: config.initialTimestamp || null,
            _interval: null,
            init() {
                this.refresh();
                this._interval = setInterval(() => this.refresh(), 3000);
            },
            // Dọn interval khi component bị hủy (re-render/wire:poll) để tránh fetch trùng lặp
            destroy() {
                if (this._interval) {
                    clearInterval(this._interval);
                    this._interval = null;
                }
            },
            modeLabel() {
                return stateLabel(this.latest?.state);
            },
            async refresh() {
                if (!config.url) return;
                try {
                    var res = await fetch(config.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) return;
                    var data = await res.json();
                    if (data.latest) this.latest = data.latest;
                    if (typeof data.online !== 'undefined') this.online = !!data.online;
                    if (data.timestamp) this.timestamp = data.timestamp;
                } catch (e) {}
            },
        };
    }
</script>
@endpush
@endonce
<div
    x-data="deviceRealtime({
        url: '{{ $realtimeUrl }}',
        initialLatest: @js($latest),
        initialOnline: @js($online),
        initialTimestamp: @js($timestamp),
    })"
    class="space-y-3"
>
        <div class="flex items-center">
            @include('livewire.energy-diagram', ['latest' => $latest, 'serial' => $serial])
        </div>

</div>
