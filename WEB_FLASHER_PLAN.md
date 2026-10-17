# Kế hoạch Triển khai: Web Firmware Flasher cho ESP32 (LuxMonitor)

> **Mục tiêu**: Cho phép người dùng/kỹ thuật viên nạp và nâng cấp firmware trực tiếp cho các bo ESP32 (ESP32-C3 SuperMini, ESP32 Classic DevKit,...) ngay trên trình duyệt web qua cổng USB (Web Serial API), tự động nhận diện dòng chip để map đúng binary và offset flash, hỗ trợ 2 tùy chọn nạp: **Flash mới** (toàn bộ) và **Update FW** (chỉ nạp app/giữ cấu hình).

---

## 1. Yêu cầu nghiệp vụ & Kỹ thuật

### 1.1. Tự động nhận diện loại bo (Chip Auto-Detection)
- Khi người dùng cắm ESP32 vào cổng USB và nhấn **"Kết nối ESP32"**, trình duyệt mở Web Serial picker.
- Thư viện `esptool-js` gửi lệnh handshake/sync với ROM bootloader của vi điều khiển.
- ROM bootloader tự động trả về định danh chip:
  - **`ESP32-C3`**: Dòng chip RISC-V (sử dụng trên *ESP32-C3 Super Mini*).
  - **`ESP32`**: Dòng chip Xtensa Dual-core cổ điển (sử dụng trên *ESP32 DevKit V1 / D0WD*).
  - (Có thể mở rộng `ESP32-S3`, `ESP32-S2` nếu sau này dùng).
- JavaScript đọc được `chip.CHIP_NAME`, MAC address, Flash size, Crystal freq.
- **Hệ thống tự động kích hoạt profile nạp tương ứng** với loại chip đó mà người dùng không cần phải chọn thủ công.

### 1.2. Hai tùy chọn nạp (Flashing Modes)

| Chế độ | Hành động Flash | Danh sách phân vùng nạp | Giữ cấu hình WiFi / NVS? | Ứng dụng thực tế |
|---|---|---|:---:|---|
| **1. Flash mới**<br>*(Full / Factory Flash)* | • Xóa toàn bộ flash (`Erase All`)<br>• Ghi đè toàn bộ phân vùng | • `bootloader.bin`<br>• `partitions.bin`<br>• `boot_app0.bin`<br>• `firmware.bin` (App)<br>• `littlefs.bin` (Web Data & Icons) | ❌ Không<br>*(Đưa về xuất xưởng)* | • Bo mới tinh chưa nạp gì<br>• Bo bị lỗi/brick/kẹt loop boot<br>• Muốn cài đặt lại sạch sẽ |
| **2. Update FW**<br>*(Cập nhật / Fast OTA)* | • **Không** xóa flash<br>• Chỉ ghi đè phân vùng ứng dụng (App) và tùy chọn LittleFS | • `firmware.bin` (App tại offset `0x10000`)<br>• *Tùy chọn*: `littlefs.bin` (nếu giao diện web nội bộ có đổi) | ✅ Có<br>*(Bảo toàn WiFi, token, lịch sử NVS)* | • Nâng cấp tính năng mới<br>• Sửa lỗi code logic<br>• Nhanh gọn (chỉ 10–15s) |

---

## 2. Bản đồ phân vùng bộ nhớ (Flash Memory Map)

Khác biệt cốt lõi giữa **ESP32 Classic** và **ESP32-C3** nằm ở địa chỉ Bootloader và kiến trúc CPU:

| Phân vùng | File Binary | Địa chỉ Offset trên **ESP32-C3** | Địa chỉ Offset trên **ESP32 Classic** | Ghi chú |
|---|---|:---:|:---:|---|
| **Bootloader** | `bootloader.bin` | `0x0000` | `0x1000` | ⚠️ **C3 nạp tại 0x0, ESP32 nạp tại 0x1000** |
| **Partitions** | `partitions.bin` | `0x8000` | `0x8000` | Bảng phân vùng (4MB) |
| **Boot App0** | `boot_app0.bin` | `0xe000` | `0xe000` | Khởi tạo con trỏ OTA về app0 |
| **Application** | `firmware.bin` | `0x10000` | `0x10000` | Mã nguồn chương trình chính |
| **LittleFS** | `littlefs.bin` | `0x290000` | `0x290000` | Giao diện Web nội bộ ESP32 + SVG icons |

*(Các offset trên khớp chính xác với PlatformIO build output của dự án `Esp32` và `Esp32Meter`)*.

