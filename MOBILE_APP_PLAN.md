# LuxMonitor Mobile App — Kế hoạch Triển khai & Task List

> **Phiên bản:** 1.4  
> **Ngày cập nhật:** 2026-10-13  
> **Đối tượng sử dụng:** End-User (Chủ sở hữu biến tần LuxPower, **KHÔNG bao gồm các chức năng Admin**).  
> **Mục tiêu:** Xây dựng ứng dụng di động hoàn chỉnh đa nền tảng (iOS & Android) giám sát thời gian thực, thống kê sản lượng, cài đặt biến tần từ xa.  
> **Cơ chế Cấu hình Tối ưu:** Bluetooth (BLE) **chỉ dùng duy nhất để cài đặt WiFi gia đình**. Ngay khi ESP32 có IP LAN, Bluetooth được **ngắt và tắt hoàn toàn**. Mọi thao tác sau đó (dò Dongle, nạp cấu hình Dongle, lấy mã xác minh Claim) được Mobile App thực hiện **trực tiếp qua HTTP API trên mạng LAN**.

---

## 1. Tổng quan Kiến trúc Hệ thống

```
┌────────────────────────────────────────────────────────────────────────┐
│                          ESP32 FIRMWARE                                │
│  - BLE: Chỉ kích hoạt lúc đầu để nhận SSID/Pass và trả IP LAN          │
│  - Tắt hẳn BLE (deinit) ngay sau khi kết nối WiFi Router thành công    │
│  - HTTP WebServer nội bộ: Nhận cấu hình Dongle & Cung cấp claim-info   │
│  - Modbus TCP Engine: Đọc biến tần qua Dongle port 8000                │
│  - MQTT Client: Gửi telemetry 10s, nhận lệnh cài đặt, nhận "set_code"  │
└──────────────┬──────────────────────────┬──────────────────────────────┘
               │ (1) BLE: Gửi WiFi        │ (3) HTTP API nội bộ qua IP LAN
               │     Trả về IP LAN        │     - POST /api/dongle/save_config
               │     TẮT BLE NGAY!        │     - GET /api/device/claim-info
               ▼                          ▼
┌────────────────────────────────────────────────────────┐
│                   MOBILE APP (USER)                    │
│  - Bước 1: Setup WiFi cho ESP32 qua BLE, lấy IP LAN    │
│  - Bước 2: Tự quét mạng LAN tìm Dongle (UDP/Port 8000) │
│  - Bước 3: Đẩy IP Dongle vào ESP32 qua HTTP API LAN    │
│  - Bước 4: Đọc mã xác minh (Server cấp) qua HTTP LAN   │
│  - Bước 5: Gọi Cloud API Claim quyền sở hữu            │
│  - Dashboard Realtime, Biểu đồ & Cài đặt thông số      │
│  - Nhận Push Notification từ FCM                       │
└──────┬──────────────────────────────────┬──────────────┘
       │                                  │
       │ (2) Tự quét LAN tìm Dongle       │ (4) REST API (Sanctum Token)
       │     (UDP broadcast / port 8000)  │     - POST /api/v1/devices/claim
       ▼                                  │     - GET /api/v1/devices/...
┌──────────────────────────────┐          ▼
│   DONGLE / INVERTER (LAN)    │  ┌──────────────────────────┐
└──────────────────────────────┘  │      CLOUD BACKEND       │
                                  │  - Mosquitto Broker      │
                                  │  - Node.js Worker        │
                                  │  - Redis & InfluxDB      │
                                  │  - Laravel 13 REST API   │
                                  │  - Alert Engine + FCM    │
                                  └───────────┬──────────────┘
                                              │ (5) Push Notification
                                              ▼
                                  ┌──────────────────────────┐
                                  │      FIREBASE (FCM)      │
                                  └──────────────────────────┘
```

---

## 2. Lựa chọn Công nghệ Mobile App

Khuyến nghị chính thức: **Flutter (Dart)**

| Tiêu chí | Flutter (Khuyến nghị) | React Native (Phương án thay thế) | Lý do chọn Flutter |
|---|---|---|---|
| **BLE (Bluetooth)** | `flutter_blue_plus` (Rất ổn định, native wrapper) | `react-native-ble-plx` (Cần cấu hình native build phức tạp) | Quét và trao đổi SSID/Pass WiFi với ESP32 cực nhanh (< 1 giây) |
| **Giao tiếp LAN** | `http` / `dio` (Hỗ trợ gọi IP LAN `http://192.168.x.x` không bị chặn) | `fetch` / `axios` | Gọi API nội bộ mạng gia đình cực kỳ mượt mà, hỗ trợ UDP socket quét mạng |
| **Giao diện & Hiệu năng** | 60–120 FPS, Canvas Skia/Impeller | Bridge/Fabric JavaScript | Biểu đồ luồng điện động (Solar Flow Animation) và đồ thị 5 đường chạy siêu mượt |
| **Biểu đồ (Charts)** | `fl_chart` (Mạnh mẽ, tùy biến trục kép cao cấp) | `react-native-gifted-charts` | Đã có sẵn mẫu biểu đồ 5 line và biểu đồ cột sản lượng tương thích UI Web hiện tại |
| **Thông báo đẩy** | `firebase_core`, `firebase_messaging` | `@react-native-firebase/messaging` | SDK chính chủ từ Google, dễ dàng setup background handlers |

---

## 3. Đặc tả 3 Trụ cột Chức năng

### Trụ cột 1: Chức năng Người dùng (Tương đương Web Portal)

1. **Xác thực & Tài khoản (Sanctum Auth)**:
   - Đăng nhập, Đăng ký tài khoản người dùng, Quên mật khẩu, Đổi mật khẩu.
   - Lưu trữ an toàn Bearer Token bằng `flutter_secure_storage`.
   - Tự động lấy thông tin người dùng (`GET /api/v1/auth/me`).

2. **Quản lý thiết bị (Device Management)**:
   - Danh sách các biến tần mà user sở hữu (`GET /api/v1/devices`).
   - Chuyển đổi trạm/biến tần đang theo dõi (Active Device Selector).
   - Đổi tên biến tần, xem thông tin phần cứng (Serial, Version, Trạng thái Online/Offline).
   - Hủy liên kết thiết bị (`POST /api/v1/devices/{serial}/unclaim`).
   - Thêm thiết bị: Qua Setup Wizard (BLE + LAN API) hoặc nhập tay thủ công.

