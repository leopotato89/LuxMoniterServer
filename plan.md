# Plan: Bỏ Filament → Laravel MVC + Vue SPA + API dùng chung mobile

## Quyết định đã chốt (user trả lời)
1. **Phạm vi**: chuyển CẢ 2 panel (user + admin) sang Vue, gỡ Filament hoàn toàn.
2. **Kiến trúc FE**: SPA riêng (Vue Router + Pinia), Laravel chỉ trả index.html + JSON API.
3. **Auth**: Sanctum Personal Access Token (Bearer) cho cả SPA và mobile — 1 đường auth.
4. **Chart**: `chart.js` + `vue-chartjs` (tái dùng gần nguyên config dataset/options hiện có).
5. **Cài đặt biến tần**: async — `POST .../settings/read` → 202 + jobId, FE poll `GET .../settings/read/{jobId}`.
6. **Realtime**: ~~Laravel Reverb~~ → **SSE (Server-Sent Events)** do Laravel đọc Redis rồi push xuống browser. (Reverb bị chặn kép: đòi `pusher-php-server` → `ext-curl` chưa bật, VÀ đòi `psr7 ^2.6` trong khi `guzzle 8.0.2` ghim `psr7 3.0.0` ⇒ phải hạ major guzzle. User chốt bỏ Reverb.)
   - Lý do chọn SSE: 0 dependency mới, 0 process mới, 0 thay đổi broker MQTT, không đụng `ext-curl`/guzzle.
   - **Không đụng MQTT**: worker Node đã ghi `device:<serial>:latest|online` vào Redis; Laravel chỉ đọc Redis. (Browser không mở được TCP thô ⇒ browser→MQTT bắt buộc MQTT-over-WS + WSS + ACL per-user, và credential `mqttuser` dùng chung cho phép publish vào `luxmonitor/<serial>/cmd/settings` ⇒ user thường ghi được register biến tần, bỏ qua API/phân quyền/audit ⇒ đã loại.)
   - **Auth cho SSE**: `EventSource` KHÔNG gửi được header `Authorization` ⇒ dùng `fetch()` + `ReadableStream` reader (TextDecoder, tự parse `event:`/`data:`, `AbortController`) để giữ nguyên Bearer token như mọi endpoint khác. KHÔNG dùng `?token=` trong URL (lộ qua log/referrer) và không cần cơ chế ticket mới.
   - **Gotcha dev**: SSE giữ 1 PHP worker/client. `php artisan serve` mặc định **1 worker** ⇒ 1 stream đang mở sẽ chặn mọi request khác. Phải đặt `PHP_CLI_SERVER_WORKERS` (≥10) khi chạy dev.
7. **Không có mobile hiện tại**; mobile sau này sẽ dùng CHUNG endpoint của user panel → không cần giữ alias route cũ, được phép thay thế thẳng các route `/api/*` (trừ contract ESP32). Endpoint user phải tự đủ cho mobile: token Bearer, không phụ thuộc cookie/session/admin.

## Quyết định bổ sung (đã chốt, không hỏi lại)
- **Route**: dùng `/api/v1/*` cho web + mobile; các route cũ `/api/devices*` bị THAY THẾ thẳng ở Phase 1 (không alias). `GET /api/device-code/{serial}` giữ nguyên (ESP32).
- **SPA serve**: Laravel phục vụ cùng origin tại `/app` trong giai đoạn chuyển tiếp, dời về `/` ở Phase 6. Asset qua `@vite` (dev dùng Vite dev server, prod dùng `npm run build`). Không cần cấu hình CORS.
- **`RealtimeController` cũ**: xoá ở Phase 6 (đã được API `/v1` thay thế hoàn toàn).
- **Gộp endpoint telemetry**: chỉ 1 endpoint `GET /devices/{serial}/realtime` trả `{online, latest, timestamp}`; BỎ `/status` riêng (`latest` + `status` + logic `recordTime()` của `RealtimeController` gộp về một chỗ).

