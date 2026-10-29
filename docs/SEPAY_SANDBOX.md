# Thanh toán SePay Sandbox trực tiếp, không dùng ngrok

Luồng hiện tại: **Đặt tour → mở thẳng SePay ở tab mới → Giả lập thanh toán → tab VietGo xác minh qua API → xác nhận đơn và hóa đơn demo**.

Không còn QR mở liên kết điện thoại, gateway Python, ngrok, URL công khai hay endpoint IPN. Website tự gọi API riêng của SePay để kiểm tra đơn; không tin tham số `success`/`paid` từ trình duyệt. Sandbox không phát sinh chuyển tiền thật và chức năng này bị chặn khi `APP_ENV=production`.

## Cài đặt trên máy thành viên khác

Cài dự án, cấu hình database và chạy:

```powershell
composer install
php artisan migrate
npm install
npm run build
```

Tại https://my.sepay.vn, kích hoạt **Cổng thanh toán → Sandbox**, rồi điền thông tin trong `.env`:

```dotenv
SEPAY_SANDBOX_ENABLED=true
SEPAY_SANDBOX_MERCHANT_ID=
SEPAY_SANDBOX_SECRET_KEY=
SEPAY_SANDBOX_CA_BUNDLE=
```

Chỉ cần Merchant ID và Secret Key của **Cổng thanh toán Sandbox**, không dùng token của sản phẩm Webhook ngân hàng hoặc khóa production. Khóa để trong `.env`, không push lên Git. `SEPAY_SANDBOX_CA_BUNDLE` là tùy chọn: máy Windows chưa cấu hình CA cho PHP/cURL có thể trỏ tới file CA tin cậy, ví dụ `"C:/Program Files/Git/usr/ssl/cert.pem"` nếu đã cài Git. Luôn bật xác minh TLS.

Sau khi đổi cấu hình, chạy `php artisan config:clear`. Khởi động MySQL và website, ví dụ:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

Không cần tài khoản ngrok hay giữ máy của người viết code hoạt động. Nếu đã cấu hình IPN cho phiên bản cũ trong SePay, có thể tắt cấu hình đó; luồng mới không sử dụng IPN. Không cần các biến cũ `SEPAY_SANDBOX_IPN_SECRET` hoặc `SEPAY_SANDBOX_PUBLIC_URL`.

## Thao tác demo

1. Đăng nhập VietGo, đặt tour và chọn **QR ngân hàng · SePay Sandbox**.
2. Website mở thẳng trang thanh toán SePay ở tab mới; tab VietGo chuyển sang theo dõi trạng thái. Nếu trình duyệt không chạy JavaScript, bấm **Tiếp tục tới SePay**, sau đó quay lại website để kiểm tra kết quả.
3. Trên SePay, chọn **Giả lập thanh toán**. QR ở trang SePay là dữ liệu ngân hàng giả lập, không dùng app ngân hàng thật để chuyển tiền.
4. Quay lại tab VietGo đang mở. Backend gọi API SePay, chỉ xác nhận khi trạng thái `CAPTURED`, đúng mã phiên, số tiền và VND.
5. Trang hiện thành công và nút xem hóa đơn demo. Nếu kết quả chưa cập nhật kịp, trang tự kiểm tra lại; cũng có nút **Kiểm tra trạng thái**.

Dùng trình duyệt trên máy đang chạy website. Không gửi `success_url`, `error_url`, `cancel_url` tới địa chỉ local: tài liệu SePay yêu cầu các URL này phải công khai. Không cần điện thoại. Nếu đóng cả tab VietGo, website chưa tự chạy kiểm tra nền; mở lại thanh toán từ **Chuyến đi của tôi** để đồng bộ kết quả. Tab bị ẩn sẽ tạm dừng kiểm tra và tiếp tục khi được mở lại. Không cần server công khai cho luồng demo này.

## Xác minh và đối soát

- API cố định: `GET https://pgapi-sandbox.sepay.vn/v1/order/detail/{order_invoice_number}`, Basic Auth từ backend. Không đưa Secret Key vào HTML/JavaScript, không chuyển thông tin xác thực sang host khác khi API trả redirect.
- 404 hoặc `AUTHENTICATION_NOT_NEEDED` giữ đơn chưa thanh toán. Timeout, lỗi xác thực, rate limit, lỗi server, JSON sai hoặc mã phiên không khớp không được coi là thành công. Giao diện cho biết chưa xác minh được và cho thử lại.
- Kiểm tra API được gộp giữa các tab bằng cache/lock, tối đa một lần mỗi 5 giây cho mỗi phiên. Không giữ khóa database trong lúc chờ mạng.
- Sau khi xác minh, transaction khóa lịch khởi hành, booking rồi intent, cùng thứ tự với luồng thanh toán của main. Chỉ tạo một Payment/Bill dù refresh, quay lại trang nhiều lần hoặc nhiều tab cùng kiểm tra. Lỗi database rollback cả payment, bill và event, đồng thời nhả lock để thử lại.
- Đơn đã hủy, phiên hết hạn, tổng tiền thay đổi, đã trả bằng cách khác hoặc tiền/tiền tệ không khớp được đưa vào `review`, không tự mở lại đơn hay thanh toán thêm. Chưa có màn hình xử lý review hoặc hoàn tiền qua SePay.
- `payment_gateway_events` lưu kết quả `ORDER_VERIFIED` và nguồn `sepay_order_api`. SePay Sandbox chuyển khoản có thể trả `transactions: []`; hệ thống lưu mã đơn SePay thực (`SEPAY-SBX-ORDER-…`) làm tham chiếu, không tạo mã giao dịch ngân hàng giả. Hóa đơn vẫn ghi rõ demo.
- Dữ liệu từ các phiên bản trước và các giao dịch đã xác nhận được giữ nguyên. Không cần migration mới nếu đã có bảng `payment_gateway_events`.

