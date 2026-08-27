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

    /* ============================================================
     * HẠT ĐIỆN TÍCH — JS engine
     * - Mỗi dây NGUỒN (PV, pin, AC couple, nguồn dự phòng, lưới nhập,
     *   biến tần phát) LUÔN có ĐÚNG 1 hạt đang chạy, hạt tới cuối dây sẽ
     *   quay lại đầu dây (lặp vô hạn). Chỉ vận tốc thay đổi theo công suất.
     * - VẬN TỐC theo công suất (đơn vị SVG/giây), dùng căn bậc 2 để NÉN
     *   dải tốc độ (tránh chênh lệch quá lớn giữa các mức công suất):
     *     power < 100W     → SPEED_MIN (cố định, không quá chậm)
     *     100–7000W        → SPEED_K × √power, chặn trong [SPEED_MIN, SPEED_MAX]
     *     power > 7000W    → SPEED_MAX (cố định, không quá nhanh)
     * - Hạt đến ngã 4 (266,340) → kích hoạt 1 hạt đi ra cho mỗi hướng
     *   đang hoạt động (tải / đẩy lưới / nạp biến tần). Không cần đến đồng thời.
     * - Dây đi ra ngã 4 có công suất <100W: nếu dây đã có 1 hạt đang chạy
     *   thì KHÔNG sinh thêm hạt nào nữa.
     * ============================================================ */
    var SPEED_K = 1.2;     // hằng số: speed = SPEED_K × √power (u/s). VD: 250W→17, 1kW→35, 4kW→70
    var SPEED_MIN = 12;    // u/s cố định khi power < 100W (không quá chậm)
    var SPEED_MAX = 90;    // u/s cố định khi power > 7000W (không quá nhanh)

    function particleSpeed(p) {
        p = Number(p) || 0;
        if (p < 100) return SPEED_MIN;   // dưới 100W → giới hạn dưới
        if (p > 7000) return SPEED_MAX;  // trên 7000W → giới hạn trên
        var s = SPEED_K * Math.sqrt(p);
        return Math.max(SPEED_MIN, Math.min(SPEED_MAX, s)); // 100–7000W: căn bậc 2, chặn min/max
    }

    function createParticleEngine(svgEl) {
        var J = { x: 266, y: 340 }; // ngã 4

        // Các dây là đoạn thẳng (tọa độ khớp với sơ đồ)
        var tracks = {
            ac:   { p1: { x: 266, y: 200 }, p2: { x: 266, y: 340 } },
            grid: { p1: { x: 195, y: 340 }, p2: { x: 266, y: 340 } },
            inv:  { p1: { x: 338, y: 340 }, p2: { x: 266, y: 340 } },
            load: { p1: { x: 266, y: 340 }, p2: { x: 266, y: 470 } },
            pv:   { p1: { x: 440, y: 140 }, p2: { x: 440, y: 238 } },
            batt: { p1: { x: 542, y: 340 }, p2: { x: 613, y: 340 } },
            eps:  { p1: { x: 440, y: 442 }, p2: { x: 440, y: 530 } },
        };

        // Chiều dài mỗi dây (đơn vị SVG) — để quy vận tốc (u/s) → tiến độ trên dây
        var lengths = {};
        Object.keys(tracks).forEach(function (t) {
            lengths[t] = Math.hypot(tracks[t].p2.x - tracks[t].p1.x, tracks[t].p2.y - tracks[t].p1.y);
        });

        // flow của 1 dây: { dir: 1|−1, power, continuous }
        // dir=1: p1→p2; dir=−1: p2→p1
        // continuous=true: sinh hạt liên tục (nguồn); false: chỉ sinh khi hạt đến ngã 4
        function flow(track, l) {
            l = l || {};
            var n = function (v) { return Number(v) || 0; };
            switch (track) {
                case 'ac':
                    if (n(l.accouple_power) > 0) return { dir: 1, power: n(l.accouple_power), continuous: true };
                    return null;
                case 'grid':
                    if (n(l.export_power) > 0) return { dir: -1, power: n(l.export_power), continuous: false };
                    if (n(l.import_power) > 0) return { dir: 1, power: n(l.import_power), continuous: true };
                    return null;
                case 'inv':
                    // inv: p1=(338,inverter), p2=(266,ngã 4). Phát → dir=1 (338→266, về ngã 4);
                    // nạp từ lưới/AC → dir=−1 (266→338, ra khỏi ngã 4 vào biến tần)
                    if (n(l.eps_power) > 0) return null;
                    if (n(l.inverter_power) > 0) return { dir: 1, power: n(l.inverter_power), continuous: true };
                    if (n(l.charge_power) > 0 && (n(l.import_power) + n(l.accouple_power)) > 0) {
                        return { dir: -1, power: Math.max(n(l.import_power), n(l.accouple_power)), continuous: false };
                    }
                    return null;
                case 'load':
                    if (n(l.eps_power) > 0) return null;
                    if (n(l.load_power) > 0) return { dir: 1, power: n(l.load_power), continuous: false };
                    return null;
                case 'pv':
                    if (n(l.pv_power) > 0) return { dir: 1, power: n(l.pv_power), continuous: true };
                    return null;
                case 'batt':
                    if (n(l.charge_power) > n(l.discharge_power)) return { dir: 1, power: n(l.charge_power), continuous: true };
                    if (n(l.discharge_power) > 0) return { dir: -1, power: n(l.discharge_power), continuous: true };
                    return null;
                case 'eps':
                    if (n(l.eps_power) > 0) return { dir: 1, power: n(l.eps_power), continuous: true };
                    return null;
            }
            return null;
        }

        // --- state ---
        var FADE_TIME = 0.2;         // giây — hạt mờ dần khi mới xuất hiện và tan dần trước đích (chống giật)
        var latest = {};
        var particles = [];          // { id, track, dir, t, slot, loop, fadeT }
        var used = new Set();        // slot đang chứa hạt

        var uid = 0;
        var raf = null;
        var last = 0;
        var slots = {};
        var running = false;

        ['ac', 'grid', 'inv', 'load', 'pv', 'batt', 'eps'].forEach(function (t) {
            slots[t] = Array.prototype.slice.call(svgEl.querySelectorAll('[data-track="' + t + '"]'));
        });

        function freeSlot(track) {
            for (var i = 0; i < slots[track].length; i++) {
                if (!used.has(slots[track][i])) return slots[track][i];
            }
            return null;
        }

        function render(p) {
            var tr = tracks[p.track];
            var from = p.dir > 0 ? tr.p1 : tr.p2;
            var to = p.dir > 0 ? tr.p2 : tr.p1;
            p.slot.setAttribute('cx', (from.x + (to.x - from.x) * p.t).toFixed(1));
            p.slot.setAttribute('cy', (from.y + (to.y - from.y) * p.t).toFixed(1));
            // Mờ dần ở 2 đầu để chuyển cảnh mượt (không giật)
            var op = 1;
            if (p.fadeT > 0) {
                if (p.t < p.fadeT) op = Math.max(0, p.t / p.fadeT);
                else if (p.t > 1 - p.fadeT) op = Math.max(0, (1 - p.t) / p.fadeT);
            }
            p.slot.style.opacity = String(op);
        }

        function spawn(track, dir, power, t0, loop) {
            var slot = freeSlot(track);
            if (!slot) return;
            var p = {
                id: ++uid, track: track, dir: dir, t: t0 || 0, slot: slot, loop: !!loop,
                fadeT: Math.min(0.5, (particleSpeed(power) * FADE_TIME) / lengths[track]),
            };
            particles.push(p);
            used.add(slot);
            slot.style.visibility = 'visible';
            render(p);
        }

        function remove(p) {
            p.slot.style.visibility = 'hidden';
            used.delete(p.slot);
            var i = particles.indexOf(p);
            if (i >= 0) particles.splice(i, 1);
        }

        function endsAtJunction(p) {
            var tr = tracks[p.track];
            var to = p.dir > 0 ? tr.p2 : tr.p1;
            return to.x === J.x && to.y === J.y;
        }

        // Hạt đến ngã 4 → 1 hạt cho mỗi hướng đi ra đang hoạt động
        // (luôn xuất phát ĐÚNG tâm ngã 4 266,340 — t0 = 0)
        function spawnOutgoing(l) {
            var outs = [];
            var fLoad = flow('load', l);
            if (fLoad) outs.push({ track: 'load', dir: 1, power: fLoad.power });
            var fGrid = flow('grid', l);
            if (fGrid && !fGrid.continuous) outs.push({ track: 'grid', dir: -1, power: fGrid.power });
            var fInv = flow('inv', l);
            if (fInv && !fInv.continuous) outs.push({ track: 'inv', dir: -1, power: fInv.power });
            for (var i = 0; i < outs.length; i++) {
                var o = outs[i];
                // Dưới 100W (Không đáng kể): dây đã có 1 hạt thì không sinh thêm
                if (o.power < 100 && particles.some(function (p) { return p.track === o.track; })) continue;
                spawn(o.track, o.dir, o.power, 0);
            }
        }

        function tick(now) {
            if (!running) return;
            var dt = last ? Math.min(0.1, (now - last) / 1000) : 0;
            last = now;
            var l = latest;
            var arrived = [];

            // Di chuyển hạt (tốc độ theo công suất hiện tại)
            for (var i = particles.length - 1; i >= 0; i--) {
                var p = particles[i];
                var f = flow(p.track, l);
                if (!f || f.dir !== p.dir) { remove(p); continue; }
                p.t += (dt * particleSpeed(f.power)) / lengths[p.track];
                p.fadeT = Math.min(0.5, (particleSpeed(f.power) * FADE_TIME) / lengths[p.track]);
                if (p.t >= 1) {
                    if (endsAtJunction(p)) arrived.push(p);
                    if (p.loop) {
                        p.t -= 1; // hạt nguồn tới đích → về đầu dây (luôn đúng 1 hạt), mờ dần nên không giật
                        render(p);
                    } else {
                        remove(p);
                    }
                } else {
                    render(p);
                }
            }

            // Đảm bảo mỗi dây NGUỒN (continuous) luôn có ĐÚNG 1 hạt đang chạy
            ['ac', 'grid', 'inv', 'pv', 'batt', 'eps'].forEach(function (track) {
                var f = flow(track, l);
                if (!f || !f.continuous) return;
                var has = particles.some(function (p) { return p.track === track && p.loop; });
                if (!has) spawn(track, f.dir, f.power, Math.random() * 0.08, true);
            });

            // Kích hoạt hạt đi ra ngã 4 (1 hạt cho mỗi hướng đang hoạt động)
            for (var j = 0; j < arrived.length; j++) spawnOutgoing(l);

            raf = requestAnimationFrame(tick);
        }

        return {
            setLatest: function (l) { latest = l || {}; },
            start: function () {
                if (running) return;
                running = true;
                last = 0;
                raf = requestAnimationFrame(tick);
            },
            stop: function () {
                running = false;
                if (raf) cancelAnimationFrame(raf);
                raf = null;
            },
        };
    }

    function deviceRealtime(config) {
        return {
            latest: config.initialLatest || {},
            online: !!config.initialOnline,
            timestamp: config.initialTimestamp || null,
            _interval: null,
            _particleEngine: null,
            // Giá trị đang hiển thị khi đếm lên/xuống (reactive để x-text tự cập nhật)
            _animVals: {},
            _animRafs: {},
            _animTargets: {},
            init() {
                this.refresh();
                this._interval = setInterval(() => this.refresh(), 3000);
            },
            // Khởi tạo engine hạt (gọi từ x-init trên <svg>)
            initParticles(svg) {
                if (this._particleEngine) this._particleEngine.stop();
                this._particleEngine = createParticleEngine(svg);
                this._particleEngine.setLatest(this.latest);
                this._particleEngine.start();
            },
            // Dọn interval + engine hạt khi component bị hủy (re-render/wire:poll)
            destroy() {
                if (this._interval) {
                    clearInterval(this._interval);
                    this._interval = null;
                }
                if (this._particleEngine) {
                    this._particleEngine.stop();
                    this._particleEngine = null;
                }
            },
            // Hiệu ứng đếm lên/xuống: trả về giá trị đang animate của 1 key
            anim(key, target) {
                target = Number(target);
                if (isNaN(target)) target = 0;
                if (!(key in this._animVals)) { this._animVals[key] = target; return target; }
                var cur = this._animVals[key];
                if (cur !== target && this._animTargets[key] !== target) {
                    this._animate(key, cur, target);
                }
                return this._animVals[key];
            },
            _animate(key, from, to) {
                if (this._animRafs[key]) cancelAnimationFrame(this._animRafs[key]);
                this._animTargets[key] = to;
                var start = performance.now();
                var dur = 700; // ms
                var self = this;
                var step = function (now) {
                    var k = Math.min(1, (now - start) / dur);
                    var eased = 1 - Math.pow(1 - k, 3); // easeOutCubic
                    self._animVals[key] = from + (to - from) * eased;
                    if (k < 1) {
                        self._animRafs[key] = requestAnimationFrame(step);
                    } else {
                        self._animVals[key] = to;
                        delete self._animRafs[key];
                        delete self._animTargets[key];
                    }
                };
                this._animRafs[key] = requestAnimationFrame(step);
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
                    if (this._particleEngine) this._particleEngine.setLatest(this.latest);
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