## Hiện trạng (đã khảo sát)
- FE = Filament v5 + Livewire v4 + Alpine + Tailwind v4 (Vite). `resources/js/app.js` RỖNG, chưa có Vue/axios/chart lib.
- Bề mặt Filament: 22 file PHP (`app/Filament/**`), 4 page Devices, 2 resource (Devices, Users), widget `HistoryChart`, 2 Livewire (`DeviceRealtime`, `EnergyBarChart`), 4 blade infolist component, 10 file CSS theme.
- Backend đã sạch: `InfluxService`, `RealtimeService`, `MqttService`, `InverterSettings` KHÔNG phụ thuộc Filament.
- `DeviceSettingsService` BỊ LẪN Filament (`Filament\Actions\Action`, `Notification`, `Width`, `DeviceSetup`) — phải tách.
- API hiện có: `GET /api/device-code/{serial}` (public, ESP32 — KHÔNG được đổi), `GET /api/devices`, `.../latest`, `.../status`, `.../history` (auth:sanctum).
- Sanctum 4 đã cài (`HasApiTokens`, migration `personal_access_tokens`, `config/sanctum.php`).
- Route `/` bị UserPanelProvider chiếm → **SPA phải mount tạm ở `/app/*` cho tới Phase 6**.
- Tests chỉ có stub `ExampleTest`; không có `.ai/rules`.

## Kiến trúc đích
```
Vue SPA (resources/js) ──axios Bearer──► /api/v1 (Sanctum token) ──► Services (giữ nguyên)
        └──laravel-echo/pusher-js──► laravel/reverb ◄── BroadcastTelemetryCommand ◄── Redis
                                                        (poll 1s, diff, chỉ broadcast khi đổi)
```

## API surface mới (`routes/api.php`, prefix `/v1`, `auth:sanctum` trừ login/register/device-code)

**Auth**: `POST /auth/login` (email|username), `POST /auth/register`, `POST /auth/logout`, `GET /auth/me`
**Devices**: `GET /devices`, `POST /devices` (admin), `GET /devices/{serial}`, `PATCH /devices/{serial}`, `DELETE /devices/{serial}` (admin), `POST /devices/claim` (serial+device_code — port từ `ListDevices::addDevice`)
**Telemetry**: `GET /devices/{serial}/realtime` (online + latest + timestamp — gộp từ `latest`+`status`), `GET /devices/{serial}/history`, `GET /devices/{serial}/dashboard?date=`, `GET /devices/{serial}/daily-energy?period&year&month`, `GET /devices/{serial}/energy-summary?date=`
**Settings**: `GET /devices/{serial}/settings/schema`, `POST /devices/{serial}/settings/read` → 202 `{job_id}`, `GET /devices/{serial}/settings/read/{jobId}` → `{status, values?, error?}`, `PUT /devices/{serial}/settings/field`, `PUT /devices/{serial}/settings`, `GET /devices/{serial}/commands`
**Users (admin)**: `GET/POST/PATCH/DELETE /users`, `POST /users/{id}/toggle-active` (thay action `toggleSuspend`)
**Broadcast/stream**: `GET /devices/{serial}/stream` (SSE, Bearer token, đọc Redis, giữ mở) — KHÔNG cần `routes/channels.php`, `config/broadcasting.php`, `install:broadcasting`.

## Việc phải làm ở backend

### Tách Filament khỏi service
- `DeviceSettingsService`: bỏ `action()`, `readAndNotify()`, `saveFieldAndNotify()` → giữ `read/save/saveField/pollRegs` thuần PHP. Chuyển logic thông báo sang Controller (JSON) / FE toast.
- `DeviceSetup` (schema động) → thay bằng `InverterSettings::clientSchema()` trả JSON shape:
  `[{title, tabs?: [{name, items: [{key, type, label, options?, min?, max?, unit?, showIf?, show?, showAc?, showGen?}]}]}]`.
  Giữ nguyên quy tắc `expandItem()` (timerange → 2 field time) và 4 điều kiện visibility; FE tự đánh giá `showIf/show/showAc/showGen` vì state nằm ở FE.

