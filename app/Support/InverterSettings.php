<?php

namespace App\Support;

/**
 * Định nghĩa các trường cài đặt biến tần — mirror CONFIG của trang
 * inverter_settings trên ESP32 (Esp32/src/inverter_settings/page.html).
 *
 * Field types:
 *   number    : reg + min/max + scale → raw = round(value*scale)
 *   select    : reg + (bit,bits) cho bitfield, hoặc cả register nếu không có bit
 *   switch    : reg + bit
 *   time      : reg (low byte = giờ, high byte = phút)
 *   timerange : 2 field time (start/end)
 *   quickcharge: reg = số phút đếm ngược (0 = tắt)
 */
class InverterSettings
{
    /**
     * @return array<int, array{title:string, tabs?:array<int,array{name:string,items:array}>|null, items?:array}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Pin lưu trữ',
                'tabs' => [
                    [
                        'name' => 'Sạc',
                        'items' => [
                            ['type' => 'number', 'reg' => 228, 'label' => 'Giới hạn điện áp sạc hệ thống', 'unit' => 'V', 'min' => 40.0, 'max' => 59.5, 'scale' => 10],
                            ['type' => 'number', 'reg' => 144, 'label' => 'Điện áp sạc nổi (Float)', 'unit' => 'V', 'min' => 50.0, 'max' => 56.0, 'scale' => 10],
                            ['type' => 'number', 'reg' => 149, 'label' => 'Điện áp cân bằng', 'unit' => 'V', 'min' => 50.0, 'max' => 59.0, 'scale' => 10],
                            ['type' => 'number', 'reg' => 227, 'label' => 'SOC dừng sạc pin', 'unit' => '%', 'min' => 10, 'max' => 101],
                            ['type' => 'number', 'reg' => 101, 'label' => 'Giới hạn dòng sạc', 'unit' => 'A', 'min' => 0, 'max' => 140],
                        ],
                    ],
                    [
                        'name' => 'Xả',
                        'items' => [
                            ['type' => 'select', 'reg' => 120, 'bit' => 4, 'bits' => 2, 'label' => 'Kiểu điều khiển xả', 'options' => [0 => 'Theo điện áp', 1 => 'Theo SOC', 2 => 'Theo cả hai']],
                            ['type' => 'number', 'reg' => 65, 'label' => 'Công suất xả (% công suất định mức)', 'unit' => '%', 'min' => 0, 'max' => 100],
                            ['type' => 'number', 'reg' => 102, 'label' => 'Dòng xả tối đa', 'unit' => 'A', 'min' => 0, 'max' => 140],
                            ['type' => 'number', 'reg' => 105, 'label' => 'SOC cắt xả khi nối lưới (on-grid)', 'unit' => '%', 'min' => 10, 'max' => 90, 'show' => [1, 2]],
                            ['type' => 'number', 'reg' => 125, 'label' => 'SOC cắt xả khi off-grid (EPS)', 'unit' => '%', 'min' => 0, 'max' => 100, 'show' => [1, 2]],
                            ['type' => 'number', 'reg' => 100, 'label' => 'Điện áp ngắt xả', 'unit' => 'V', 'min' => 40.0, 'max' => 52.0, 'scale' => 10, 'show' => [0, 2]],
                            ['type' => 'number', 'reg' => 169, 'label' => 'Điện áp cắt xả khi nối lưới (on-grid)', 'unit' => 'V', 'min' => 40.0, 'max' => 56.0, 'scale' => 10, 'show' => [0, 2]],
                            ['type' => 'number', 'reg' => 162, 'label' => 'Điện áp pin thấp (BatLowVolt)', 'unit' => 'V', 'min' => 40.0, 'max' => 50.0, 'scale' => 10, 'show' => [0, 2]],
                            ['type' => 'number', 'reg' => 163, 'label' => 'Điện áp phục hồi (BatLowBackVolt)', 'unit' => 'V', 'min' => 42.0, 'max' => 52.0, 'scale' => 10, 'show' => [0, 2]],
                            ['type' => 'switch', 'reg' => 21, 'bit' => 10, 'label' => 'Xả cưỡng bức (Forced Discharge)'],
                            ['type' => 'number', 'reg' => 82, 'label' => 'Công suất xả cưỡng bức', 'unit' => '%', 'min' => 0, 'max' => 100, 'showIf' => 'forcedDischarge'],
                            ['type' => 'time', 'reg' => 84, 'label' => 'Giờ bắt đầu xả cưỡng bức', 'showIf' => 'forcedDischarge'],
                            ['type' => 'time', 'reg' => 85, 'label' => 'Giờ kết thúc xả cưỡng bức', 'showIf' => 'forcedDischarge'],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Sạc AC',
                'items' => [
                    ['type' => 'quickcharge', 'reg' => 234, 'label' => 'Sạc cưỡng bức (phút)'],
                    ['type' => 'select', 'reg' => 120, 'bit' => 1, 'bits' => 3, 'label' => 'Kiểu sạc AC', 'options' => [0 => 'Tắt', 1 => 'Theo giờ', 2 => 'Theo điện áp', 3 => 'Theo SOC', 4 => 'Theo áp + giờ', 5 => 'Theo SOC + giờ']],
                    ['type' => 'number', 'reg' => 160, 'label' => 'SOC bắt đầu sạc AC', 'unit' => '%', 'min' => 0, 'max' => 90, 'showAc' => [3, 5]],
                    ['type' => 'number', 'reg' => 161, 'label' => 'SOC kết thúc sạc AC', 'unit' => '%', 'min' => 0, 'max' => 100, 'showAc' => [3, 5]],
                    ['type' => 'number', 'reg' => 158, 'label' => 'Điện áp bắt đầu sạc AC', 'unit' => 'V', 'min' => 38.5, 'max' => 52.0, 'scale' => 10, 'showAc' => [2, 4]],
                    ['type' => 'number', 'reg' => 159, 'label' => 'Điện áp kết thúc sạc AC', 'unit' => 'V', 'min' => 48.0, 'max' => 59.0, 'scale' => 10, 'showAc' => [2, 4]],
                    ['type' => 'timerange', 'label' => 'Khung giờ 1', 'start' => 68, 'end' => 69, 'showAc' => [1, 4, 5]],
                    ['type' => 'timerange', 'label' => 'Khung giờ 2', 'start' => 70, 'end' => 71, 'showAc' => [1, 4, 5]],
                    ['type' => 'timerange', 'label' => 'Khung giờ 3', 'start' => 72, 'end' => 73, 'showAc' => [1, 4, 5]],
                ],
            ],
            [
                'title' => 'Cài đặt Hybrid',
                'items' => [
                    ['type' => 'switch', 'reg' => 110, 'bit' => 10, 'label' => 'Chế độ Hybrid (đo tổng tải cả nhà)'],
                    ['type' => 'switch', 'reg' => 21, 'bit' => 15, 'label' => 'Bán điện lên lưới (Grid Export)'],
                    ['type' => 'number', 'reg' => 103, 'label' => 'Công suất đẩy lưới tối đa', 'unit' => '%', 'min' => 0, 'max' => 100, 'showIf' => 'gridExport'],
                ],
            ],
            [
                'title' => 'AC Coupling',
                'items' => [
                    ['type' => 'switch', 'reg' => 179, 'bit' => 11, 'label' => 'Bật AC Coupling'],
                    ['type' => 'select', 'reg' => 120, 'bit' => 7, 'bits' => 1, 'label' => 'Kiểu điều khiển (theo điện áp/SOC)', 'options' => [0 => 'Theo điện áp', 1 => 'Theo SOC']],
                    ['type' => 'number', 'reg' => 196, 'label' => 'SOC bắt đầu', 'unit' => '%', 'min' => 0, 'max' => 90, 'showGen' => [1]],
                    ['type' => 'number', 'reg' => 197, 'label' => 'SOC kết thúc', 'unit' => '%', 'min' => 20, 'max' => 100, 'showGen' => [1]],
                    ['type' => 'number', 'reg' => 194, 'label' => 'Điện áp bắt đầu', 'unit' => 'V', 'min' => 38.4, 'max' => 52.0, 'scale' => 10, 'showGen' => [0]],
                    ['type' => 'number', 'reg' => 195, 'label' => 'Điện áp kết thúc', 'unit' => 'V', 'min' => 48.0, 'max' => 59.0, 'scale' => 10, 'showGen' => [0]],
                ],
            ],
            [
                'title' => 'Ắc quy chì-axit',
                'items' => [
                    ['type' => 'number', 'reg' => 99, 'label' => 'Điện áp sạc cưỡng bức', 'unit' => 'V', 'min' => 50.0, 'max' => 59.0, 'scale' => 10],
                    ['type' => 'number', 'reg' => 204, 'label' => 'Dung lượng chì-axit', 'unit' => 'Ah', 'min' => 50, 'max' => 5000],
                ],
            ],
        ];
    }

    /**
     * Tất cả field (kể cả của timerange) dạng phẳng với key ổn định.
     *
     * @return array<string, array<string, mixed>> key => field
     */
    public static function fields(): array
    {
        $out = [];
        foreach (self::sections() as $sec) {
            $items = $sec['tabs'] ?? [['items' => $sec['items'] ?? []]];
            foreach ($items as $tab) {
                foreach ($tab['items'] as $it) {
                    self::addFields($out, $it);
                }
            }
        }

        return $out;
    }

