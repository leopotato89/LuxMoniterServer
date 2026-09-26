/*
 * Engine hạt điện tích — port nguyên logic từ `createParticleEngine` của bản Alpine
 * (resources/views/livewire/device-realtime.blade.php), chỉ đổi sang ES module.
 *
 * Quy tắc giữ nguyên:
 * - Mỗi dây NGUỒN (PV, pin, AC couple, nguồn dự phòng, lưới nhập, biến tần phát) LUÔN
 *   có ĐÚNG 1 hạt đang chạy; hạt tới cuối dây thì quay lại đầu dây (lặp vô hạn).
 *   Chỉ vận tốc thay đổi theo công suất.
 * - Vận tốc = SPEED_K × √power, chặn trong [SPEED_MIN, SPEED_MAX] (nén dải tốc độ).
 * - Hạt đến ngã 4 (J) → sinh 1 hạt đi ra cho mỗi hướng đang hoạt động.
 * - Dây đi ra ngã 4 có công suất < 100W: nếu dây đã có hạt thì không sinh thêm.
 *
 * Toạ độ `tracks` và `J` PHẢI khớp với markup SVG trong EnergyDiagram.vue —
 * sửa hình vẽ thì phải sửa cả hai.
 */

const SPEED_K = 1.2; // speed = SPEED_K × √power
const SPEED_MIN = 12;
const SPEED_MAX = 90;

function particleSpeed(power) {
    const p = Number(power) || 0;

    if (p < 100) {
        return SPEED_MIN;
    }

    if (p > 7000) {
        return SPEED_MAX;
    }

    return Math.max(SPEED_MIN, Math.min(SPEED_MAX, SPEED_K * Math.sqrt(p)));
}