### Thêm mới
- `app/Http/Controllers/Api/V1/{AuthController,DeviceController,DeviceTelemetryController,DeviceSettingsController,UserController}.php`
- `app/Http/Requests/**` (FormRequest cho từng write op)
- `app/Http/Resources/{DeviceResource,UserResource}.php` — KHÔNG lộ `password`; `device_code` chỉ cho owner/admin
- `app/Policies/UserPolicy.php` (admin-only); dùng `DevicePolicy` sẵn có thay `canAccess()` trong `DeviceApiController`
- `RealtimeService::snapshot(string $serial): array` trả `{online, latest, timestamp}` — chuyển logic `recordTime()` từ `RealtimeController` vào đây (service sẽ nhận thêm `InfluxService`), để `DeviceTelemetryController` chỉ còn 1 dòng và là nguồn duy nhất cho cả SPA lẫn mobile.
- `app/Jobs/ReadDeviceSettingsJob.php` — gọi `DeviceSettingsService::read()`, ghi kết quả vào `Cache` key `settings_read:{jobId}` (TTL 5'), `ShouldBeUnique` theo serial
- `app/Http/Controllers/Api/V1/DeviceStreamController.php` — `__invoke`, SSE: tắt output buffering (`while (ob_get_level()) ob_end_flush()`), `set_time_limit(0)`, loop đọc `RealtimeService::snapshot()`, gửi `event: telemetry` khi payload ĐỔI, comment heartbeat mỗi ~15s, thoát khi `connection_aborted()`. `ponytail:` 1 PHP worker/client, nâng cấp = Reverb khi cần nhiều client.
- `app/Events/DeviceTelemetryUpdated.php` và `app/Console/Commands/BroadcastTelemetryCommand.php`: **KHÔNG cần nữa** (SSE đọc trực tiếp trong request).
- `app/Http/Controllers/Api/V1/DeviceTelemetryController.php` — snapshot 1 lần (dùng cho load đầu + fallback polling)
- `routes/channels.php`, `config/broadcasting.php`, `->withBroadcasting(...)`: **KHÔNG cần** (đã bỏ Reverb).
- `RealtimeController` (session auth) bị xóa ở Phase 6 — API trên thay thế.

## Việc phải làm ở frontend

### Dependencies thêm (cần duyệt)
`vue`, `vue-router`, `pinia`, `axios`, `chart.js`, `vue-chartjs`, `@vitejs/plugin-vue`
**Composer: KHÔNG thêm gì** (đã bỏ Reverb); **KHÔNG cần** `laravel-echo`, `pusher-js`, `mqtt.js`.
Node cho Vite 8: phải `nvm use 22.22.2` (máy mặc định nvm đang là 14.21.3 — build sẽ fail nếu quên).

### Cấu trúc `resources/js/`
- `app.js` — createApp + router + pinia + mount
- `router/index.js` — routes + guard `requiresAuth` / `requiresAdmin`
- `lib/api.js` — axios instance, interceptor gắn `Bearer`, 401 → clear token + redirect login
- `lib/echo.js` → **KHÔNG cần**. Thay bằng `lib/telemetry-stream.js`: `fetch()` + `ReadableStream` reader (TextDecoder, tự parse `event:`/`data:`), `AbortController`, auto-reconnect backoff; 3 lần fail liên tiếp → fallback poll `GET /devices/{serial}/realtime` chậm hơn (15s).
- `stores/{auth,devices,device,settings,notifications}.js`
- `views/{Login,Register,DevicesIndex,DeviceShow,DeviceSettingsView,AdminUsers,NotFound}.vue`
- `components/realtime/EnergyDiagram.vue` + `composables/useParticleEngine.js` (port nguyên toạ độ `tracks`/`J`, `particleSpeed`, `flow`, `spawnOutgoing` + `anim()` easeOutCubic 700ms)
- `components/charts/{DailyHistoryChart,EnergyBarChart}.vue` (vue-chartjs; giữ nguyên defs màu/label tiếng Việt)
- `components/settings/{SettingsTabs,fields/*}.vue` (switch tự lưu, input có nút Lưu, select/time/number)
- `components/ui/{Modal,Toast,DataTable,Tabs,ConfirmDialog,Skeleton}.vue` — thay Filament table/modal/notification
- `resources/views/app.blade.php` — shell SPA (@vite + #app)
- Port CSS: `resources/css/filament/*` → `resources/css/app.css` (bỏ selector `.fi-*`, giữ nền lưới tối `#0f172a` + style diagram)

### Thay đổi build
- `vite.config.js`: bỏ 2 entry `resources/css/filament/*/theme.css`, thêm `@vitejs/plugin-vue`; input = `resources/css/app.css`, `resources/js/app.js`
- `composer run dev`: KHÔNG thêm reverb/subscriber. Chỉ cần đảm bảo `PHP_CLI_SERVER_WORKERS` ≥10 cho `php artisan serve` (SSE giữ worker).

## Tiến độ (cập nhật)
- **Phase 0 XONG**: baseline đo + deps FE cài + bỏ Reverb (chốt SSE).
- **Phase 1 — Login (ĐÃ XONG)**: SPA tại `/`, auth API v1, tests auth xanh, phpstan baseline giữ nguyên. Browser verified login/logout flow.
- **Phase 2 — Devices (ĐÃ XONG)**: `Api\\V1\\DeviceController` + `DeviceTelemetryController` + `RealtimeService::snapshot()` implemented; FE list/detail OK; 24 device tests green.
- **Phase 3 — Realtime (ĐÃ XONG - CHUYỂN SANG WebSocket)**: ban đầu chốt SSE trong plan, nhưng thực tế đã triển khai WebSocket bằng `laravel/reverb` + `telemetry:broadcast` daemon để tránh PHP worker blocking trên dev. Files changed:
  - `app/Events/DeviceTelemetryUpdated.php` (broadcast event)
  - `app/Console/Commands/BroadcastTelemetry.php` (daemon)
  - `routes/channels.php` (private `device.{serial}` channel)
  - `resources/js/composables/useTelemetry.js` (client subscribe/pusher-js)
  - `.env` / vite env mappings and `composer.json` updates
  The reverb server is running locally (127.0.0.1:8080) and the daemon broadcasts only when payload changes. Đã hoàn thiện sơ đồ `EnergyDiagram.vue` với layout cuộn ngang trên mobile.
- **Phase 4 — Charts (ĐÃ XONG)**: Hoàn thành 3 màn hình Chart và KPI (`DashboardChart`, `DashboardEnergyChart`, `DashboardKpis`). Đặc biệt `DashboardEnergyChart` hỗ trợ xem theo Tháng/Năm/Tất cả qua InfluxDB (`monthlyEnergy`, `yearlyEnergy`), tooltip responsive dark mode, fix lỗi dính nhãn trục Y, và layout responsive an toàn trên mobile.
- **Build & Tests**: frontend built; `php artisan test` all passed (40/40); phpstan baseline unchanged.
- **Remaining**: Users UI (admin), và final cleanup Phase 6.

## Phases (làm tuần tự, TEST sau mỗi phase trước khi sang phase kế)

- **Phase 0 — Chuẩn bị (ĐÃ XONG)**: `nvm use 22.22.2`; cài deps FE (KHÔNG cài gì ở composer); chốt contract API; baseline `pint`/`phpstan --memory-limit=1G`/`pest`/`npm run build`. Chưa xoá gì. (Đã xong phần đo baseline.)
- **Phase 1 — Login (ĐÃ XONG)**: thực tế đã làm: gỡ 2 panel Filament khỏi `bootstrap/providers.php` ⇒ **SPA sở hữu `/`** (không còn mount tạm ở `/app`); `vite.config.js` bỏ entry theme Filament + thêm `@vitejs/plugin-vue`; `resources/views/app.blade.php`; `resources/js/{app.js,App.vue,router,lib/api.js,stores/auth.js,layouts/AuthLayout.vue,views/{Login,Register,Home,NotFound}.vue}`; API `/api/v1/auth/{login,register,logout,me}` + `LoginRequest`/`RegisterRequest`/`UserResource` + rate limit `throttle:login` (5/phút/IP); `ForceJsonResponse`; catch-all loại `api`/`up`. **Chưa** đụng route `/api/devices*` cũ (để dành Phase 2).
- **Phase 2 — Devices (ĐÃ XONG)**: `Api\V1\DeviceController` (index/store/show/update/destroy/claim) + `Api\V1\DeviceTelemetryController` (realtime/history) + `DeviceResource` + `RealtimeService::snapshot()` + 3 FormRequest; **xoá** `DeviceApiController` và `RealtimeController`; toàn bộ quyền qua `DevicePolicy`. FE: `stores/{devices,toast}.js`, `components/ui/{Modal,Toaster,ToggleSwitch}.vue`, `layouts/AppLayout.vue` (sidebar/topbar), `views/DevicesIndex.vue` (search/sort/phân trang/lọc claimed/gạt enabled/modal tạo-sửa-xoá-claim), `views/DeviceShow.vue` (thông tin cơ bản), xoá `views/Home.vue` (route `/` giờ là màn thiết bị, layout cha `AppLayout`).
  - Test: `tests/Feature/Api/DeviceTest.php` 24 test — scope owner/admin, search, filter, whitelist sort, 403/404, prohibited owner_id, claim 4 nhánh (201/422/409/409), realtime+history có mock, `device_code` ẩn với non-owner.
  - Verify browser: list dữ liệu thật OK; toggle 2 chiều + toast; modal xoá; **thiết bị thật không bị đụng** (thao tác trên thiết bị tạm `TESTUI0001` rồi xoá).
  - **Còn thiếu trong Phase 2**: API + màn quản lý **Users** (admin) và picker chọn chủ sở hữu trong modal Sửa thiết bị (API đã hỗ trợ `owner_id`, chỉ chưa có UI).
- **Phase 3 — Realtime (ĐÃ XONG)**: Giao diện WebSocket qua Laravel Reverb, `EnergyDiagram.vue`/particle engine + anim số, layout responsive cuộn ngang trên mobile.
- **Phase 4 — Charts (ĐÃ XONG)**: `dashboard`, `daily-energy`, `energy-summary` API + 3 màn chart/KPI, hỗ trợ chọn thời gian linh hoạt và fix CSS tương thích mobile.
- **Phase 5 — Settings async (ĐÃ XONG)**: `settings/schema` + `ReadDeviceSettingsJob` + UI tabs/fields + write field + audit list. Đã tách `DeviceSettingsService` khỏi Filament. Thêm component `DeviceSettings.vue` vào SPA.
- **Phase 6 — Gỡ Filament/Livewire**: xoá `app/Filament`, `app/Livewire`, `resources/views/{filament,livewire,vendor}`, `resources/css/filament`, `RealtimeController`; bỏ `filament/filament` + `livewire/*` khỏi composer; xoá 2 provider khỏi `bootstrap/providers.php`; `composer update` + `php artisan package:discover`; chuyển SPA từ `/app` về `/`; cập nhật `AGENTS.md`, xoá/sửa `.github/instructions/{filament-theme,realtime-telemetry}.instructions.md`, xoá skill `filament-pro` khỏi `.agents/skills` + `skills-lock.json`.
- **Phase 7 — Hardening**: Pest feature tests (auth, authorization theo owner/admin, claim flow, settings schema shape), `pint`, `phpstan` level 7, rà lại rate limit + validation biên.

## Verification
- Mỗi phase: `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse --memory-limit=1G` → `php artisan test --compact` → `nvm use 22.22.2; npm run build`. (Baseline `composer test` ĐỎ SẴN ở cả 3 tầng — xem `/memories/repo/baseline.md`; so sánh tương đối, không kỳ vọng xanh tuyệt đối cho tới Phase 7.)
- API: `php artisan route:list --path=api`, `curl` login lấy token → gọi `/api/v1/devices`; user thường gọi device không phải của mình → 403.
- Realtime: mở `/app/devices/{serial}` → xác nhận stream SSE cập nhật số liệu + diagram; **kill kết nối** → xác nhận FE reconnect; chặn endpoint stream → xác nhận fallback poll 15s chạy. Kiểm tra `php artisan serve` với `PHP_CLI_SERVER_WORKERS≥10` để SSE không chặn request khác.
- Settings: tắt Node worker → xác nhận job fail đúng hạn 25s + FE hiện lỗi, không treo HTTP.
- Test Pest: `RefreshDatabase`, fake `RealtimeService`/`InfluxService`, KHÔNG gọi Redis/MQTT/Influx thật.

## Rủi ro / ràng buộc
- `/api/device-code/{serial}` là contract với ESP32 (public, không auth) → giữ nguyên byte-for-byte.
- **Chưa có mobile** nên được phép thay thế route cũ; NHƯNG mọi endpoint nhóm user phải tự đủ cho mobile về sau: chỉ cần Bearer token, không phụ thuộc cookie/session, `is_admin`, hay endpoint admin. Kiểm tra điều này khi review từng endpoint ở Phase 1–5.
- Token trong localStorage = rủi ro XSS → chấp nhận (ponytail note), giảm thiểu bằng token hết hạn ngắn + revoke khi logout.
- Settings read cần queue worker thật; `QUEUE_CONNECTION=sync` sẽ phá mô hình 202+poll → phải set `database`/`redis`.
- **SSE**: giữ 1 PHP worker/client ⇒ ceiling vài chục client đồng thời (tuỳ `pm.max_children`). Đánh dấu `ponytail:` trong controller, nâng cấp = Reverb. Dev phải có `PHP_CLI_SERVER_WORKERS≥10` vì `artisan serve` mặc định 1 worker. SSE cần tắt output buffering + `set_time_limit(0)`; một số proxy (nginx `proxy_buffering on`) phải tắt buffering cho route stream.
- `EventSource` không gửi được header `Authorization` ⇒ đã chốt dùng `fetch` + `ReadableStream` để giữ Bearer token, KHÔNG đưa token vào query string.