3. **Dashboard Giám sát Thời gian thực (Realtime)**:
   - **Sơ đồ luồng điện động (Energy Flow Diagram)**:
     - Mô phỏng dòng năng lượng trực quan: Tấm pin (PV) ➔ Biến tần ➔ Tải tiêu thụ / Pin lưu trữ / Điện lưới.
     - Hiệu ứng hạt năng lượng chuyển động tỉ lệ theo công suất (W) thực tế.
   - **Thẻ chỉ số tức thời (KPI Cards)**:
     - Pin: Dung lượng SOC (%), Điện áp (V), Công suất Sạc/Xả (W), Nhiệt độ (°C).
     - PV: Tổng công suất PV (W), Chi tiết chuỗi PV1 / PV2 (V, A, W).
     - Lưới: Công suất Mua/Bán (W), Điện áp lưới (V), Tần số (Hz).
     - Tải: Công suất tiêu thụ gia đình (W), Tải cổng dự phòng EPS (W).
   - **Tần suất cập nhật**: Polling linh hoạt mỗi 3 giây (`GET /devices/{serial}/realtime`) hoặc kết nối trực tiếp WebSocket qua Laravel Reverb.

4. **Thống kê & Biểu đồ (Analytics & Charts)**:
   - **Biểu đồ thời gian thực 24h** (`GET /devices/{serial}/dashboard`):
     - Đồ thị 5 đường: SOC (0-100%), PV Power, Tải nhà, Sạc/Xả net (±), Điện lưới net (±).
     - Bộ chọn ngày với nút chuyển ngày trước/sau.
   - **Biểu đồ cột sản lượng năng lượng** (`GET /devices/{serial}/daily` & `/energy`):
     - Sản lượng PV phát, Điện lưới mua vào, Điện phát lên lưới, Điện sạc vào pin, Điện xả từ pin.
     - Xem theo Ngày, Tháng, Năm.

5. **Cài đặt thông số biến tần từ xa (Remote Inverter Settings)**:
   - Đọc sơ đồ cài đặt từ Cloud (`GET /devices/{serial}/settings/schema`).
   - **Quy trình Đọc**: Gửi yêu cầu đọc (`POST .../settings/read`) ➔ Đợi kết quả relay từ ESP32 ➔ Hiển thị lên các nhóm cài đặt:
     - *Cài đặt Pin/Sạc*: Giới hạn dòng sạc, điện áp thả nổi, điện áp sạc hấp thụ...
     - *Cài đặt Pin/Xả*: Ngưỡng xả sâu (Cut-off SOC/Voltage), dòng xả tối đa...
     - *Sạc AC*: Lịch sạc lưới theo khung giờ, công suất sạc AC...
     - *Chế độ Hybrid*: Bám tải (Zero-export), Ưu tiên hòa lưới, Cắt đỉnh phụ tải...
   - **Quy trình Ghi**: Sửa từng trường (`PUT .../settings/field`), xác nhận bảo mật, kiểm tra nhật ký lệnh (`GET .../commands`).

---

### Trụ cột 2: Quy trình Cấu hình Kết hợp: BLE (WiFi) ➔ HTTP LAN API ➔ Auto-Claim

Quy trình này chia làm 2 giai đoạn rõ ràng:
1. **Giai đoạn BLE siêu ngắn (chỉ 2–3 giây)**: Cài đặt WiFi và lấy IP LAN của ESP32. Sau đó **tắt vĩnh viễn Bluetooth**.
2. **Giai đoạn HTTP API qua mạng LAN**: Toàn bộ thao tác còn lại thực hiện qua HTTP REST nội bộ cực kỳ ổn định, nhẹ nhàng và không phụ thuộc Bluetooth.

```
[User App]                            [ESP32]                      [Dongle / LAN]                [Laravel Cloud]
    │                                    │                               │                              │
════╪════════════════════════════════════╪═══════════════════════════════╪══════════════════════════════╪═══════════════
    │ GIAI ĐOẠN 1: SETUP WIFI BẰNG BLE (TỐN 2-3 GIÂY)                    │                              │
════╪════════════════════════════════════╪═══════════════════════════════╪══════════════════════════════╪═══════════════
    ├─── 1. Quét & Kết nối BLE ─────────▶│                               │                              │
    │    (LuxMonitor-XXXX)               │                               │                              │
    ├─── 2. Gửi WiFi SSID/Mật khẩu ─────▶│                               │                              │
    │                                    ├── Kết nối WiFi Router ───────▶│                              │
    │◀── 3. Báo WiFi OK + IP: 192.168.1.X┤ (Đã có IP mạng LAN)           │                              │
    │                                    │                               │                              │
    │─── 4. Lệnh ngắt BLE hoàn tất ─────▶├── TẮT HẲN BLE (deinit)        │                              │
    │                                    │   (Giải phóng 100% RAM & RF)  │                              │
════╪════════════════════════════════════╪═══════════════════════════════╪══════════════════════════════╪═══════════════
    │ GIAI ĐOẠN 2: THAO TÁC QUA HTTP API LAN (KHÔNG DÙNG BLE NỮA)       │                              │
════╪════════════════════════════════════╪═══════════════════════════════╪══════════════════════════════╪═══════════════
    │                                    │                               │                              │
    │ [App tự quét mạng LAN gia đình]    │                               │                              │
    │ (Bắn UDP broadcast hoặc quét       │                               │                              │
    │  port 8000 song song < 1.5 giây)   │                               │                              │
    ├────────────────────────────────────┼─ Gửi gói probe port 8000 ────▶│                              │
    │◀───────────────────────────────────┼── Nhận IP, Dongle/Inv SN ─────┤                              │
    │                                    │                               │                              │
    ├─── 5. POST http://<esp_ip>/api/dongle/save_config ────────────────▶│                              │
    │    (Lưu IP Dongle & Serials)       ├── Lưu NVS ("dongle")          │                              │
    │                                    ├── Bắt đầu đọc Modbus TCP ────▶│                              │
    │                                    ├── Kết nối MQTT Server ──────────────────────────────────────▶│
    │                                    │   (Gửi telemetry lần đầu)     │                              │
    │                                    │◀── Server cấp "device_code" ─────────────────────────────────┤
    │                                    │    (Topic: cmd/set_code)      │                              │
    │                                    │    (ESP32 lưu Flash NVS)      │                              │
    │                                    │                               │                              │
    ├─── 6. GET http://<esp_ip>/api/device/claim-info ──────────────────▶│                              │
    │◀── 7. Trả { serial, device_code } ─┤                               │                              │
    │                                                                    │                              │
    ├─── 8. App tự động gọi Claim lên Server ────────────────────────────┼─────────────────────────────▶│
    │    POST /api/v1/devices/claim                                      │                              │
    │    { serial: iserial, device_code: code }                          │                              │
    │◀── 9. Xác nhận Gán Quyền Sở Hữu Thành Công! ───────────────────────┼──────────────────────────────┤
    │                                                                    │                              │
    ▼ Vào thẳng Dashboard trạm                                           ▼ Chạy bền bỉ Modbus + MQTT    ▼
```