## Kiểm chứng thực tế

Đã thử một giao dịch Sandbox thực trên cơ sở dữ liệu MySQL kiểm thử: biểu mẫu mở thẳng SePay ở tab mới, chọn **Giả lập thanh toán**, tab VietGo xác minh qua API và tạo đúng một Payment/Bill. Event lưu `ORDER_VERIFIED` với nguồn `sepay_order_api`. Ngrok và gateway không chạy trong lượt thử. Đã kiểm tra giao diện 1440px và 390px, cùng các trường hợp API lỗi, số tiền sai, phiên hết hạn, hủy đơn, kiểm tra lặp và rollback database.

## Kiểm thử

```powershell
php artisan test --filter="SePaySandboxTest|QrPaymentTest"
```

MySQL: dùng `phpunit.mysql.xml` với DB test riêng `TourDB_codex_test`, chỉnh `DB_PORT` theo máy; không chạy RefreshDatabase trên DB làm việc. Kết quả khi tích hợp vào main `c665b7f` ngày 29/10/2026: 20/20 kiểm thử SePay và bảo vệ booking đạt trên MySQL (269 assertions). Toàn bộ SQLite có 166/186 đạt, 20 trường hợp lỗi; main gốc có 159/181 đạt, 22 trường hợp lỗi. Tập tên kiểm thử lỗi sau tích hợp không có trường hợp mới so với main gốc. Các kiểm thử SePay IPN cũ được thay bằng kiểm thử xác minh API; các lỗi còn lại nằm ở luồng demo cũ, báo giá và dữ liệu mẫu. Đây là kết quả kiểm thử, không có nghĩa toàn bộ chức năng của main đã được nghiệm thu.

Tài liệu chính thức:
- https://developer.sepay.vn/vi/cong-thanh-toan/sandbox
- https://developer.sepay.vn/vi/cong-thanh-toan/API/tong-quan
- https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/chi-tiet-don-hang
- https://github.com/sepayvn/sepay-pg-php/blob/main/src/Auth/SignatureGenerator.php

## Tích hợp vào main mới (29/10/2026)

Chuyển phần SePay từ commit `8a58ea5` của dev-Loc vào main `c665b7f`. Giữ nguyên MoMo, VNPAY, phương thức mô phỏng nội bộ và các tuyến nghiệp vụ mới. SePay tuân thủ thời hạn giữ chỗ, chặn giao dịch đang chờ ở phương thức khác, cho phép thử lại sau Payment thất bại và cập nhật bản ghi đó thay vì tạo Payment trùng. Giao dịch đến sau khi hết hạn giữ chỗ hoặc chuyến bị hủy chuyển sang kiểm tra thủ công.

Đã kiểm tra trên database preview riêng: mở SePay trực tiếp, giả lập thanh toán, VietGo xác minh qua API, giữ đúng một Payment/Bill/event và xóa thời hạn giữ chỗ. Không cần IPN, ngrok hoặc gateway Python. Các báo cáo nội bộ và `.env` không được commit.

## Cập nhật lựa chọn thanh toán tour — 29/10/2026

Theo lựa chọn cuối cùng của người dùng, giữ bốn phương án: SePay Sandbox, thanh toán tại văn phòng (mô phỏng), thẻ tín dụng/ghi nợ (mô phỏng) và VietQR demo nội bộ. Chỉ bỏ VNPAY và MoMo khỏi lựa chọn và chặn khởi tạo/mở lại giao dịch bằng hai phương thức đó. `payments.checkout_methods` mặc định là `sepay`, `cash`, `card`, `vietqr_demo`.

Đã khôi phục chọn kịch bản thành công/thất bại/chờ xử lý cho tiền mặt và thẻ; nút xử lý giao dịch mô phỏng đang chờ; tạo, tiếp tục và xác nhận QR demo. Các phương thức mô phỏng vẫn tuân thủ `PAYMENT_DEMO_ENABLED` và bị khóa trong production như trước. SePay được chọn sẵn khi sẵn sàng; nếu thiếu cấu hình, người dùng vẫn có thể chọn phương thức mô phỏng đang khả dụng.

Giữ hóa đơn và dữ liệu giao dịch cũ. Endpoint nhận kết quả đã ký của MoMo/VNPAY vẫn được giữ để xử lý giao dịch đã tạo trước đây. Đơn có giao dịch cũ đang chờ cần đối soát trước khi thu thêm tiền. Chức năng đặt khách sạn không đổi.

Đã kiểm tra giao diện 1440px/390px hiển thị đúng bốn phương thức, chuyển kịch bản theo phương thức và chặn VNPAY/MoMo ở server. Theo yêu cầu khôi phục nhanh, không chạy lại toàn bộ bộ kiểm thử sau xác nhận lựa chọn cuối cùng.