---

## 3. Kiến trúc Giải pháp

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                      Trình duyệt Web (Chrome / Edge / Opera)                     │
│                                                                                 │
│   [ views/WebFlasher.vue ] ◄──► [ composables/useWebFlasher.js ]                │
│                                           │                                     │
│                                     esptool-js                                  │
│                                           │                                     │
│                             Web Serial API (navigator.serial)                   │
└───────────────────────────────────────────┬─────────────────────────────────────┘
                                            │ Cáp USB
                                            ▼
                      ┌───────────────────────────────────────────┐
                      │             ESP32 / ESP32-C3              │
                      │  ROM Bootloader (CH340 / USB-Serial-JTAG) │
                      └───────────────────────────────────────────┘
                                            ▲
                                            │ Tải file .bin
┌───────────────────────────────────────────┴─────────────────────────────────────┐
│                          Laravel Web Server / SPA Backend                       │
│                                                                                 │
│   public/firmware/                                                              │
│     ├── manifest.json  (danh sách phiên bản, chip, offset, file url)            │
│     ├── esp32-c3/      (bootloader.bin, partitions.bin, firmware.bin, littlefs) │
│     └── esp32-devkit/  (bootloader.bin, partitions.bin, firmware.bin, littlefs) │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Các bước triển khai chi tiết (Phases)

### Phase 1: Chuẩn bị kho Firmware trên Web Server & Script Đồng bộ
1. **Cấu trúc thư mục tĩnh chứa binary**:
   Tạo thư mục trong `cloud/lux_monitor_manager/public/firmware/`:
   ```
   public/firmware/
   ├── manifest.json
   ├── c3/
   │   ├── bootloader.bin
   │   ├── partitions.bin
   │   ├── boot_app0.bin
   │   ├── firmware.bin
   │   └── littlefs.bin
   └── devkit/
       ├── bootloader.bin
       ├── partitions.bin
       ├── boot_app0.bin
       ├── firmware.bin
       └── littlefs.bin
   ```
2. **File `manifest.json`**:
   Mô tả chi tiết cấu hình nạp, danh sách file, offset cho từng loại chip:
   ```json
   {
     "version": "1.0.0",
     "updated_at": "2026-10-07",
     "projects": [
       { "id": "luxmonitor", "name": "LuxMonitor Standard" },
       { "id": "esp32meter", "name": "Esp32Meter (LuxMonitor + MeterEmu)" }
     ],
     "targets": {
       "ESP32-C3": {
         "name": "ESP32-C3 Super Mini",
         "chip_family": "ESP32-C3",
         "baudrate": 460800,
         "flash_mode": "dio",
         "flash_size": "4MB",
         "full_flash": [
           { "offset": 0, "path": "/firmware/c3/bootloader.bin", "label": "Bootloader" },
           { "offset": 32768, "path": "/firmware/c3/partitions.bin", "label": "Partitions" },
           { "offset": 57344, "path": "/firmware/c3/boot_app0.bin", "label": "Boot App0" },
           { "offset": 65536, "path": "/firmware/c3/firmware.bin", "label": "Firmware App" },
           { "offset": 2686976, "path": "/firmware/c3/littlefs.bin", "label": "LittleFS Web Data" }
         ],
         "update_flash": [
           { "offset": 65536, "path": "/firmware/c3/firmware.bin", "label": "Firmware App" }
         ]
       },
       "ESP32": {
         "name": "ESP32 DevKit Classic",
         "chip_family": "ESP32",
         "baudrate": 460800,
         "flash_mode": "dio",
         "flash_size": "4MB",
         "full_flash": [
           { "offset": 4096, "path": "/firmware/devkit/bootloader.bin", "label": "Bootloader" },
           { "offset": 32768, "path": "/firmware/devkit/partitions.bin", "label": "Partitions" },
           { "offset": 57344, "path": "/firmware/devkit/boot_app0.bin", "label": "Boot App0" },
           { "offset": 65536, "path": "/firmware/devkit/firmware.bin", "label": "Firmware App" },
           { "offset": 2686976, "path": "/firmware/devkit/littlefs.bin", "label": "LittleFS Web Data" }
         ],
         "update_flash": [
           { "offset": 65536, "path": "/firmware/devkit/firmware.bin", "label": "Firmware App" }
         ]
       }
     }
   }
   ```