#### Ưu điểm vượt trội của phương án này:
1. **Bluetooth chỉ chạy 3 giây**: Tránh hoàn toàn việc Bluetooth giữ kết nối lâu gây xung đột sóng 2.4GHz với WiFi. Sau bước 1, ESP32 thu hồi ngay ~25KB RAM và giải phóng 100% ăng-ten cho WiFi.
2. **Giao tiếp qua HTTP LAN siêu ổn định**: Gửi config Dongle và lấy `device_code` bằng HTTP GET/POST thông thường trên mạng nội bộ `http://192.168.x.x/api/...`. Đơn giản, tin cậy, không lo giới hạn kích thước gói tin (MTU) của Bluetooth.
3. **Mã xác minh (`device_code`) hoàn toàn tự động**: Server cấp qua MQTT ➔ ESP32 lưu Flash ➔ App gọi HTTP LAN lấy mã ➔ App gọi Cloud Claim. Người dùng không cần mở web ESP32 và không cần nhập tay 1 ký tự nào.

---

### Trụ cột 3: Thông báo đẩy FCM (Firebase Cloud Messaging)

#### 1. Các sự kiện kích hoạt cảnh báo:
- **Cảnh báo Pin yếu (Low Battery Alert - Hỗ trợ cả 2 chế độ SOC & VOL)**:
  - Do biến tần có thể không đọc được % pin qua giao tiếp BMS, hỗ trợ 2 chế độ:
    - **Theo SOC (%)**: Khi pin tụt xuống dưới ngưỡng và xả điện. Thông báo mỗi lần pin tụt xuống 5% (ví dụ 30%, 25%, 20%...). Nội dung: *"Pin lưu trữ còn lại [30]%. Hãy chú ý tải tiêu thụ."*
    - **Theo Điện áp (Voltage)**: Khi điện áp pin tụt xuống dưới ngưỡng và xả điện. Thông báo mỗi lần điện áp pin tụt xuống 0.2V (ví dụ 52.2V, 52.0V, 51.8V...). Nội dung: *"Điện áp pin đang ở mức thấp ([52.2] Vol). Hãy chú ý tải tiêu thụ."*
  - **Quy tắc Cooldown & Chống Spam**: Không báo lại 2 lần trong vòng 1 tiếng (3600s) ở cùng một mức VOL hoặc SOC. Khôi phục trạng thái (hysteresis) khi pin sạc hồi phục trên ngưỡng + 5% hoặc + 0.5V.
- **Cảnh báo Mất điện lưới & Có điện lưới trở lại**:
  - **Mất điện lưới (`GridLost`)**: Kích hoạt khi điện áp lưới tụt dưới 50V (mất nguồn AC). Tiêu đề: *Mất điện lưới*, nội dung: *Điện lưới khu vực của biến tần [Serial] đã bị mất. Hệ thống chuyển sang chạy độc lập / pin lưu trữ.*
  - **Có điện lưới (`GridRestored`)**: Kích hoạt khi điện áp lưới phục hồi đạt >= 180V. Tiêu đề: *Có điện lưới trở lại*, nội dung: *Điện lưới đã có trở lại ([220.5]V). Biến tần [Serial] đã hòa lưới bình thường.*
- **Cảnh báo lỗi biến tần (Inverter Fault / Warning)**:
  - Điều kiện: `state = 1` hoặc `fault_code != 0` hoặc `warning_code != 0`.
  - Hệ thống dịch mã lỗi sang Tiếng Việt (Tra cứu bảng register 60-63 trong `modbus_protocol.md`):
    - *Lỗi quá nhiệt biến tần (Inverter Over Temperature)*
    - *Lỗi quá áp chuỗi PV (PV Over Voltage)*
    - *Lỗi giao tiếp khối pin BMS (BMS Comm Fault)*
- **Mất kết nối thiết bị & Online trở lại (Device Offline / Recovery)**:
  - Worker phát hiện LWT MQTT (`status = offline`) hoặc không có telemetry trong 5 phút.
  - Nội dung: *Thiết bị LuxMonitor [Serial] đã mất kết nối Internet.*

#### 2. Cơ chế Backend (Laravel Notifications):
- Dùng **hệ thống Laravel Notification**: một thông báo duy nhất được gửi đồng thời qua 3 channel:
  - `database`: lưu vào bảng `notifications` (lịch sử, đã đọc/chưa đọc qua `read_at`).
  - `broadcast`: phát realtime qua WebSocket (Reverb) lên kênh private `App.Models.User.{id}`.
  - `fcm`: push về điện thoại (package `laravel-notification-channels/fcm`, FCM HTTP v1).
- Bảng cơ sở dữ liệu:
  - `notifications`: bảng chuẩn của Laravel (`php artisan make:notifications-table`), thay cho `alert_logs`.
  - `device_fcm_tokens`: danh sách token thiết bị của user (`user_id`, `fcm_token`, `device_name`, `platform`), trả về qua `routeNotificationForFcm()` trên `User`.
  - `device_alert_settings`: cấu hình ngưỡng cảnh báo từng thiết bị (`battery_alert_mode` soc/voltage, `low_battery_soc`, `low_battery_voltage`, `notify_fault`, `notify_grid`, `notify_offline`, `notify_recovery`).
- Người nhận: mọi user sở hữu thiết bị (`$device->users`).
- Cơ chế chống Spam (Anti-spam / Cooldown):
  - Cache/Redis lock và lưu vết step: pin không báo lại cùng mức trong 1 giờ; lỗi và mất kết nối có cooldown 15 phút.


---

### Trụ cột 4: Quản lý Vòng đời Firmware — Web Flasher (Lần đầu / USB) & Cloud OTA Update (Từ xa)

Hệ thống cung cấp 2 giải pháp nạp firmware đồng bộ khép kín:
1. **Web Flasher (Nạp lần đầu qua USB / Cứu hộ)**: Dành cho mạch mới mua xuất xưởng hoặc khi mạch bị lỗi mất cấu hình mạng.
2. **Cloud OTA Update (Nâng cấp từ xa qua Internet)**: Dành cho toàn bộ quá trình sử dụng thường ngày, thực hiện chỉ với 1 chạm từ Mobile App hoặc Web Portal mà không cần tháo mạch ra khỏi tủ điện.

#### 1. Đặc tả Trang Web Flasher (Web-based USB Flasher):
- **Công nghệ cốt lõi**: `esptool-js` (thư viện chính thức từ Espressif Systems) chạy trên Web Serial API.
- **Môi trường hỗ trợ**:
  - **Máy tính (PC/Laptop)**: Các trình duyệt nhân Chromium (Google Chrome, Microsoft Edge, Brave, Cốc Cốc) qua cáp USB.
  - **Điện thoại di động (Mobile)**: **Chỉ hỗ trợ trên hệ điều hành Android** (thông qua trình duyệt Google Chrome kết nối cáp OTG Type-C sang ESP32). iOS không hỗ trợ do chính sách bảo mật khóa cổng Serial của Apple.
- **Cơ chế Nhận diện Chip Tự động**:
  - Khi người dùng bấm "Kết nối", `esptool-js` đồng bộ bootloader và đọc thanh ghi phần cứng:
    - Nếu là `ESP32` (D0WD/V3...): Tự động chọn gói firmware `devkit` (`esp32dev`).
    - Nếu là `ESP32-C3`: Tự động chọn gói firmware `c3` (`esp32-c3-devkitm-1`).
    - Nếu cắm chip không hợp lệ: Hiển thị cảnh báo từ chối nạp.