    /**
     * Tập hợp các register cần đọc từ thiết bị.
     *
     * @return array<int, true>
     */
    public static function regs(): array
    {
        $regs = [];
        foreach (self::fields() as $f) {
            if (isset($f['reg'])) {
                $regs[(int) $f['reg']] = true;
            }
        }

        ksort($regs);

        return $regs;
    }

    /**
     * Chuyển bản đồ register thô (reg => uint16) thành các giá trị form.
     *
     * @param  array<int, int>  $regs
     * @return array<string, mixed> key => giá trị form
     */
    public static function valuesFromRegs(array $regs): array
    {
        $out = [];
        foreach (self::fields() as $key => $f) {
            if (! isset($regs[$f['reg']])) {
                continue;
            }
            $raw = (int) $regs[$f['reg']];
            $out[$key] = self::decode($f, $raw);
        }

        return $out;
    }

    /**
     * Sinh các lệnh ghi từ các field đã thay đổi (so với giá trị hiện tại).
     *
     * @param  array<string, mixed>  $new  key => giá trị form mới
     * @param  array<string, mixed>  $current  key => giá trị form hiện tại
     * @return array<int, array{reg:int, value:int, bit?:int, bits?:int}> danh sách lệnh cần ghi
     */
    public static function writesFromChanges(array $new, array $current): array
    {
        $writes = [];
        foreach (self::fields() as $key => $f) {
            if (! array_key_exists($key, $new)) {
                continue;
            }
            $old = $current[$key] ?? null;
            $val = $new[$key];

            // Bỏ qua nếu không đổi (so sánh chuỗi để tránh float 49 vs 49.0)
            if ($old !== null && (string) $old === (string) $val) {
                continue;
            }

            $writes[] = self::encodeWrite($f, $val);
        }

        return $writes;
    }