3. **Script tự động sync binary từ PlatformIO**:
   Tạo file `cloud/tools/sync-firmware.ps1` để copy file bin sau khi build từ `Esp32/.pio/build/` và `Esp32Meter/.pio/build/` sang `public/firmware/`.

---

### Phase 2: Cài đặt Thư viện & Viết Composable `useWebFlasher.js`
1. **Cài đặt thư viện**:
   ```bash
   cd cloud/lux_monitor_manager
   npm install esptool-js
   ```
2. **Xây dựng `resources/js/composables/useWebFlasher.js`**:
   - Quản lý trạng thái:
     - `isSupported`: Kiểm tra `navigator.serial` (cảnh báo nếu trình duyệt không hỗ trợ).
     - `isConnected`: Trạng thái kết nối cổng COM.
     - `isFlashing`: Đang trong tiến trình ghi flash.
     - `chipInfo`: Lưu thông tin chip (`chipName`, `macAddress`, `flashSize`).
     - `detectedTarget`: Đối tượng target mapping từ `manifest.json`.
     - `progress`: % tiến trình ghi flash.
     - `statusText`: Thông điệp trạng thái hiện tại (ví dụ: "Đang đồng bộ bootloader...", "Đang ghi firmware...").
     - `terminalLogs`: Danh sách log dòng (hỗ trợ hiển thị terminal UI).
   - Các hàm chính:
     - `connect()`: Mở Web Serial, khởi tạo `Transport` & `ESPLoader`, chạy sync, lấy thông tin chip và tự động match profile từ manifest.
     - `disconnect()`: Đóng cổng serial an toàn.
     - `flash({ mode, includeLittleFs })`:
       - Tải các file binary từ server bằng `fetch()` thành `ArrayBuffer` / `binaryString`.
       - Nếu `mode === 'full'`: gọi `eraseFlash()` rồi nạp toàn bộ danh sách `full_flash`.
       - Nếu `mode === 'update'`: chỉ nạp danh sách `update_flash` (kèm LittleFS nếu người dùng tích chọn).
       - Callback `reportProgress` cập nhật thanh tiến trình theo % thực tế.
       - Sau khi hoàn thành: gọi `hardReset()` để bo mạch tự khởi động lại vào firmware mới.

---

### Phase 3: Xây dựng Giao diện UI `WebFlasher.vue`
Tạo `resources/js/views/WebFlasher.vue` với thiết kế giao diện hiện đại, trực quan:
1. **Bước 1 — Kiểm tra trình duyệt & Hướng dẫn kết nối**:
   - Banner cảnh báo nếu không dùng Chrome/Edge/Opera (kèm nút hướng dẫn).
   - Hướng dẫn nhanh: Cắm cáp USB nối ESP32 với máy tính.
   - Nút lớn: **[ 🔌 Kết nối thiết bị qua USB ]** (hiển thị màu nổi bật, icon USB).
2. **Bước 2 — Nhận diện thiết bị (Card thông tin sau khi kết nối thành công)**:
   - Badge xanh: `Đã kết nối COM`.
   - Thông tin nhận diện:
     - **Dòng Chip**: `ESP32-C3 (RISC-V)` hoặc `ESP32 Classic` (tự động nhận diện).
     - **Địa chỉ MAC**: `XX:XX:XX:XX:XX:XX`.
     - **Kích thước Flash**: `4MB`.
   - Tự động hiển thị Firmware Profile tương ứng kèm tag: `Tự động khớp chính xác`.
3. **Bước 3 — Chọn cấu hình nạp**:
   - Radio chọn chế độ (2 card lựa chọn có icon và mô tả rõ):
     - 🔘 **Flash mới (Toàn bộ / Cài đặt gốc)**: Khuyên dùng cho bo mạch mới hoặc cần khôi phục lại từ đầu. Xóa sạch và nạp lại toàn bộ.
     - 🔘 **Update FW (Nâng cấp nhanh)**: Chỉ cập nhật chương trình, giữ nguyên cấu hình WiFi và cài đặt thiết bị đã lưu.
   - Checkbox phụ (khi chọn Update FW):
     - `[ ] Cập nhật cả giao diện Web nội bộ (LittleFS)` (mặc định tắt).
4. **Bước 4 — Tiến trình nạp & Terminal Log**:
   - Nút hành động: **[ ⚡ Bắt đầu nạp Firmware ]**.
   - Thanh tiến trình:
     - Progress bar hiển thị phần trăm mượt mà.
     - Text trạng thái: `Đang ghi Firmware App (65%)... [128KB / 940KB]`.
   - Hộp Terminal Log (Dark terminal box):
     - Cuộn tự động hiển thị từng dòng log từ `esptool-js` (Connecting..., Erasing flash..., Writing at 0x00010000...).