- **2 Phương thức Nạp Firmware**:
  - **Phương thức 1: Cài mới hoàn toàn (Full Install / Khôi phục xuất xưởng)**:
    - Xóa sạch toàn bộ bộ nhớ flash (`eraseFlash = true`).
    - Nạp đầy đủ các phân vùng: `bootloader.bin` (offset 0x0/0x1000), `partitions.bin` (0x8000), `boot_app0.bin` (0xe000), `firmware.bin` (0x10000) và `littlefs.bin` (nếu có).
    - Dành cho: Bo mạch mới mua trắng tinh, hoặc nạp lại khi mạch bị lỗi phân vùng.
  - **Phương thức 2: Cập nhật FW giữ nguyên cấu hình (Update / Keep Settings)**:
    - **Không xóa flash**.
    - **Chỉ nạp đè `firmware.bin`** vào phân vùng ứng dụng (`0x10000`).
    - Dành cho: Nâng cấp tính năng mới mà **giữ nguyên 100% cấu hình WiFi, IP Dongle và mã `device_code`** trong bộ nhớ NVS.
- **Hỗ trợ Driver & Xử lý sự cố Thông minh (Smart Fallback)**:
  - Nếu danh sách cổng COM trống hoặc người dùng bấm hủy: Web tự động hiển thị popup gợi ý kèm nút tải **Driver 1-Click** lưu trực tiếp trên Server (Bộ cài tự động `CP210x Driver` của Silicon Labs và `CH341SER.EXE` của WCH).
  - Hướng dẫn thao tác nút bấm BOOT: Hướng dẫn giữ nút BOOT trong 3 giây khi cắm cáp đối với các bo mạch DevKit giá rẻ không có mạch tự động reset.

#### 2. Đặc tả Cập nhật Firmware từ xa (Cloud OTA Update):
- **Cơ chế phân vùng kép an toàn (`Dual-Partition OTA`)**:
  - Bảng phân vùng `default_ota_4MB.csv` chia bộ nhớ 4MB thành 2 phân vùng ứng dụng chạy luân phiên `ota_0` và `ota_1` (~1.4MB mỗi phân vùng).
  - Mạch luôn nạp vào phân vùng dự phòng. Nếu quá trình tải bị mất điện, mất WiFi hoặc file hỏng mã MD5, bootloader tự động giữ nguyên phiên bản cũ và khởi động lại an toàn, **không bao giờ bị brick/treo mạch**.
- **Luồng kích hoạt từ xa (Remote Trigger)**:
  1. Người dùng bấm nút **[Cập nhật Firmware]** trên Mobile App hoặc Web Portal.
  2. Server gửi lệnh MQTT: `luxmonitor/<serial>/cmd/ota` kèm URL firmware (`https://.../firmware/{board}/vX.X.bin`) và mã băm MD5.
  3. Module `HTTPUpdate` trên ESP32 tự động tải về qua HTTPS, ghi flash và phản hồi tiến trình % ngược lại MQTT để Web/Mobile hiển thị thanh loading.
  4. Sau khi hoàn tất và kiểm tra toàn vẹn thành công, ESP32 tự reboot vào bản mới và giữ nguyên toàn bộ cấu hình mạng/NVS.

---

## 4. Kế hoạch Triển khai Chi tiết (Task Lists)

### Giai đoạn 1: Mở rộng Backend Cloud (Laravel & Worker)

- [x] **Task 1.1**: Hạ tầng Laravel Notification (database + broadcast + FCM)
  - Thêm trait `Notifiable` cho `User` (nếu chưa có), chạy `php artisan make:notifications-table` ➔ bảng `notifications`.
  - Cài `laravel-notification-channels/fcm`, cấu hình service account Firebase (`storage/firebase_credentials.json`).
  - Migration & Model `device_fcm_tokens` (id, user_id, fcm_token unique, device_name, platform, timestamps); quan hệ `User hasMany DeviceFcmToken`, `User::routeNotificationForFcm()` trả về danh sách token.
  - Migration & Model `device_alert_settings` (id, device_id, battery_alert_mode, low_battery_soc, low_battery_voltage, notify_fault, notify_grid, notify_offline, notify_recovery); quan hệ `Device hasOne`.
  - Khai báo kênh private `App.Models.User.{id}` trong `routes/channels.php` cho broadcast thông báo.
  - PHP Enum `AlertType` (LowBattery, Fault, Offline, Recovery, GridLost, GridRestored) và `AlertSeverity` (Info, Warning, Critical).
- [x] **Task 1.2**: Xây dựng API quản lý FCM Token trên Laravel
  - `POST /api/v1/fcm/tokens`: Đăng ký token mới của app (`updateOrCreate` theo `fcm_token`).
  - `DELETE /api/v1/fcm/tokens`: Xóa token khi đăng xuất.
- [x] **Task 1.3**: Xây dựng các Notification class (`app/Notifications`)
  - Class `DeviceAlertNotification` nhận `AlertType` và tham số.
  - `via()` trả về `['database', 'broadcast', FcmChannel::class]`.
  - `toDatabase()` / `toBroadcast()`: cùng payload `{ device_serial, type, severity, title, body }`.
  - `toFcm()`: kèm `data: { device_serial, notification_id, type }` để deep-link trong app.
- [x] **Task 1.4**: Xây dựng Công cụ phát hiện cảnh báo (`AlertDetectionService`)
  - Hook kiểm tra realtime từ Redis: pin yếu (theo SOC tụt 5% hoặc VOL tụt 0.2V, cooldown 1h cùng mức), biến tần lỗi (`state = 1` hoặc `fault_code`/`warning_code`), mất điện lưới (< 50V) / có điện lưới (>= 180V), mất kết nối (`online = 0`) / online trở lại.
  - Áp dụng Redis cache cooldown và state machine theo dõi step pin và lưới điện.
  - Command nền `php artisan telemetry:alerts` tự động quét thiết bị định kỳ (đã tích hợp vào `composer dev`).
  - Khi phát hiện: `$owner->notify(new DeviceAlertNotification(...))` ➔ một lần gửi đi cả 3 channel.
- [x] **Task 1.5**: Bổ sung API thông báo cho Mobile App (dựa trên `$user->notifications()`)
  - `GET /api/v1/notifications`: Danh sách thông báo (phân trang) kèm số lượng chưa đọc.
  - `PATCH /api/v1/notifications/{id}/read` và `POST /api/v1/notifications/mark-all-read`: Dùng `markAsRead()`.
  - `GET/PUT /api/v1/devices/{serial}/alert-settings`: Đọc/Lưu cài đặt ngưỡng cảnh báo.

---

### Giai đoạn 2: Nâng cấp Firmware ESP32 (BLE Siêu Tinh Gọn & HTTP LAN API)

