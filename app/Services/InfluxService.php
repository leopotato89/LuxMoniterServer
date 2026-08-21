<?php

namespace App\Services;

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
        $fields = ['battery_soc', 'pv_power', 'load_power', 'charge_power', 'discharge_power', 'export_power', 'import_power'];

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
      pv: if exists r.pv_power then r.pv_power else 0.0,
      load: if exists r.load_power then r.load_power else 0.0,
      charge_net: (if exists r.charge_power then r.charge_power else 0.0) - (if exists r.discharge_power then r.discharge_power else 0.0),
      grid_net: (if exists r.export_power then r.export_power else 0.0) - (if exists r.import_power then r.import_power else 0.0)
  }))
  |> keep(columns: ["_time", "soc", "pv", "load", "charge_net", "grid_net"])
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
            return ['labels' => [], 'series' => ['soc' => [], 'pv' => [], 'load' => [], 'charge_net' => [], 'grid_net' => []]];
        }

        return $this->parseDashboardCsv($response->body());
    }

    /**
     * Parse annotated CSV dạng pivoted (cột _time + soc/pv/load/charge_net/grid_net).
     *
     * @return array{labels: array<int, string>, series: array<string, array<int, float>>}
     */
    protected function parseDashboardCsv(string $csv): array
    {
        $labels = [];
        $series = ['soc' => [], 'pv' => [], 'load' => [], 'charge_net' => [], 'grid_net' => []];
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
