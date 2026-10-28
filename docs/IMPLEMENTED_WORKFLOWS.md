# Các luồng nghiệp vụ đã bổ sung

## Phạm vi

- Tour thông thường: giữ ghế 30 phút, hết hạn tự hủy/trả ghế; khách đổi lịch hoặc đổi tour chưa tạo giao dịch thanh toán, tính lại giá/voucher và lưu lịch sử.
- Thanh toán tour: nhiều lần thử, trạng thái thành công/thất bại/chờ, retry và chống xử lý lặp; hoàn một phần/toàn phần, thất bại và retry; đối soát với sổ giao dịch demo. Mỗi booking tối đa một hóa đơn.
- Tour riêng: khách chấp nhận hoặc từ chối báo giá; chấp nhận tạo chuyến riêng và booking theo tổng giá nhóm, lưu snapshot, bảo vệ phiên bản/hạn báo giá. Chuyến riêng không mở bán qua checkout công khai.
- Khách sạn: quản trị loại phòng và tồn/giá từng đêm; tìm phòng, giữ 15 phút, thanh toán/hủy/hết hạn, xem đơn, hoàn thành lưu trú. Giá từng đêm được chụp khi đặt.
- Điều hành: Guide xem chuyến được giao, điểm danh, bắt đầu/hoàn thành và báo cáo; nhà tổ chức hủy chuyến xử lý booking/hoàn tiền; kiểm tra phân công hướng dẫn viên và sức chứa.
- Tài khoản: quên/reset/đổi mật khẩu khách, thu hồi phiên cũ; outbox thông báo sau commit, chống gửi trùng khi retry bình thường, nhắc chuyến ngày mai.

## Chạy sau khi triển khai code

Áp dụng migration mới bằng `php artisan migrate` trên database đã sao lưu và kiểm tra. Thay đổi hiện tại chưa áp dụng migration vào database SQLite có sẵn trong repo.

Chạy Laravel scheduler mỗi phút (`php artisan schedule:run`) và queue worker theo cấu hình triển khai. Scheduler đã đăng ký `bookings:expire` mỗi phút, hết hạn phòng mỗi phút và `customer:departure-reminders` mỗi giờ. Thời gian nhắc theo timezone cấu hình ứng dụng, hiện mặc định UTC.

Khách sạn hiện có chưa được tự thêm giá/phòng giả. Có thể tạo phòng và tồn tại `/admin/hotel-booking`, hoặc chạy seeder tùy chọn `php artisan db:seed --class=HotelBookingDemoSeeder` để tạo khách sạn giả lập có nhãn DEMO.

## Điểm truy cập

- Khách: tài khoản có liên kết lịch trình/tư vấn, đổi lịch và đơn đặt phòng; trang chi tiết khách sạn có nút xem phòng.
- Admin/Sale: menu quản trị thêm điều hành và đặt phòng; trang thanh toán có đối soát demo và retry refund.
- Guide: đăng nhập chuyển sang `/operations/departures`, chỉ thao tác các chuyến được phân công.
- Đăng nhập có liên kết quên mật khẩu; hồ sơ có liên kết đổi mật khẩu.

## Giới hạn

Thanh toán và hoàn tiền vẫn là mô phỏng, không chuyển tiền thật. Chưa kết nối cổng thanh toán, webhook nhà cung cấp hay hệ thống đặt phòng bên ngoài. Chi tiết tại `PAYMENT_WORKFLOWS.md` và `HOTEL_BOOKING_MVP.md`.

Đổi tour đã thanh toán chưa được hỗ trợ; cần thêm nghiệp vụ thu/hoàn chênh lệch. Email cần mail transport phù hợp; queue/mail lỗi sau commit giữ outbox để thử lại. Việc gửi email không bảo đảm đúng một lần tuyệt đối nếu tiến trình chết sau khi SMTP nhận thư nhưng trước khi ghi kết quả.

SQLite tests kiểm tra tồn chỗ, rollback và xử lý lặp; chưa xác minh tranh chấp khóa dòng trên MySQL.

## Kiểm chứng

Chạy `php vendor/bin/phpunit`, `php vendor/bin/pint --dirty --test`, `php artisan view:cache`, `npm run build`.

Sau tích hợp, PHPUnit có 131 tests: 123 đạt, 7 failures và 1 error. Toàn bộ 55 tests mới đạt. Chạy bản HEAD trước thay đổi trong checkout tạm cho kết quả 76 tests: 68 đạt, cùng 7 failures và 1 error. Các lỗi có sẵn liên quan kỳ vọng giá/trẻ em/ngày sinh và dữ liệu seed; chưa thay đổi nghiệp vụ một loại hành khách của dự án để sửa các kỳ vọng cũ này.