- [ ] **Task 2.1**: Tích hợp `NimBLE-Arduino` phục vụ WiFi Provisioning
  - Khởi tạo GATT Server cực kỳ tinh gọn, tên Bluetooth: `LuxMonitor-XXXX`.
  - Chỉ duy nhất 1 Characteristic `WIFI_PROV_CHAR` (Write/Notify):
    - App gửi: `{"ssid":"...","pass":"..."}`.
    - ESP32 kết nối WiFi và phản hồi: `{"status":"connected","ip":"192.168.1.55"}`.
  - Ngay sau khi phản hồi thành công hoặc sau 60 giây: **Gọi `NimBLEDevice::deinit(true)` tắt vĩnh viễn BLE**.
- [ ] **Task 2.2**: Cung cấp HTTP API nội bộ mạng LAN trên ESP32 WebServer
  - Tận dụng `POST /api/dongle/save_config?ip=...&dserial=...&iserial=...` (đã có sẵn trong `dongle_setup.cpp`).
  - Bổ sung endpoint mới: `GET /api/device/claim-info`:
    - Trả về JSON: `{"serial":"4313800597","device_code":"847192","ready":true}`.
    - Nếu chưa nhận được mã từ Server (MQTT đang kết nối), trả về `{"ready":false}` để App polling.
- [ ] **Task 2.3**: Hoàn thiện tiếp nhận mã xác minh từ Server qua MQTT
  - Subscribe topic `luxmonitor/<serial>/cmd/set_code` (retained từ Server).
  - Khi nhận mã, lưu vào NVS (`Preferences` namespace `"device"`) và gửi ACK lên `cmd/result`.
- [ ] **Task 2.4**: Nút bấm BOOT (GPIO9): Nhấn giữ 3 giây để bật lại BLE trong vòng 3 phút khi người dùng muốn đổi WiFi.
- [ ] **Task 2.5**: Cấu hình Partition Table Dual OTA & Module Cloud OTA trên Firmware ESP32
  - Cấu hình `board_build.partitions = default_ota_4MB.csv` trong `platformio.ini` cho cả `devkit` và `c3` (chia 2 phân vùng `ota_0` và `ota_1` ~1.4MB).
  - Tích hợp `#include <HTTPUpdate.h>` xử lý cập nhật OTA an toàn qua HTTPS.
  - Subscribe MQTT topic `luxmonitor/<serial>/cmd/ota` nhận payload `{ url, md5, version }`.
  - Gửi bản tin tiến trình % nạp về topic `luxmonitor/<serial>/cmd/result` và tự khởi động lại vào phân vùng mới sau khi nạp thành công.

---

### Giai đoạn 2B: Trang Web Flasher & Hạ tầng Cloud OTA Firmware

- [x] **Task 2B.1**: Quản lý Kho Firmware & Manifest trên Cloud Server (`LuxMoniterServer`)
  - Thư mục lưu trữ binaries: `public/firmware/{board_type}/` (`devkit` và `c3`).
  - File `manifest.json`: Chứa phiên bản mới nhất, changelog, md5 hash, và offset các phân vùng (`bootloader`, `partitions`, `boot_app0`, `firmware`, `littlefs`).
  - API Backend:
    - `GET /api/v1/firmware/manifest`: Trả về thông tin phiên bản và danh sách file binary cho Web Flasher.
    - `POST /api/v1/devices/{serial}/ota`: Gửi lệnh MQTT `cmd/ota` kích hoạt nạp từ xa cho thiết bị cụ thể.
- [x] **Task 2B.2**: Xây dựng Trang Web Flasher (`/flash`) trên Vue 3 SPA
  - Cài đặt thư viện `esptool-js` (Espressif Systems).
  - Route công khai: `/flash` trong Vue Router (truy cập trực tiếp không bắt buộc đăng nhập, có thể dùng từ máy tính hoặc điện thoại Android cáp OTG).
  - Giao diện thân thiện, chuẩn theme LuxMonitor:
    - Nút lớn "Kết nối Thiết bị & Nạp Firmware".
    - Thanh đo tiến trình % kèm thông số tốc độ nạp (Baudrate 460800 / 921600).
    - Khung Log Terminal thu gọn hiển thị thông tin MAC, Flash Size, loại chip.
- [x] **Task 2B.3**: Tính năng Tự động Nhận diện Chip & 2 Chế độ Nạp
  - Đọc thanh ghi phần cứng khi kết nối cổng COM: Tự động phát hiện `ESP32` hay `ESP32-C3` để tải đúng bộ binary tương ứng.
  - Radio/Toggle chọn 2 chế độ:
    - **Cài mới hoàn toàn (Full Install)**: Xóa toàn bộ Flash ➔ Nạp đủ 4-5 file phân vùng (cho mạch mới).
    - **Cập nhật FW giữ cấu hình (Keep Settings)**: Chỉ nạp đè file `firmware.bin` vào offset `0x10000` (giữ nguyên WiFi, IP Dongle, device_code trong NVS).
- [x] **Task 2B.4**: Tích hợp Bộ tải Driver USB 1-Click & Hướng dẫn Trực quan
  - Tự động bắt sự kiện danh sách COM trống / bấm hủy ➔ Bật popup gợi ý Driver.
  - Nút tải trực tiếp từ server: `CP210x Windows Driver` (Silicon Labs) và `CH341SER.EXE` (WCH).
  - Hướng dẫn bằng ảnh minh họa: Hướng dẫn cắm cáp OTG Type-C trên Android, mẹo nhấn giữ nút BOOT trong 3 giây khi nạp mạch DevKit.
- [x] **Task 2B.5**: Tích hợp Giao diện Kiểm tra & Kích hoạt OTA trên Mobile App (Flutter) và Web Portal
  - Thẻ thông tin phiên bản firmware trong trang Cài đặt thiết bị / Device Info.
  - Tự động so sánh phiên bản đang chạy với bản mới nhất trên Server.
  - Huy hiệu "Có bản cập nhật mới" kèm nút bấm [Nâng cấp OTA ngay] ➔ Hiển thị dialog xác nhận và theo dõi tiến trình nạp trực tiếp.

---

### Giai đoạn 3: Khởi tạo Dự án Mobile App & Core Kiến trúc

- [x] **Task 3.1**: Khởi tạo dự án Flutter
  - Package identifier: `com.luxmonitor.app` (khởi tạo tại `d:\Php\CODE\LuxMonitor\MobileFluter` trên Flutter 3.47.6 / Dart 3.13.5).
  - Cấu trúc thư mục module hóa hoàn chỉnh:
    - `core/` (constants, theme, network, storage, di, bloc, widgets)
    - `modules/auth/`
    - `modules/devices/`
    - `modules/dashboard/`
    - `modules/statistics/`
    - `modules/settings/`
    - `modules/provisioning/`
    - `modules/notifications/`
