# Hướng dẫn Luồng xác thực & Đăng ký thiết bị (Dành cho Firmware ESP32)

Tài liệu này mô tả chi tiết các bước giao tiếp MQTT giữa ESP32 và Server (LuxMonitor) để thực hiện tính năng **tự động đăng ký (Auto-register)**, **cấp mã thiết bị (Device Code)**, và **người dùng thêm thiết bị (Claim Device)**.

---

## Tổng quan kiến trúc

- Server sẽ không bắt người dùng (hay admin) phải tạo thủ công thiết bị trước.
- Khi ESP32 lần đầu kết nối và gửi bản tin MQTT, Server sẽ nhận diện serial mới, tự động tạo trên Database và sinh ra **Mã thiết bị (Device Code)** (gồm 6 chữ số ngẫu nhiên).
- Server sẽ đẩy mã này xuống ESP32. ESP32 có nhiệm vụ lưu lại vào Flash (NVM) để hiển thị lên màn hình (hoặc web config) cho người dùng thấy.
- Người dùng lấy `Serial` và `Mã thiết bị` này nhập vào giao diện Web/App để khẳng định quyền sở hữu (Claim).

---

## Luồng giao tiếp chi tiết

### Bước 1: Khởi động & Gửi dữ liệu (Auto-register)
Khi khởi động, ESP32 thực hiện kết nối MQTT và bắt đầu gửi các bản tin telemetry định kỳ như bình thường:
- **Topic:** `luxmonitor/<serial>/telemetry`
- **Payload:** JSON chứa dữ liệu công suất, điện áp...

*Lưu ý: Không cần gọi API hay gửi bản tin MQTT đăng ký riêng biệt. Node.js Worker trên Server tự động lắng nghe topic telemetry, nếu phát hiện `<serial>` mới, nó sẽ tự động kích hoạt tạo thiết bị trên hệ thống.*

### Bước 2: Nhận "Mã thiết bị" từ Server
Ngay khi thiết bị được tạo mới trên Server, Server sẽ sinh mã 6 số (ví dụ: `847192`) và publish xuống:
- **Topic:** `luxmonitor/<serial>/cmd/set_code`
- **Payload:** Chuỗi mã xác minh (Text trơn, ví dụ: `847192`).
- **QoS:** `1`
- **Retain:** `true`

**Nhiệm vụ của ESP32:**
1. Phải **subscribe** vào topic `luxmonitor/<serial>/cmd/set_code`. Do Server gửi với cờ `retain=true`, ESP32 sẽ nhận được ngay lập tức ở lần subscribe đầu tiên (hoặc nếu lỡ mất kết nối rồi kết nối lại).
2. Khi nhận được message ở topic này (và payload không rỗng), ESP32 **phải lưu đoạn mã này vào bộ nhớ Flash (NVM)**.
3. Mã này là **cố định** cho thiết bị, không được tự ý xóa khỏi Flash trừ khi người dùng thao tác hard-reset toàn bộ thiết bị.

### Bước 3: Phản hồi xác nhận (ACK)
Để báo cho Server biết ESP32 đã lưu mã thành công, ESP32 **bắt buộc** phải publish một bản tin xác nhận.
- **Topic:** `luxmonitor/<serial>/cmd/result`
- **Payload (JSON):**
  ```json
  {
    "action": "set_code",
    "status": "success"
  }
  ```

*Tại sao phải có bước này?* Vì Server đang dùng cờ `retain=true`. Nếu ESP32 không ACK, Server sẽ không bao giờ xóa bản tin bị kẹt trên Broker MQTT. Hậu quả là mỗi lần ESP32 mất điện khởi động lại, nó lại nhận lại bản tin cấp mã đó. Khi nhận được ACK `success`, Server sẽ tự động dọn dẹp (publish một chuỗi rỗng `""` với cờ `retain=true` để xóa mã khỏi Broker).

### Bước 4: Hiển thị cho Người dùng
Sau khi lưu thành công, ESP32 hiển thị `Serial` và `Mã thiết bị` ra ngoài cho người dùng thấy (qua màn hình LCD/OLED hoặc giao diện WiFi Web Config nội bộ).

### Bước 5: Người dùng Claim thiết bị
1. Người dùng mở trang Web/App của hệ thống.
2. Bấm "Thêm thiết bị giám sát".
3. Nhập `Serial` và `Mã thiết bị` đang hiển thị trên ESP32.
4. Server kiểm tra khớp mã → Gắn quyền sở hữu thiết bị cho người dùng đó.

---

## Tóm tắt Checklist cho ESP32 Firmware
- [ ] Gửi dữ liệu định kỳ vào `luxmonitor/<serial>/telemetry`.
- [ ] Subscribe vào `luxmonitor/<serial>/cmd/set_code`.
- [ ] Xử lý lưu Flash (NVM) khi nhận được string mã 6 số từ topic `set_code` (bỏ qua nếu payload rỗng `""`).
- [ ] Gửi JSON `{"action": "set_code", "status": "success"}` vào topic `luxmonitor/<serial>/cmd/result` ngay sau khi lưu Flash thành công.
- [ ] Làm màn hình / giao diện hiển thị cho phép người dùng xem được `Mã thiết bị` này để thêm vào app.