5. **Bước 5 — Hoàn tất**:
   - Modal/Banner thông báo thành công: `Nạp thành công! Thiết bị đã tự khởi động lại`.
   - Gợi ý tiếp theo: Kết nối vào điểm phát WiFi `LuxMonitor-XXXX` hoặc mở trang cài đặt.

---

### Phase 4: Tích hợp Routing & Menu Điều hướng trong Vue SPA
1. **Vue Router** (`resources/js/router/index.js`):
   - Thêm route:
     ```js
     {
         path: '/flasher',
         name: 'tools.flasher',
         component: () => import('../views/WebFlasher.vue'),
     }
     ```
   - Cho phép mở cả khi chưa đăng nhập (để tiện lợi cho kỹ thuật viên setup nhanh) lẫn khi đã đăng nhập.
2. **Menu Điều hướng** (`resources/js/layouts/AppLayout.vue`):
   - Thêm nút / link **"Nạp Firmware USB"** vào User Menu hoặc thanh Topbar/Header để truy cập nhanh.

---

### Phase 5: Thử nghiệm, Xử lý lỗi ngoại lệ & Tối ưu hóa
1. **Xử lý đặc thù của ESP32-C3 SuperMini (USB-Serial/JTAG)**:
   - Chip C3 Super Mini dùng giao tiếp USB CDC trực tiếp. Một số trường hợp firmware cũ bị kẹt loop crash khiến cổng serial không phản hồi DTR/RTS reset tự động.
   - Thêm hướng dẫn trực quan trên giao diện: *"Nếu không kết nối được, hãy giữ nút BOOT, cắm lại cáp USB (hoặc bấm nút RESET) rồi thử lại"*.
2. **Xử lý tốc độ baudrate**:
   - Mặc định baudrate kết nối: 115200 (ổn định cho handshake).
   - Tự động đàm phán nâng baudrate lên 460800 hoặc 921600 khi truyền dữ liệu binary để tăng tốc độ nạp (chỉ mất ~10 giây).
3. **CORS & Binary Fetch**:
   - Do cùng origin (SPA phục vụ từ Laravel), các file tĩnh `.bin` tải về trực tiếp không bị vướng CORS.
   - Bổ sung cấu hình MIME type cho `.bin` trong web server nếu cần.

---

## 5. Bảng tổng hợp các file sẽ tạo / chỉnh sửa

| STT | File | Hành động | Mục đích |
|---|---|---|---|
| 1 | `public/firmware/manifest.json` | Tạo mới | Cung cấp metadata, offset bộ nhớ và URL tải file binary |
| 2 | `public/firmware/{c3,devkit}/*.bin` | Copy/Sync | Lưu các file binary biên dịch từ ESP32 |
| 3 | `cloud/tools/sync-firmware.ps1` | Tạo mới | Script tiện ích copy binary sau khi build PlatformIO |
| 4 | `package.json` | Cập nhật | Cài đặt `esptool-js` |
| 5 | `resources/js/composables/useWebFlasher.js` | Tạo mới | Logic kết nối Web Serial, auto-detect chip, nạp flash |
| 6 | `resources/js/views/WebFlasher.vue` | Tạo mới | Màn hình giao diện Web Flasher |
| 7 | `resources/js/router/index.js` | Chỉnh sửa | Đăng ký route `/flasher` |
| 8 | `resources/js/layouts/AppLayout.vue` | Chỉnh sửa | Bổ sung nút liên kết vào menu ứng dụng |

---

## 6. Trình tự thực hiện đề xuất

- **Bước 1**: Phê duyệt kế hoạch này.
- **Bước 2**: Thực hiện **Phase 1** (Tạo cấu trúc `public/firmware/`, file `manifest.json`, chuẩn bị binary từ PlatformIO).
- **Bước 3**: Thực hiện **Phase 2** (Cài đặt `esptool-js`, xây dựng `useWebFlasher.js`).
- **Bước 4**: Thực hiện **Phase 3 & 4** (Tạo component `WebFlasher.vue`, đăng ký router và menu).
- **Bước 5**: Kiểm thử trực tiếp với phần cứng thật (cắm bo ESP32 / ESP32-C3 qua USB kiểm tra nhận diện và 2 chế độ nạp).