- [x] **Task 3.2**: Thiết lập Quản lý trạng thái (State Management) & DI
  - Sử dụng `flutter_bloc` (^9.1.1) + `equatable` (^3.0.0) cho luồng dữ liệu reactive và an toàn.
  - Cấu hình `get_it` (^9.3.0) trong `injection_container.dart` làm Service Locator.
- [x] **Task 3.3**: Xây dựng Tầng Mạng & Lưu trữ
  - `Dio` client cấu hình 2 Client chuyên biệt:
    - `CloudApiClient`: Gọi REST API Cloud, tự động gắn Bearer Token Sanctum, xử lý lỗi typed `ApiException`, lắng nghe sự kiện 401 Unauthorized để logout.
    - `LanApiClient`: Gọi trực tiếp tới IP LAN của ESP32 (`http://192.168.x.x`) với timeout ngắn 3s phục vụ WiFi Provisioning và lấy `claim-info`.
  - `TokenStorage`: Kết hợp `FlutterSecureStorage` (lưu token an toàn) và `SharedPreferences` (lưu tùy chọn server URL, serial đang xem, theme).
- [x] **Task 3.4**: Xây dựng UI Design System
  - Hỗ trợ Dark Mode / Light Mode hoàn chỉnh theo tông màu Solar (`#FFA000` PV, `#10B981` Pin, `#3B82F6` Lưới, `#8B5CF6` Tải).
  - Bộ Component dùng chung: `AppButton` (loading state), `AppTextField` (mật khẩu ẩn/hiện), `MetricCard` (KPI card năng lượng), `AppConfirmDialog` (xác nhận nguy hiểm/an toàn), `EmptyStateView`, `LoadingOverlay`.

---

### Giai đoạn 4: Màn hình Xác thực & Quản lý Thiết bị trên App

- [x] **Task 4.1**: Module Đăng nhập & Đăng ký
  - Màn hình Đăng nhập (`POST /api/v1/auth/login`) với email/username, nhớ mật khẩu.
  - Màn hình Đăng ký tài khoản người dùng (`POST /api/v1/auth/register`) với xác thực form và tự động đăng nhập.
  - Màn hình Quên mật khẩu (`POST /api/v1/auth/forgot-password`).
  - Bloc quản lý: `AuthBloc` (LoginRequested, RegisterRequested, ForgotPasswordRequested).
- [x] **Task 4.2**: Màn hình Thông tin Tài khoản (User Profile & Cài đặt giao diện)
  - Nút Avatar người dùng trên AppBar điều hướng vào trang Thông tin tài khoản (`ProfileScreen`).
  - Cập nhật thông tin cá nhân: Đổi tên hiển thị (`PUT /api/v1/user/profile`).
  - Đổi mật khẩu an toàn (`PUT /api/v1/user/password`) với mật khẩu hiện tại và mật khẩu mới.
  - Quản lý giao diện ứng dụng: Chuyển đổi linh hoạt Tối (Dark), Sáng (Light) và Tự động theo hệ thống (System).
  - Tối ưu UI/UX: Xử lý cuộn khi bật bàn phím (chống lỗi sọc vàng đen `RenderFlex overflow`), đệm an toàn `SafeArea` đáy màn hình chống bị thanh điều hướng Android đè lên.
- [x] **Task 4.3**: Màn hình Danh sách Thiết bị (Device Selector)
  - Hiển thị danh sách các trạm của user kèm trạng thái, tên trạm, serial.
  - Thanh chọn nhanh trạm biến tần `DeviceSelectorBar` ở đầu ứng dụng.
  - Chức năng sửa tên biến tần (`PATCH /api/v1/devices/{serial}`), hủy liên kết biến tần (`POST /api/v1/devices/{serial}/unclaim` kèm modal cảnh báo nguy hiểm).
  - Bloc quản lý: `DeviceBloc` (LoadDevices, SelectDevice, RenameDeviceRequested, UnclaimDeviceRequested).
  - Căn lề tiêu đề đồng bộ, bọc vùng đệm an toàn với thanh điều hướng cử chỉ của hệ điều hành.
- [x] **Task 4.4**: Màn hình Thêm thiết bị thủ công
  - Form nhập Serial biến tần (10 số) + Mã xác minh (6 số PIN) qua `ClaimDeviceScreen`.
  - Kết nối API `POST /api/v1/devices/claim`, thông báo kết quả và tự động chọn trạm mới thêm.

---

### Giai đoạn 5: Dashboard Realtime & Biểu đồ Thống kê

- [x] **Task 5.1**: Màn hình Dashboard Realtime (Đa hướng Dọc / Ngang)
  - Polling định kỳ mỗi 3s dữ liệu `/api/v1/devices/{serial}/realtime`.
  - Hiển thị trạng thái biến tần: Normal, Standby, Fault, EPS/Backup.
  - Bố cục responsive:
    - Chế độ dọc: Sơ đồ luồng điện, KPI Cards, Biểu đồ công suất 24h hôm nay xếp theo chiều dọc.
    - Chế độ xoay ngang: Chia tỉ lệ vàng 7/5 giữa Sơ đồ luồng điện và Biểu đồ thời gian thực.
    - Cố định chiều cao và tự động co giãn trong khung hình ở chế độ ngang (không tràn viền, không cần cuộn trang).
- [x] **Task 5.2**: Xây dựng Sơ đồ Luồng Năng lượng Động (Solar Flow Widget)
  - Custom Painter vẽ sơ đồ các nhánh: Tấm pin PV, Biến tần trung tâm, Pin lưu trữ, Điện lưới, Tải tiêu thụ gia đình.
  - **Port chính xác 100% thuật toán hạt điện tích rời rạc từ Web (`useParticleEngine.js`)**:
    - Dây nguồn (`ac`, `grid` khi kéo lưới, `inv` khi biến tần phát, `pv`, `batt`, `eps`): Duy trì đúng 1 hạt lặp vô hạn (`loop: true`), vận tốc tính chuẩn $1.2 \times \sqrt{P} \in [12, 90]$.
    - Cơ chế ngã 4 (`endsAtJunction`): Khi hạt nguồn chạm ngã 4 $J(266, 340)$, hạt nguồn lặp lại chu kỳ từ đầu dây và kích hoạt `_spawnOutgoing(latest)` sinh hạt con đi vào các nhánh nhận (`load`, `grid` khi đẩy lưới, `inv` khi nạp từ lưới/AC).
    - Hạt nhánh nhận: Sinh từ ngã 4 với $t = 0, \text{loop} = false$, di chuyển ra thiết bị nhận và tự hủy khi $t \ge 1$.
    - Chống spam hạt: Nhánh nhận có công suất $< 100\text{W}$ nếu đã có hạt thì không sinh thêm hạt mới.
    - Hiệu ứng mờ 2 đầu dây mềm mại $fadeT = \min\left(0.5, \frac{\text{speed} \times 0.2\text{s}}{\text{length}}\right)$ khớp hoàn toàn bản Web.
