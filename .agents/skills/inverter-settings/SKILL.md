---
name: inverter-settings
description: "Use when adding, changing or debugging an inverter setting (register field): the InverterSettings register map and its encode/decode rules, the DeviceSetup form schema, the MQTT read/write round-trip in DeviceSettingsService/MqttService, or device_commands audit rows."
---

# Inverter settings (register map round-trip)

`app/Support/InverterSettings.php` is the source of truth; it mirrors the ESP32 settings page (`Esp32/src/inverter_settings/page.html`). Change both together and keep `reg`, `label`, `unit` identical.

## Field entry

```php
['type' => 'number', 'reg' => 228, 'label' => '…', 'unit' => 'V', 'min' => 40.0, 'max' => 59.5, 'scale' => 10]
```

| type | encoding |
| --- | --- |
| `number` | `raw = round(value * scale)`, `scale` absent = 1 |
| `switch` | 1 bit: `raw bit = value ? 1 : 0` |
| `select` | with `bit`+`bits` = bitfield slice; without them = whole register |
| `time` | low byte = giờ, high byte = phút (`HH:MM`) |
| `timerange` | two `time` fields, `start`/`end` regs — expands to `reg_<start>` / `reg_<end>` |
| `quickcharge` | register = số phút đếm ngược, `0` = tắt |

Visibility keys (`show`, `showAc`, `showGen`, `showIf`) affect the form only — keep them when copying an entry.

## Keys and derived helpers

- Field key is `reg_<n>`, or `reg_<n>_b<bit>` when `bit` is set. These keys are the form field names and the argument of `saveField()` — never rename an existing one.
- `sections()` (UI tree) → `fields()` (flat key => field) → `regs()` (registers to read) → `valuesFromRegs()` (decode) → `writesFromChanges()` (diff, string compare so `49` and `49.0` are equal) → `writeForField()`.
- Adding a bit to a register that already carries fields: pick a free bit, never reuse another field's `bit`.

## Round-trip

1. Read: `DeviceSettingsService::read()` → `MqttService::publishRead()` publishes `{"action":"read"}` on `luxmonitor/<serial>/cmd/settings` → ESP32 → `cmd/result` → worker → Redis `device:<serial>:cmd_result` → `pollRegs()` blocks (100 ms `usleep`, default 25 s) until `{"action":"read","regs":{...}}` → `valuesFromRegs()`.
2. Write: `save()` (diff) or `saveField()` (single key) → `MqttService::publishSettings()` → same topic with `{"reg":X,"value":Y[,"bit":B,"bits":N]}`.
3. Every write must create a `device_commands` row (`reg`, `bit`, `value`, `request_json`, `status` = `sent|failed`). `send()`/`saveField()` returns bool, not throws, when MQTT is unreachable.
4. Failures surface as `RuntimeException` ("Không gửi được lệnh đọc MQTT.", "Thiết bị không phản hồi lệnh đọc trong Ns.") and are caught at the Filament boundary in `DeviceSettingsService::action()` → `Notification::make()->danger()`.

## Testing without hardware

There is no broker, Redis or Influx in the test environment (SQLite in memory). Bind fakes for the concrete classes (no interfaces exist):

```php
$this->mock(MqttService::class)->shouldReceive('publishRead')->andReturn(true);
$this->mock(RealtimeService::class)->shouldReceive('commandResult')->andReturn(json_encode(['action' => 'read', 'regs' => [228 => 500]]));
```

Encode/decode is pure static — cover it directly with `valuesFromRegs()` / `writesFromChanges()` / `writeForField()` expectations instead of going through HTTP.

## Traps

- `read()` blocks up to 25 s inside a web request (and `pollRegs()` is a busy loop) — never call it in a loop or from a queue-less path.
- A write is fire-and-forget: it does **not** wait for `cmd/result`, so "Đã lưu cài đặt" is not proof the inverter accepted the value.
- Missing `cmd/result` means the external Node worker is down, not an app bug.