    /**
     * Lệnh ghi cho 1 field cụ thể (theo key "reg_X" hoặc "reg_X_bY").
     *
     * @return array{reg:int, value:int, bit?:int, bits?:int}|null
     */
    public static function writeForField(string $key, mixed $value): ?array
    {
        $field = self::fields()[$key] ?? null;

        if ($field === null) {
            return null;
        }

        return self::encodeWrite($field, $value);
    }

    // ============================================================
    //  Nội bộ
    // ============================================================

    private static function addFields(array &$out, array $it): void
    {
        if ($it['type'] === 'timerange') {
            self::addField($out, ['type' => 'time', 'reg' => $it['start'], 'label' => $it['label'].' — bắt đầu'] + array_intersect_key($it, array_flip(['showAc', 'showGen', 'show', 'showIf'])) + ['_range' => 'start']);
            self::addField($out, ['type' => 'time', 'reg' => $it['end'], 'label' => $it['label'].' — kết thúc'] + array_intersect_key($it, array_flip(['showAc', 'showGen', 'show', 'showIf'])) + ['_range' => 'end']);

            return;
        }

        self::addField($out, $it);
    }

    private static function addField(array &$out, array $it): void
    {
        $key = isset($it['bit']) ? "reg_{$it['reg']}_b{$it['bit']}" : "reg_{$it['reg']}";
        $out[$key] = $it;
    }

    /**
     * Giải mã giá trị register thô → giá trị form.
     */
    private static function decode(array $f, int $raw): mixed
    {
        return match ($f['type']) {
            'switch' => (($raw >> $f['bit']) & 1) === 1,
            'select' => isset($f['bit'])
                ? ($raw >> $f['bit']) & ((1 << $f['bits']) - 1)
                : $raw,
            'time' => sprintf('%02d:%02d', $raw & 0xFF, ($raw >> 8) & 0xFF),
            'quickcharge' => $raw,
            default => $f['scale'] ?? 1 > 1 ? round(($raw / ($f['scale'] ?? 1)), 2) : $raw,
        };
    }

    /**
     * Sinh payload ghi từ field + giá trị form.
     */
    private static function encodeWrite(array $f, mixed $val): array
    {
        $write = ['reg' => (int) $f['reg']];

        switch ($f['type']) {
            case 'switch':
                $write['bit'] = (int) $f['bit'];
                $write['bits'] = 1;
                $write['value'] = $val ? 1 : 0;
                break;

            case 'select':
                if (isset($f['bit'])) {
                    $write['bit'] = (int) $f['bit'];
                    $write['bits'] = (int) $f['bits'];
                    $write['value'] = (int) $val;
                } else {
                    $write['value'] = (int) $val;
                }
                break;

            case 'time':
                [$h, $m] = array_map('intval', explode(':', (string) $val));
                $write['value'] = ($h & 0xFF) | (($m & 0xFF) << 8);
                break;

            default: // number, quickcharge
                $write['value'] = ($f['scale'] ?? 1) > 1
                    ? (int) round((float) $val * (int) $f['scale'])
                    : (int) $val;
        }

        return $write;
    }
}