- [x] **Task 5.3**: Thẻ chỉ số chi tiết (KPI Cards)
  - Chi tiết công suất và điện áp 2 chuỗi PV1, PV2.
  - Chi tiết thông số Pin (SOC %, Điện áp, Dòng sạc/xả, Nhiệt độ).
  - Chi tiết điện lưới (Công suất hòa lưới/mua lưới, Điện áp, Tần số).
  - Tải tiêu thụ gia đình và tải cổng dự phòng EPS.
- [x] **Task 5.4**: Màn hình Biểu đồ 24 Giờ & Biểu đồ Hôm nay (Timeline Charts)
  - Vẽ đồ thị 5 đường bằng `fl_chart`: SOC (trục phụ 0-100%), PV, Tải, Sạc/Xả net, Lưới net.
  - Tối ưu không gian hiển thị (Compact Chart): Giảm tối đa padding viền card và độ rộng trục Y để đường biểu đồ to, rõ ràng và sắc nét.
  - Tăng độ dày mật độ lấy mẫu lên 5 phút/lần (`window=5m`) đồng bộ cả Backend Laravel InfluxDB, Web và Mobile.
  - Tooltip biểu đồ dây tinh gọn: chỉ hiển thị giờ:phút `HH:mm` in đậm, loại bỏ tiền tố và ngày tháng rườm rà.
  - Tooltip biểu đồ tròn KPI: Hiển thị phân bổ chi tiết sản lượng (Cấp tải, Sạc pin, Đẩy lưới) và nguồn cấp tiêu thụ (Trực tiếp, Xả pin, Kéo lưới) kèm sản lượng (kWh) và tỷ lệ phần trăm (%).
  - Thanh chọn ngày (Hôm nay, Hôm qua, DatePicker linh hoạt).
- [x] **Task 5.5**: Màn hình Thống kê Sản lượng (Energy Analytics)
  - Biểu đồ cột phân chia màu theo: PV tạo ra, Lưới mua vào, Bán lên lưới, Năng lượng sạc/xả.
  - Bộ lọc xem theo: Ngày / Tháng / Năm.
  - Thẻ tóm tắt chỉ số năng lượng tổng quan gọn gàng, trực quan.

---

### Giai đoạn 6: Màn hình Cài đặt Thông số Biến tần (Remote Settings)

- [x] **Task 6.1**: Tải Schema cài đặt động từ Cloud & Bố cục Sidebar dọc (Vertical Tab Navigation)
  - Gọi `/api/v1/devices/{serial}/settings/schema`.
  - **Bố cục chuẩn Web**: Thiết kế Sidebar dọc cố định bên trái (86px) hiển thị icon và nhãn các nhóm cấu hình (Sạc AC, Hybrid, Pin lưu trữ, AC Coupling, Ắc quy chì-axit...).
  - Vùng nội dung bên phải hiển thị danh sách các card cài đặt với màu nền chuẩn `#262626` (Dark) / Card Surface, nút bấm compact hài hòa (36px).
- [x] **Task 6.2**: Quy trình Đọc thông số thực tế từ Biến tần
  - Gửi lệnh đọc `POST /devices/{serial}/settings/read`.
  - Polling trạng thái `GET /devices/{serial}/settings/read/{jobId}` cho đến khi có dữ liệu (kèm popup loading spinner và timeout 25s an toàn).
  - Điền giá trị đọc được vào các ô nhập liệu / dropdown tương ứng.
- [x] **Task 6.3**: Lưu thông số biến tần
  - Kiểm tra tính hợp lệ của giá trị (min/max theo schema).
  - Dialog xác nhận cảnh báo trước khi thay đổi thông số phần cứng.
  - Gửi lệnh `PUT /devices/{serial}/settings/field` và hiển thị kết quả toast/snackbar xác nhận.
- [x] **Task 6.4**: Màn hình Nhật ký Lệnh (Audit Log)
  - Nút "Nhật ký" trên thanh công cụ điều hướng đến màn hình Lịch sử lệnh (`CommandHistoryScreen`).
  - Hiển thị danh sách các lần điều khiển cài đặt thiết bị (`GET /devices/{serial}/commands`) kèm trạng thái Thành công / Thất bại / Đang xử lý.

---

### Giai đoạn 7: Luồng Cấu hình Setup Wizard (BLE ➔ LAN API ➔ Auto-Claim)

- [ ] **Task 7.1**: Bước 1 — Cài đặt WiFi bằng BLE
  - Quét tìm Bluetooth `LuxMonitor-XXXX` ➔ Kết nối.
  - Nhập SSID + Mật khẩu WiFi nhà ➔ Gửi qua BLE.
  - Nhận phản hồi IP LAN được cấp (ví dụ: `192.168.1.55`).
  - Gửi tín hiệu hoàn tất BLE và ngắt kết nối Bluetooth ngay lập tức.
- [ ] **Task 7.2**: Bước 2 — Điện thoại tự dò tìm Dongle qua LAN
  - Mobile App gửi gói UDP broadcast (port 48899 / 8000) hoặc quét socket TCP song song port 8000 trên dải LAN gia đình.
  - Bắt gói phản hồi: Lấy Dongle IP, Dongle Serial và Inverter Serial.
- [ ] **Task 7.3**: Bước 3 — Đẩy cấu hình Dongle vào ESP32 qua HTTP LAN
  - App gọi: `POST http://192.168.1.55/api/dongle/save_config?ip=...&dserial=...&iserial=...`.
  - ESP32 phản hồi "OK", bắt đầu kết nối Modbus TCP và MQTT Server.
- [ ] **Task 7.4**: Bước 4 — Lấy mã xác minh từ ESP32 qua HTTP LAN
  - App polling: `GET http://192.168.1.55/api/device/claim-info` (mỗi 1s, tối đa 10s).
  - Khi ESP32 nhận được mã từ Server đẩy xuống qua MQTT, trả về: `{"serial":"...","device_code":"847192"}`.
- [ ] **Task 7.5**: Bước 5 — Tự động gọi Cloud Claim
  - App tự động gọi: `POST https://server/api/v1/devices/claim` với Serial và `device_code`.
  - Cloud xác nhận liên kết thành công ➔ Chuyển thẳng người dùng vào Dashboard trạm mới!

---

### Giai đoạn 8: Tích hợp FCM Push Notification & Trung tâm Cảnh báo

- [x] **Task 8.1**: Cấu hình Firebase & Push Notification Services
  - Tạo Model `AppNotification`, `NotificationListResponse`, `DeviceAlertSetting`.
  - Chuẩn bị pipeline đăng ký Token thiết bị và phân quyền thông báo.
- [x] **Task 8.2**: Đăng ký Token với Backend (`POST /api/v1/fcm/tokens`)
  - Viết `NotificationRepository` hỗ trợ đồng bộ Token thiết bị lên máy chủ Cloud.
- [x] **Task 8.3**: Xử lý BLoC & Cơ chế Fallback Thông minh
  - `NotificationBloc` hỗ trợ Polling Unread Count, đánh dấu đã đọc (Optimistic UI), phân trang cuộn mượt mà.
  - Tự động fallback an toàn (danh sách rỗng, unread = 0) khi API server đang bảo trì hoặc chưa hoàn tất migrate.