export function createParticleEngine(svgEl) {
    const J = { x: 266, y: 340 }; // ngã 4

    const tracks = {
        ac: { p1: { x: 266, y: 200 }, p2: { x: 266, y: 340 } },
        grid: { p1: { x: 195, y: 340 }, p2: { x: 266, y: 340 } },
        inv: { p1: { x: 338, y: 340 }, p2: { x: 266, y: 340 } },
        load: { p1: { x: 266, y: 340 }, p2: { x: 266, y: 470 } },
        pv: { p1: { x: 440, y: 140 }, p2: { x: 440, y: 238 } },
        batt: { p1: { x: 542, y: 340 }, p2: { x: 613, y: 340 } },
        eps: { p1: { x: 440, y: 442 }, p2: { x: 440, y: 530 } },
    };

    const lengths = {};

    Object.keys(tracks).forEach((track) => {
        lengths[track] = Math.hypot(tracks[track].p2.x - tracks[track].p1.x, tracks[track].p2.y - tracks[track].p1.y);
    });

    /** Luồng của một dây: { dir: 1|-1, power, continuous } (null = không có dòng). */
    function flow(track, latest) {
        const l = latest || {};
        const n = (value) => Number(value) || 0;

        switch (track) {
            case 'ac':
                if (n(l.accouple_power) > 0) {
                    return { dir: 1, power: n(l.accouple_power), continuous: true };
                }

                return null;

            case 'grid':
                if (n(l.export_power) > 0) {
                    return { dir: -1, power: n(l.export_power), continuous: false };
                }

                if (n(l.import_power) > 0) {
                    return { dir: 1, power: n(l.import_power), continuous: true };
                }

                return null;

            case 'inv':
                // Phát → dir=1 (biến tần về ngã 4); nạp từ lưới/AC → dir=-1 (ra khỏi ngã 4)
                if (n(l.eps_power) > 0) {
                    return null;
                }

                if (n(l.inverter_power) > 0) {
                    return { dir: 1, power: n(l.inverter_power), continuous: true };
                }

                if (n(l.charge_power) > 0 && n(l.import_power) + n(l.accouple_power) > 0) {
                    return { dir: -1, power: Math.max(n(l.import_power), n(l.accouple_power)), continuous: false };
                }

                return null;

            case 'load':
                if (n(l.eps_power) > 0) {
                    return null;
                }

                if (n(l.load_power) > 0) {
                    return { dir: 1, power: n(l.load_power), continuous: false };
                }

                return null;

            case 'pv':
                if (n(l.pv_power) > 0) {
                    return { dir: 1, power: n(l.pv_power), continuous: true };
                }

                return null;

            case 'batt':
                if (n(l.charge_power) > n(l.discharge_power)) {
                    return { dir: 1, power: n(l.charge_power), continuous: true };
                }

                if (n(l.discharge_power) > 0) {
                    return { dir: -1, power: n(l.discharge_power), continuous: true };
                }

                return null;

            case 'eps':
                if (n(l.eps_power) > 0) {
                    return { dir: 1, power: n(l.eps_power), continuous: true };
                }

                return null;

            default:
                return null;
        }
    }

    const FADE_TIME = 0.2; // giây — mờ dần ở 2 đầu để chuyển cảnh không giật

    let latest = {};
    let particles = [];
    const used = new Set();
    let uid = 0;
    let raf = null;
    let last = 0;
    let running = false;

    const slots = {};

    ['ac', 'grid', 'inv', 'load', 'pv', 'batt', 'eps'].forEach((track) => {
        slots[track] = Array.prototype.slice.call(svgEl.querySelectorAll(`[data-track="${track}"]`));
    });

    function freeSlot(track) {
        for (let i = 0; i < slots[track].length; i++) {
            if (!used.has(slots[track][i])) {
                return slots[track][i];
            }
        }

        return null;
    }

    function render(p) {
        const tr = tracks[p.track];
        const from = p.dir > 0 ? tr.p1 : tr.p2;
        const to = p.dir > 0 ? tr.p2 : tr.p1;

        p.slot.setAttribute('cx', (from.x + (to.x - from.x) * p.t).toFixed(1));
        p.slot.setAttribute('cy', (from.y + (to.y - from.y) * p.t).toFixed(1));

        let opacity = 1;

        if (p.fadeT > 0) {
            if (p.t < p.fadeT) {
                opacity = Math.max(0, p.t / p.fadeT);
            } else if (p.t > 1 - p.fadeT) {
                opacity = Math.max(0, (1 - p.t) / p.fadeT);
            }
        }

        p.slot.style.opacity = String(opacity);
    }

    function spawn(track, dir, power, t0, loop) {
        const slot = freeSlot(track);

        if (!slot) {
            return;
        }

        const p = {
            id: ++uid,
            track,
            dir,
            t: t0 || 0,
            slot,
            loop: Boolean(loop),
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

        const index = particles.indexOf(p);

        if (index >= 0) {
            particles.splice(index, 1);
        }
    }

    function endsAtJunction(p) {
        const tr = tracks[p.track];
        const to = p.dir > 0 ? tr.p2 : tr.p1;

        return to.x === J.x && to.y === J.y;
    }

    function spawnOutgoing(l) {
        const outs = [];
        const fLoad = flow('load', l);

        if (fLoad) {
            outs.push({ track: 'load', dir: 1, power: fLoad.power });
        }

        const fGrid = flow('grid', l);

        if (fGrid && !fGrid.continuous) {
            outs.push({ track: 'grid', dir: -1, power: fGrid.power });
        }

        const fInv = flow('inv', l);

        if (fInv && !fInv.continuous) {
            outs.push({ track: 'inv', dir: -1, power: fInv.power });
        }

        outs.forEach((out) => {
            // Dưới 100W (không đáng kể): dây đã có hạt thì không sinh thêm.
            if (out.power < 100 && particles.some((p) => p.track === out.track)) {
                return;
            }

            spawn(out.track, out.dir, out.power, 0);
        });
    }

    function tick(now) {
        if (!running) {
            return;
        }

        const dt = last ? Math.min(0.1, (now - last) / 1000) : 0;
        last = now;

        const arrived = [];

        // Di chuyển hạt theo vận tốc của công suất hiện tại.
        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            const f = flow(p.track, latest);

            if (!f || f.dir !== p.dir) {
                remove(p);
                continue;
            }

            p.t += (dt * particleSpeed(f.power)) / lengths[p.track];
            p.fadeT = Math.min(0.5, (particleSpeed(f.power) * FADE_TIME) / lengths[p.track]);

            if (p.t >= 1) {
                if (endsAtJunction(p)) {
                    arrived.push(p);
                }

                if (p.loop) {
                    p.t -= 1; // hạt nguồn về đầu dây, luôn đúng 1 hạt
                    render(p);
                } else {
                    remove(p);
                }
            } else {
                render(p);
            }
        }

        // Mỗi dây nguồn luôn có đúng 1 hạt đang chạy.
        ['ac', 'grid', 'inv', 'pv', 'batt', 'eps'].forEach((track) => {
            const f = flow(track, latest);

            if (!f || !f.continuous) {
                return;
            }

            const has = particles.some((p) => p.track === track && p.loop);

            if (!has) {
                spawn(track, f.dir, f.power, Math.random() * 0.08, true);
            }
        });

        // Hạt đến ngã 4 → sinh hạt đi ra.
        arrived.forEach(() => spawnOutgoing(latest));

        raf = requestAnimationFrame(tick);
    }

    return {
        setLatest(l) {
            latest = l || {};
        },
        start() {
            if (running) {
                return;
            }

            running = true;
            last = 0;
            raf = requestAnimationFrame(tick);
        },
        stop() {
            running = false;

            if (raf) {
                cancelAnimationFrame(raf);
            }

            raf = null;
        },
    };
}
