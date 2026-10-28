# SePay Payment Gateway Sandbox

Dùng cho demo học tập, không phát sinh chuyển tiền thật. Chỉ gọi host `pay-sandbox.sepay.vn`; toàn bộ chức năng bị chặn khi `APP_ENV=production`.

## Cấu hình

Tạo tài khoản tại https://my.sepay.vn, kích hoạt **Cổng thanh toán → Sandbox**. Không dùng API token của sản phẩm Webhook ngân hàng và không dùng khóa production.

Điền trong `.env` (không commit hoặc gửi các khóa qua chat):

```dotenv
SEPAY_SANDBOX_ENABLED=true
SEPAY_SANDBOX_MERCHANT_ID=
SEPAY_SANDBOX_SECRET_KEY=
SEPAY_SANDBOX_IPN_SECRET=
SEPAY_SANDBOX_PUBLIC_URL=https://your-tunnel-host
```

`SEPAY_SANDBOX_IPN_SECRET` là chuỗi bí mật riêng dùng để xác thực callback. Trong **Cổng thanh toán → Cấu hình → IPN** của Sandbox, chọn auth type **SECRET_KEY** và đặt cùng giá trị. IPN URL:

```text
https://your-tunnel-host/payments/sepay-sandbox/ipn
```

Không thêm `/payments/...` vào `SEPAY_SANDBOX_PUBLIC_URL`; biến này chỉ chứa URL gốc HTTPS. Thay khóa/tunnel cần `php artisan config:clear`. Không chia sẻ file `.env`, SQL backup hoặc `storage/app/private`.

Chạy `php artisan migrate`, `npm install`, `npm run build`. Không cần cài thêm SDK: SDK hiện yêu cầu Guzzle 7 trong khi Laravel của dự án dùng Guzzle 8. Biểu mẫu được ký HMAC-SHA256 theo mã nguồn chính thức, giữ nguyên thứ tự trường.

## Callback trên máy local

Giữ website chạy tại `127.0.0.1:8001`. Trong hai terminal riêng:

```powershell
python scripts/sepay-sandbox-gateway.py --port 8010 --upstream-port 8001
ngrok http http://127.0.0.1:8010 --inspect=false
```

Cần tài khoản/cấu hình ngrok sẵn có. Lấy host HTTPS ngrok trả về, cập nhật `.env` và IPN trong SePay. Cổng gateway 8010 chỉ nhận callback, trang quay về tĩnh và trang thanh toán điện thoại có chữ ký và hạn dùng. Các đường dẫn trang chủ, admin, `.env`, tài khoản, booking đều trả 404. Không tunnel trực tiếp toàn bộ website 8001. Không bật ngrok inspection để tránh lưu các header xác thực callback.

Giữ cả website, gateway và tunnel chạy trong lúc demo. Ngrok có thể hiện màn hình cảnh báo truy cập lần đầu trên điện thoại; bấm **Visit Site** để tiếp tục. Khi tunnel ngừng hoặc đổi host, liên kết điện thoại cũ không còn dùng được. Dừng hai terminal bằng Ctrl+C sau demo.

## Kịch bản trình diễn với Android

1. Đăng nhập VietGo trên máy tính, đặt tour và chọn **QR ngân hàng · SePay Sandbox**.
2. Trang VietGo hiển thị số tiền lấy từ đơn và QR liên kết có hạn dùng. Giữ trang này mở.
3. Quét bằng Camera/Google Lens trên Android, mở liên kết. QR này mở website thử nghiệm; không phải VietQR chuyển khoản.
4. Trên điện thoại, chọn **Mở SePay Sandbox**, sau đó chọn **Giả lập thanh toán** trong trang của SePay. Không dùng app ngân hàng thật để chuyển tiền. SePay Sandbox dùng ngân hàng giả lập ACMEBank.
5. SePay gửi IPN qua HTTPS; website tự cập nhật thành công và hiện nút xem hóa đơn demo. Không cần đăng nhập VietGo trên điện thoại. Có thể dùng nút mở SePay trong tab mới để thử trên cùng máy tính.

Trang quay về không xác nhận trả tiền. Nếu đóng/hủy tab SePay trước khi thanh toán, đơn vẫn chưa trả tiền; phiên hết hạn sau khoảng `PAYMENT_QR_LIFETIME_MINUTES` (mặc định 10 phút), rồi cho tạo phiên mới. IPN đến sau hạn, sau hủy đơn, sai tiền/tiền tệ/trạng thái hoặc giao dịch bị void được đưa vào **review**, không tự mở lại đơn hay hoàn tiền.

## Xử lý IPN và đối soát

- IPN không cần session khách hàng; riêng đúng endpoint IPN được miễn CSRF, còn biểu mẫu đặt/thanh toán vẫn kiểm tra CSRF.
- Kiểm tra `X-Secret-Key` bằng constant-time comparison; chỉ nhận JSON, giới hạn kích thước và cấu trúc.
- Đối chiếu mã phiên, trạng thái CAPTURED/APPROVED/PAYMENT/BANK_TRANSFER, tiền VND từ cả order và transaction với số tiền đã khóa và tổng tiền hiện tại của đơn.
- Transaction database khóa booking trước intent. IPN lặp không tạo thêm Payment/Bill. Transaction ID dùng lại cho đơn khác bị từ chối. Khi có lỗi database, toàn bộ thay đổi rollback để SePay có thể retry.
- Mã giao dịch SePay sandbox có tiền tố `SEPAY-SBX-`; hóa đơn ghi rõ demo.
- Bảng `payment_gateway_events` lưu các trường đối soát tối thiểu, kết quả `paid`/`review` và lý do; không lưu body gốc, khóa hoặc dữ liệu ngân hàng/thẻ/khách hàng. Dùng bảng này để xem các giao dịch cần kiểm tra. Chưa có màn hình xử lý review hoặc hoàn tiền qua SePay; không tự sửa trạng thái rồi coi như đã hoàn tiền.

## Kiểm chứng

Ngày 28/10/2026 đã thử tài khoản Sandbox thực: biểu mẫu được SePay chấp nhận; bấm **Giả lập thanh toán** trên SePay phát IPN qua ngrok; MySQL kiểm thử cập nhật payment/booking/bill. Không gọi callback giả nội bộ để tạo kết quả này. QR điện thoại được giải mã độc lập, kiểm tra URL có chữ ký và bản sửa URL bị từ chối; đã kiểm tra giao diện 1440px và 390px. Chưa xác nhận thao tác quét trên thiết bị Android vật lý của người dùng.

Tests: `php artisan test --filter="SePaySandboxTest|QrPaymentTest"`. MySQL: dùng `phpunit.mysql.xml` với DB test riêng `TourDB_codex_test`, chỉnh `DB_PORT` theo máy; không chạy RefreshDatabase trên DB làm việc.

Tài liệu chính thức:
- https://developer.sepay.vn/vi/cong-thanh-toan/sandbox
- https://developer.sepay.vn/vi/cong-thanh-toan/IPN
- https://github.com/sepayvn/sepay-pg-php/blob/main/src/Auth/SignatureGenerator.php
