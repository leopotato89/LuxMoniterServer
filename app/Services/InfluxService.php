<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Query lịch sử time-series từ InfluxDB 2.x qua Flux.
 */
class InfluxService
{
    /**
     * Lấy chuỗi dữ liệu lịch sử của một field theo thiết bị.
     *
     * @return array<int, array{time: string, value: float}>
     */
    public function history(
        string $serial,
        string $field,
        string $start = '-6h',
        string $stop = 'now()',
        string $window = '1m',
    ): array {
        return \Illuminate\Support\Facades\Cache::remember("influx_history_{$serial}_{$field}_{$start}_{$stop}_{$window}", 60, function () use ($serial, $field, $start, $stop, $window) {
            $flux = sprintf(
                'from(bucket: "%s")
      |> range(start: %s, stop: %s)
      |> filter(fn: (r) => r._measurement == "inverter")
      |> filter(fn: (r) => r._field == "%s")
      |> filter(fn: (r) => r.device == "%s")
      |> aggregateWindow(every: %s, fn: mean, createEmpty: false)
      |> keep(columns: ["_time", "_value"])
      |> sort(columns: ["_time"])',
                config('influx.bucket'),
                $start,
                $stop,
                $field,
                $serial,
                $window,
            );

            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('influx.token'),
                'Accept' => 'application/csv',
            ])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
                'query' => $flux,
                'type' => 'flux',
            ]);

            if (! $response->successful()) {
                return [];
            }

            return $this->parseAnnotatedCsv($response->body());
        });
    }

    /**
     * Dữ liệu dashboard: 5 chuỗi trên cùng trục thời gian (1 query duy nhất).
     * - soc:  battery_soc (%)
     * - pv:   pv_power (W)
     * - load: load_power (W)
     * - charge_net: charge_power - discharge_power (W) — sạc = (+), xả = (-)
     * - grid_net: export_power - import_power (W) — đẩy lưới = (+), lấy lưới = (-)
     *
     * @return array{labels: array<int, string>, series: array<string, array<int, float>>}
     */
    public function dashboard(string $serial, string $start, string $stop, string $window): array
    {
        return \Illuminate\Support\Facades\Cache::remember("influx_dashboard_{$serial}_{$start}_{$stop}_{$window}", 60, function () use ($serial, $start, $stop, $window) {
            $fields = ['battery_soc', 'pv_power', 'accouple_power', 'load_power', 'charge_power', 'discharge_power', 'export_power', 'import_power'];

            $flux = sprintf(
                'from(bucket: "%s")
      |> range(start: %s, stop: %s)
      |> filter(fn: (r) => r._measurement == "inverter")
      |> filter(fn: (r) => r.device == "%s")
      |> filter(fn: (r) => %s)
      |> aggregateWindow(every: %s, fn: mean, createEmpty: false)
      |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")
      |> map(fn: (r) => ({ r with
          soc: if exists r.battery_soc then r.battery_soc else 0.0,
          pv: (if exists r.pv_power then r.pv_power else 0.0) + (if exists r.accouple_power then r.accouple_power else 0.0),
          load: if exists r.load_power then r.load_power else 0.0,
          charge_net: (if exists r.charge_power then r.charge_power else 0.0) - (if exists r.discharge_power then r.discharge_power else 0.0),
          grid_net: (if exists r.export_power then r.export_power else 0.0) - (if exists r.import_power then r.import_power else 0.0),
          export_power: if exists r.export_power then r.export_power else 0.0,
          import_power: if exists r.import_power then r.import_power else 0.0
      }))
      |> keep(columns: ["_time", "soc", "pv", "load", "charge_net", "grid_net", "export_power", "import_power"])
      |> sort(columns: ["_time"])',
                config('influx.bucket'),
                $start,
                $stop,
                $serial,
                implode(' or ', array_map(fn (string $f): string => 'r._field == "'.$f.'"', $fields)),
                $window,
            );

            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('influx.token'),
                'Accept' => 'application/csv',
            ])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
                'query' => $flux,
                'type' => 'flux',
            ]);

            if (! $response->successful()) {
                return ['labels' => [], 'series' => ['soc' => [], 'pv' => [], 'load' => [], 'charge_net' => [], 'grid_net' => [], 'export_power' => [], 'import_power' => []]];
            }

            return $this->parseDashboardCsv($response->body());
        });
    }

    /**
     * Bản ghi CUỐI CÙNG của ngày được chọn — chứa các field tích lũy trong ngày (*_energy_day).
     *
     * @param  string  $date  Định dạng Y-m-d (giờ địa phương)
     * @return array<string, float|string>
     */
    public function daily(string $serial, string $date): array
    {
        return \Illuminate\Support\Facades\Cache::remember("influx_daily_{$serial}_{$date}", 60, function () use ($serial, $date) {
            $start = Carbon::parse($date, config('app.timezone'))->startOfDay();
            $stop = (clone $start)->addDay();

            $flux = sprintf(
                'from(bucket: "%s")
      |> range(start: %s, stop: %s)
      |> filter(fn: (r) => r._measurement == "inverter")
      |> filter(fn: (r) => r.device == "%s")
      |> last()
      |> keep(columns: ["_field", "_value"])',
                config('influx.bucket'),
                $start->copy()->setTimezone('UTC')->toIso8601ZuluString(),
                $stop->copy()->setTimezone('UTC')->toIso8601ZuluString(),
                $serial,
            );

            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('influx.token'),
                'Accept' => 'application/csv',
            ])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
                'query' => $flux,
                'type' => 'flux',
            ]);

            if (! $response->successful()) {
                return [];
            }

            return $this->parseFieldValueCsv($response->body());
        });
    }

    /**
     * Điện năng tích lũy của TỪNG NGÀY (theo giờ địa phương) trong khoảng thời gian.
     *
     * Lấy giá trị CUỐI CÙNG (*_energy_day) của mỗi ngày, nhóm theo local day.
     * Các cột trả về: pv, accouple, charge, discharge, load, import, export.
     *
     * @return array<string, array<string, float>> keyed by 'Y-m-d'
     */
    public function dailyEnergy(string $serial, string $start, string $stop): array
    {
        return \Illuminate\Support\Facades\Cache::remember("influx_dailyEnergy_{$serial}_{$start}_{$stop}", 300, function () use ($serial, $start, $stop) {
            $fields = ['pv_energy_day', 'accouple_energy_day', 'charge_energy_day', 'discharge_energy_day', 'load_energy_day', 'import_energy_day', 'export_energy_day'];
            $timezone = config('app.timezone');

            $flux = sprintf(
                'import "timezone"
option location = timezone.location(name: "%s")

from(bucket: "%s")
  |> range(start: %s, stop: %s)
  |> filter(fn: (r) => r._measurement == "inverter")
  |> filter(fn: (r) => r.device == "%s")
  |> filter(fn: (r) => %s)
  |> aggregateWindow(every: 1d, fn: max, createEmpty: false, timeSrc: "_start")
  |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")',
                $timezone,
                config('influx.bucket'),
                $start,
                $stop,
                $serial,
                implode(' or ', array_map(fn (string $f): string => 'r._field == "'.$f.'"', $fields)),
            );

            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('influx.token'),
                'Accept' => 'application/csv',
            ])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
                'query' => $flux,
                'type' => 'flux',
            ]);

            if (! $response->successful()) {
                return [];
            }

            return $this->parseDailyEnergyCsv($response->body(), $fields);
        });
    }

    /**
     * Tổng điện năng theo tháng (dành cho chế độ xem Năm).
     *
     * @return array<string, array<string, float>> keyed by 'Y-m'
     */
    public function monthlyEnergy(string $serial, string $start, string $stop): array
    {
        return \Illuminate\Support\Facades\Cache::remember("influx_monthlyEnergy_{$serial}_{$start}_{$stop}", 3600, function () use ($serial, $start, $stop) {
            $fields = ['pv_energy_day', 'accouple_energy_day', 'charge_energy_day', 'discharge_energy_day', 'load_energy_day', 'import_energy_day', 'export_energy_day'];
            $timezone = config('app.timezone');

            $flux = sprintf(
                'import "timezone"
option location = timezone.location(name: "%s")

from(bucket: "%s")
  |> range(start: %s, stop: %s)
  |> filter(fn: (r) => r._measurement == "inverter")
  |> filter(fn: (r) => r.device == "%s")
  |> filter(fn: (r) => %s)
  |> aggregateWindow(every: 1d, fn: max, createEmpty: false, timeSrc: "_start")
  |> aggregateWindow(every: 1mo, fn: sum, createEmpty: false, timeSrc: "_start")
  |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")',
                $timezone,
                config('influx.bucket'),
                $start,
                $stop,
                $serial,
                implode(' or ', array_map(fn (string $f): string => 'r._field == "'.$f.'"', $fields)),
            );

            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('influx.token'),
                'Accept' => 'application/csv',
            ])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
                'query' => $flux,
                'type' => 'flux',
            ]);

            if (! $response->successful()) {
                return [];
            }

            return $this->parseEnergyCsvWithFormat($response->body(), $fields, 'Y-m');
        });
    }

    /**
     * Tổng điện năng theo năm (dành cho chế độ xem Tất cả).
     *
     * @return array<string, array<string, float>> keyed by 'Y'
     */
    public function yearlyEnergy(string $serial, string $start = '2020-01-01T00:00:00Z', string $stop = 'now()'): array
    {
        return \Illuminate\Support\Facades\Cache::remember("influx_yearlyEnergy_{$serial}_{$start}_{$stop}", 3600, function () use ($serial, $start, $stop) {
            $fields = ['pv_energy_day', 'accouple_energy_day', 'charge_energy_day', 'discharge_energy_day', 'load_energy_day', 'import_energy_day', 'export_energy_day'];
            $timezone = config('app.timezone');

            $flux = sprintf(
                'import "timezone"
option location = timezone.location(name: "%s")

from(bucket: "%s")
  |> range(start: %s, stop: %s)
  |> filter(fn: (r) => r._measurement == "inverter")
  |> filter(fn: (r) => r.device == "%s")
  |> filter(fn: (r) => %s)
  |> aggregateWindow(every: 1d, fn: max, createEmpty: false, timeSrc: "_start")
  |> aggregateWindow(every: 1y, fn: sum, createEmpty: false, timeSrc: "_start")
  |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")',
                $timezone,
                config('influx.bucket'),
                $start,
                $stop,
                $serial,
                implode(' or ', array_map(fn (string $f): string => 'r._field == "'.$f.'"', $fields)),
            );

            $response = Http::withHeaders([
                'Authorization' => 'Token '.config('influx.token'),
                'Accept' => 'application/csv',
            ])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
                'query' => $flux,
                'type' => 'flux',
            ]);

            if (! $response->successful()) {
                return [];
            }

            return $this->parseEnergyCsvWithFormat($response->body(), $fields, 'Y');
        });
    }

    /**
     * Thời gian của bản ghi MỚI NHẤT của thiết bị (lấy từ cột _time của InfluxDB).
     */
    public function latestTime(string $serial, string $range = '-3h'): ?Carbon
    {
        $flux = sprintf(
            'from(bucket: "%s")
  |> range(start: %s)
  |> filter(fn: (r) => r._measurement == "inverter")
  |> filter(fn: (r) => r.device == "%s")
  |> keep(columns: ["_time"])
  |> group()
  |> max(column: "_time")
  |> keep(columns: ["_time"])',
            config('influx.bucket'),
            $range,
            $serial,
        );

        $response = Http::withHeaders([
            'Authorization' => 'Token '.config('influx.token'),
            'Accept' => 'application/csv',
        ])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
            'query' => $flux,
            'type' => 'flux',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $header = null;

        foreach (preg_split('/\r?\n/', trim($response->body())) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = str_getcsv($line);

            if ($header === null) {
                $header = array_flip($cols);

                continue;
            }

            $time = $cols[$header['_time']] ?? null;

            if ($time === null || $time === '') {
                continue;
            }

            return Carbon::parse($time)->setTimezone(config('app.timezone'));
        }

        return null;
    }

    /**
     * Parse CSV pivoted (cột _time + các field *_energy_day) thành mảng theo ngày.
     *
     * Vì dữ liệu thô ghi nhiều điểm/giây, ta nhóm theo NGÀY ĐỊA PHƯƠNG và giữ giá trị
     * CUỐI CÙNG của mỗi ngày (các dòng đã sắp xếp _time tăng dần).
     *
     * @param  array<int, string>  $fields
     * @return array<string, array<string, float>>
     */
    protected function parseDailyEnergyCsv(string $csv, array $fields): array
    {
        $header = null;
        $lastByDay = [];

        foreach (preg_split('/\r?\n/', trim($csv)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = str_getcsv($line);

            if ($header === null) {
                $header = array_flip($cols);

                continue;
            }

            $time = $cols[$header['_time']] ?? null;

            if ($time === null || $time === '') {
                continue;
            }

            $day = Carbon::parse($time)->setTimezone(config('app.timezone'))->format('Y-m-d');

            foreach ($fields as $f) {
                $lastByDay[$day][$f] = (float) ($cols[$header[$f]] ?? 0);
            }
        }

        $days = [];

        foreach ($lastByDay as $day => $row) {
            $days[$day] = [
                'pv' => (float) ($row['pv_energy_day'] ?? 0),
                'accouple' => (float) ($row['accouple_energy_day'] ?? 0),
                'charge' => (float) ($row['charge_energy_day'] ?? 0),
                'discharge' => (float) ($row['discharge_energy_day'] ?? 0),
                'load' => (float) ($row['load_energy_day'] ?? 0),
                'import' => (float) ($row['import_energy_day'] ?? 0),
                'export' => (float) ($row['export_energy_day'] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Parse CSV pivoted (cột _time + các field *_energy_day) thành mảng với định dạng thời gian chỉ định.
     */
    protected function parseEnergyCsvWithFormat(string $csv, array $fields, string $format = 'Y-m'): array
    {
        $header = null;
        $lastByDay = [];

        foreach (preg_split('/\r?\n/', trim($csv)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = str_getcsv($line);

            if ($header === null) {
                $header = array_flip($cols);

                continue;
            }

            $time = $cols[$header['_time']] ?? null;

            if ($time === null || $time === '') {
                continue;
            }

            $day = Carbon::parse($time)->setTimezone(config('app.timezone'))->format($format);

            foreach ($fields as $f) {
                if (! isset($cols[$header[$f]]) || $cols[$header[$f]] === '') {
                    continue;
                }

                $lastByDay[$day][$f] = (float) ($cols[$header[$f]] ?? 0);
            }
        }

        $days = [];

        foreach ($lastByDay as $day => $row) {
            $days[$day] = [
                'pv' => (float) ($row['pv_energy_day'] ?? 0),
                'accouple' => (float) ($row['accouple_energy_day'] ?? 0),
                'charge' => (float) ($row['charge_energy_day'] ?? 0),
                'discharge' => (float) ($row['discharge_energy_day'] ?? 0),
                'load' => (float) ($row['load_energy_day'] ?? 0),
                'import' => (float) ($row['import_energy_day'] ?? 0),
                'export' => (float) ($row['export_energy_day'] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Parse annotated CSV dạng (cột _field + _value).
     *
     * @return array<string, float|string>
     */
    protected function parseFieldValueCsv(string $csv): array
    {
        $fields = [];
        $header = null;

        foreach (preg_split('/\r?\n/', trim($csv)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = str_getcsv($line);

            if ($header === null) {
                $header = array_flip($cols);

                continue;
            }

            $field = $cols[$header['_field']] ?? null;
            $value = $cols[$header['_value']] ?? null;

            if ($field !== null && $value !== null && $value !== '') {
                $fields[$field] = is_numeric($value) ? (float) $value : $value;
            }
        }

        return $fields;
    }

    /**
     * Parse annotated CSV dạng pivoted (cột _time + soc/pv/load/charge_net/grid_net).
     *
     * @return array{labels: array<int, string>, series: array<string, array<int, float>>}
     */
    protected function parseDashboardCsv(string $csv): array
    {
        $labels = [];
        $series = ['soc' => [], 'pv' => [], 'load' => [], 'charge_net' => [], 'grid_net' => [], 'export_power' => [], 'import_power' => []];
        $header = null;

        foreach (preg_split('/\r?\n/', trim($csv)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = str_getcsv($line);

            if ($header === null) {
                $header = array_flip($cols);

                continue;
            }

            $labels[] = $cols[$header['_time']] ?? '';

            foreach (array_keys($series) as $key) {
                $raw = $cols[$header[$key]] ?? '';
                $series[$key][] = $raw !== '' ? (float) $raw : 0.0;
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Parse annotated CSV trả về từ InfluxDB v2.
     *
     * @return array<int, array{time: string, value: float}>
     */
    protected function parseAnnotatedCsv(string $csv): array
    {
        $points = [];
        $header = null;

        foreach (preg_split('/\r?\n/', trim($csv)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = str_getcsv($line);

            if ($header === null) {
                $header = array_flip($cols);

                continue;
            }

            $time = $cols[$header['_time']] ?? null;
            $value = $cols[$header['_value']] ?? null;

            if ($time !== null && $value !== null && $value !== '') {
                $points[] = [
                    'time' => $time,
                    'value' => (float) $value,
                ];
            }
        }

        return $points;
    }
}
