# Thanh toán VietQR ở chế độ demo

## Trình diễn trước giảng viên

Tính năng mô phỏng trọn luồng QR mà không cần chuyển tiền, mở tài khoản cổng thanh toán hoặc cấu hình webhook ngân hàng thật.

1. Đăng nhập tài khoản khách hàng, chọn lịch khởi hành và đặt tour. Có thể dùng tài khoản mẫu `demo01@vietgo.test` / `Customer@123` trên database đã seed.
2. Tại trang thanh toán, chọn **Chuyển khoản VietQR · Demo**, bấm **Tiếp tục thanh toán**.
3. Trang QR hiển thị đúng số tiền sau giảm giá, mã đặt tour, nội dung giao dịch riêng và đồng hồ 10 phút. Lúc này đơn vẫn chờ thanh toán, chưa có payment hoặc hóa đơn.
4. Bấm **Mở ngân hàng mô phỏng**. Trong tab mới, chọn **Mô phỏng thanh toán thành công**.
5. Quay về tab QR: trạng thái tự cập nhật, có nút **Xem hóa đơn**. Đơn được xác nhận; hóa đơn có nhãn demo và giao dịch có tiền tố `DEMO-`.
6. Đặt một đơn khác để thử **Thất bại** hoặc **Mã hết hạn**. Hai kết quả này không tạo khoản thanh toán/hóa đơn; trang QR cho tạo mã mới với nội dung giao dịch mới.
7. Tải lại trang hoặc gửi lại kết quả thành công: vẫn chỉ có một payment và một hóa đơn. Hủy đơn chưa thanh toán sẽ đóng QR của đơn đó.

Ảnh QR là ảnh SVG thật, tạo trực tiếp trên máy chủ và có thể tải xuống. Payload theo cấu trúc VietQR/NAPAS (TLV, đồng tiền VND và CRC16). Bản demo cố định BIN `000000` và tài khoản giả `VIETGODEMO`, không phải thông tin nhận tiền thật. Ứng dụng ngân hàng thật có thể từ chối mã này; sử dụng ngân hàng mô phỏng trên web để trình diễn. Không có kết nối hoặc xác thực giao dịch tại ngân hàng/NAPAS. Đổi BIN/số tài khoản không làm bản demo trở thành tích hợp thanh toán thật.

## Cài đặt

```sh
composer install
php artisan migrate
npm install
npm run build
```

PHP cần extension XMLWriter để tạo SVG; Composer kiểm tra yêu cầu này khi cài đặt.

Trong `.env` của môi trường phát triển:

```dotenv
APP_ENV=local
PAYMENT_DEMO_ENABLED=true
PAYMENT_QR_LIFETIME_MINUTES=10
```

```sh
php artisan config:clear
```

Flag mặc định tắt trong `.env.example`. Toàn bộ endpoint QR/mô phỏng luôn bị chặn khi `APP_ENV=production`, kể cả khi flag bật. Các phương thức mô phỏng cũ (tiền mặt/MoMo/thẻ) vẫn theo luồng hiện có. Khi demo QR tắt, lựa chọn QR không hiển thị ở trang chọn phương thức.

Database đang có dữ liệu chỉ cần chạy migration mới; không chạy `migrate:fresh`. Bảng `payment_intents` lưu yêu cầu QR và lịch sử các lần thử; bảng `payments` vẫn chỉ ghi giao dịch thành công và giữ ràng buộc duy nhất theo đơn. Không cần thay MySQL, Supabase hoặc cấu hình ảnh.

## Cách xử lý

- `POST /payments/{booking}` với `bank_transfer`: khóa bản ghi booking, lấy tổng tiền ở server, tái sử dụng QR còn hạn hoặc tạo intent mới. Số tiền/nội dung gửi thêm từ trình duyệt không quyết định số tiền phải trả.
- `GET /payments/{booking}/qr/{intent}`: trang QR, trạng thái và hướng dẫn demo. Khách chỉ được xem QR của chính mình và đúng đơn.
- `GET .../image.svg`: tạo ảnh từ payload đã lưu; không gửi dữ liệu đơn cho website QR bên ngoài. Mã đã đóng trả HTTP 410.
- `GET .../status`: polling mỗi 3 giây khi tab đang hiển thị, có nút kiểm tra thủ công. Không suy đoán thanh toán thành công khi lỗi mạng. Nếu JavaScript không chạy, liên kết tải lại trang vẫn dùng được.
- `GET .../demo-bank`: màn hình ngân hàng mô phỏng, chỉ đọc.
- `POST .../demo-bank`: nhận một trong ba kết quả demo, yêu cầu phiên khách hàng, đúng chủ đơn, CSRF và giới hạn tần suất. Đây là thao tác mô phỏng nội bộ, không phải webhook ngân hàng thật.
- Thành công: khóa booking trước intent, đối chiếu tổng tiền/trạng thái/hạn dùng rồi ghi payment, xác nhận booking, tạo hóa đơn và đánh dấu intent đã trả trong cùng transaction. Lỗi database rollback toàn bộ. Gửi lại kết quả không tạo thêm giao dịch.
- Mã hết hạn, đã đóng, đơn hủy, thay đổi số tiền hoặc đã thanh toán qua cách khác không được dùng để ghi nhận thêm khoản thanh toán. Thanh toán QR không trừ chỗ lần nữa.

Thời hạn QR là thời hạn **lần thử thanh toán**, không tự hủy booking/trả chỗ. Chính sách giữ chỗ/hủy tour vẫn theo nghiệp vụ hiện có.

## Kiểm tra

```sh
php artisan test --filter=QrPaymentTest
php vendor/bin/phpunit -c phpunit.mysql.xml --filter=QrPaymentTest
```

MySQL dùng database riêng `TourDB_codex_test`; đặt `DB_PORT` đúng máy. Các kiểm thử bao gồm vector payload/CRC độc lập, số tiền sau giảm giá, không ghi nhận trước kết quả, ba trạng thái demo, thời điểm hết hạn chính xác, tạo lại QR, khóa quyền, tắt demo/production, client sửa amount, kết quả trùng hoặc muộn, hủy đơn và rollback khi tạo hóa đơn lỗi.

Thư viện tạo SVG: [BaconQrCode](https://github.com/Bacon/BaconQrCode). Mẫu payload đối chiếu: [dynamic-vietqr](https://github.com/khoativi/dynamic-vietqr#example); cấu trúc và CRC tham khảo cách triển khai của [subiz/vietqr](https://github.com/subiz/vietqr). Tài liệu dịch vụ tạo QR: [VietQR.io](https://www.vietqr.io/qr-dong).

Để triển khai tiền thật cần tích hợp đối tác/ngân hàng có môi trường thử nghiệm và API giao dịch, xác thực thông báo server, đối soát tiền/mã giao dịch và quy trình hoàn tiền. Bộ mô phỏng này phục vụ phạm vi trình diễn học tập.