- [x] **Task 8.4**: Màn hình Trung tâm Thông báo (Notification Center)
  - Quả chuông trên AppBar với Badge màu đỏ đếm số lượng thông báo chưa đọc.
  - Giao diện bộ lọc nhanh (Tất cả / Chưa đọc), icon severity badge (🔴 Fault, 🟡 Warning, 🔵 Info, ⚪ System).
  - Thao tác kéo để làm mới (Pull-to-refresh), phân trang cuộn, nút "Đọc tất cả".
- [x] **Task 8.5**: Màn hình Cài đặt Ngưỡng Cảnh báo Thiết bị
  - Nút Cài đặt (Bánh răng) từ Trung tâm thông báo và nút "Cảnh báo" trên header Cài đặt biến tần.
  - Bộ chọn biến tần linh hoạt (`DeviceBloc`), thanh trượt chọn ngưỡng pin yếu theo % SOC hoặc Điện áp (V).
  - Nhóm các switch bật/tắt sự kiện: Lỗi biến tần (Fault), Mất/Có điện lưới, Mất kết nối Internet (Offline), Khôi phục kết nối (Recovery).
  - Nút Lưu cấu hình cảnh báo có SnackBar phản hồi người dùng tức thì.

---

### Giai đoạn 9: Kiểm thử, Tối ưu & Đóng gói Release

- [ ] **Task 9.1**: Kiểm thử tích hợp toàn trình (End-to-End Testing)
  - Test luồng Setup: Cắm ESP32 ➔ Bật app ➔ Kết nối BLE nhập WiFi (3s) ➔ App tự quét Dongle LAN ➔ Bắn HTTP LAN ➔ Auto Claim ➔ Vào Dashboard có dữ liệu ngay.
  - Test luồng cảnh báo: Rút dây mạng biến tần hoặc hạ dung lượng pin ➔ Kiểm tra tốc độ nhận thông báo FCM trên điện thoại (< 5 giây).
- [ ] **Task 9.2**: Tối ưu pin & tài nguyên điện thoại
  - Giảm tần suất polling khi app chạy ngầm hoặc người dùng tắt màn hình.
  - Tối ưu bộ nhớ đệm hình ảnh và vẽ biểu đồ.
- [ ] **Task 9.3**: Đóng gói & Chuẩn bị phát hành
  - Cấu hình Icon app, Splash screen sang trọng với logo LuxMonitor.
  - Build Android App Bundle (.aab / .apk) có ký chữ ký phát hành (Keystore).
  - Build iOS Archive (.ipa) qua Xcode cho TestFlight.

---

## 5. Bảng Đặc tả Kỹ thuật Chi tiết

### 5.1 Giao thức BLE Provisioning (Cực kỳ Tinh gọn)

- **Service UUID**: `19B10000-E8F2-537E-4F6C-D104768A1214`
- **Chỉ duy nhất 1 Characteristic**: `WIFI_PROV_CHAR` (`UUID: ...-0001` - Write, Notify)
  - **App ghi vào**: `{"ssid":"WiFiNhaMinh","pass":"matkhau123"}`
  - **ESP32 phản hồi qua Notify**: `{"status":"connected","ip":"192.168.1.55"}` hoặc `{"status":"failed","reason":"wrong_pass"}`
  - Ngay sau đó: **ESP32 ngắt và deinit Bluetooth**.

### 5.2 Giao tiếp HTTP API Mạng LAN (Giữa Mobile App và ESP32)

| Phương thức | Endpoint trên ESP32 | Tham số / Body | Ý nghĩa |
|---|---|---|---|
| `POST` | `http://<ip>/api/dongle/save_config` | `?ip=192.168.1.88&dserial=DG...&iserial=4313...` | Mobile đẩy IP và Serial của Dongle vừa tìm được qua LAN vào ESP32 |
| `GET` | `http://<ip>/api/device/claim-info` | Không | Mobile đọc `{ serial, device_code, ready }` từ ESP32 sau khi Server cấp mã qua MQTT |

### 5.3 Danh sách API Backend Cloud sử dụng trên Mobile

| Phương thức | Đường dẫn API | Mục đích | Ghi chú |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | Đăng nhập lấy Bearer token | Đã có |
| `POST` | `/api/v1/auth/register` | Đăng ký tài khoản người dùng | Đã có |
| `GET` | `/api/v1/devices` | Lấy danh sách biến tần của user | Đã có |
| `POST` | `/api/v1/devices/claim` | Gán biến tần vào tài khoản (Serial + Code) | Đã có |
| `GET` | `/api/v1/devices/{serial}/realtime` | Lấy dữ liệu tức thời (SOC, PV, Lưới, Tải) | Đã có |
| `GET` | `/api/v1/devices/{serial}/dashboard` | Lấy biểu đồ 24h đường cong công suất | Đã có |
| `GET` | `/api/v1/devices/{serial}/daily` | Lấy thống kê sản lượng năng lượng theo ngày | Đã có |
| `GET` | `/api/v1/devices/{serial}/settings/schema` | Lấy danh mục cấu hình biến tần | Đã có |
| `POST` | `/api/v1/devices/{serial}/settings/read` | Gửi yêu cầu đọc thông số từ biến tần | Đã có |
| `PUT` | `/api/v1/devices/{serial}/settings/field` | Ghi cài đặt thông số mới | Đã có |
| `POST` | `/api/v1/fcm/tokens` | **Mới**: Đăng ký FCM token của máy điện thoại | Cần làm ở Phase 1 |
| `DELETE`| `/api/v1/fcm/tokens` | **Mới**: Hủy đăng ký FCM token khi đăng xuất | Cần làm ở Phase 1 |
| `GET` | `/api/v1/notifications` | **Mới**: Lấy lịch sử thông báo sự kiện | Cần làm ở Phase 1 |

---

## 6. Rủi ro Kỹ thuật & Biện pháp Xử lý

1. **Triệt tiêu hoàn toàn rủi ro nghẽn Bluetooth / WiFi**:
   - Nhờ chuyển sang mô hình: BLE **chỉ chạy đúng 2–3 giây** để nạp WiFi, sau đó tắt hẳn. ESP32 không bao giờ phải chịu áp lực chạy song song BLE và WiFi, không sợ xung đột RF 2.4GHz, RAM luôn trống hơn 180KB.
2. **Quét Dongle LAN cực nhanh**:
   - Điện thoại quét UDP hoặc socket song song trên dải LAN gia đình chỉ mất 1–2 giây, không tốn tài nguyên ESP32.
3. **Chống spam cảnh báo đẩy (FCM Spam)**:
   - Triển khai **Hysteresis** (chỉ cảnh báo lại khi SOC đã hồi phục lên > 20% rồi mới giảm lại) và **Cooldown Cache** (tối thiểu 15 phút giữa 2 thông báo cùng loại).
