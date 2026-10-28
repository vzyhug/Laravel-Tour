# Thanh toán và hoàn tiền demo

Phạm vi: tour booking hiện tại. Không kết nối ngân hàng/cổng thanh toán, không yêu cầu thẻ, không chuyển hoặc hoàn tiền thật. Khách sạn giữ mô hình thanh toán riêng.

## Thử luồng

1. Tạo booking tour và mở trang thanh toán.
2. Chọn phương thức và kịch bản demo: thành công, thất bại hoặc chờ xử lý.
3. Thất bại cho phép thử lại với một lần thử mới. Chờ xử lý cho phép chủ booking chọn kết quả mô phỏng; không thể mở thêm lần thử song song.
4. Thành công xác nhận booking và tạo một hóa đơn. Gửi lại cùng idempotency key không tạo thêm giao dịch/hóa đơn.
5. Hủy theo chính sách hiện có tạo `payment_refunds`, ghi vào sổ demo, và chỉ đánh dấu payment `refunded` khi tổng hoàn thành công bằng số đã thu. Hoàn một phần giữ payment `completed`; số tiền chi tiết nằm ở refund.
6. Admin/Staff mở Thanh toán, chạy đối soát demo, xem lần thử và các hoàn tiền. Refund thất bại có nút thử lại. Để kiểm thử lỗi, đặt cấu hình nội bộ `payments.mock_refund_outcome` thành `failed`, rồi về `succeeded` để thử lại; không nhận tùy chọn này từ người dùng.

## Dữ liệu và API nội bộ

- `Payment` 1–N `PaymentAttempt`, `PaymentRefund`, `PaymentReconciliation`.
- Một `Payment`/booking giữ tương thích code cũ; nhiều lần thử nằm trong attempts.
- `MockPaymentGateway` ghi sổ `mock_gateway_transactions` độc lập với trạng thái ứng dụng để đối soát.
- `RefundService::request(Booking $booking, float $amount, string $key, string $reason): PaymentRefund` idempotent và giới hạn tổng hoàn. `process(PaymentRefund $refund)` thử lại refund thất bại.
- Khóa theo thứ tự departure → booking → payment, refund khi cần. RefundService không khóa departure.
- Thanh toán đến sau khi hết chỗ/hủy đơn được hoàn mô phỏng, không xác nhận lại booking và không tạo hóa đơn.
- Migration mới đặt UNIQUE `bill.booking_id`; dữ liệu cũ có trùng phải được xử lý trước khi triển khai. Không tự xóa hóa đơn.
- Đối soát chỉ ghi kết quả, không tự sửa giao dịch. Thanh toán dữ liệu cũ không có bằng chứng demo được ghi rõ là cần kiểm tra.

## Giới hạn

Adapter hiện tại chạy đồng bộ và cục bộ. Tích hợp thật cần adapter theo nhà cung cấp được chọn, xác minh webhook, inbox/outbox, retry và đối soát bên ngoài. Endpoint `resolve-demo` yêu cầu đăng nhập và quyền sở hữu; đây không phải webhook của nhà cung cấp. Không dùng các trạng thái mô phỏng làm bằng chứng thu/hoàn tiền thật.

Kiểm thử: `php vendor/bin/phpunit tests/Feature/PaymentWorkflowTest.php` trên database test; không migrate database đang sử dụng để chạy test.


## Bảo vệ settlement và đối soát bổ sung

- Settlement kiểm tra loại giao dịch, khóa idempotency, trạng thái và số tiền trong sổ demo khớp lần thử, payment và booking trước khi ghi thành công. Dữ liệu lệch trả 409; không xác nhận đơn hoặc tạo hóa đơn.
- Refund từ chối số tiền không hữu hạn, không dương hoặc có quá hai chữ số thập phân, tránh ghi nhận hoàn 0 đồng do làm tròn. Kết quả sổ demo phải đúng loại, số tiền và trạng thái trước khi đánh dấu hoàn.
- Đối soát kiểm tra cả lần thử pending/failed, refund failed, trạng thái payment tổng hợp, mã giao dịch và giá trị hóa đơn. Sai lệch chỉ được ghi để kiểm tra, không tự sửa dữ liệu hoặc tự thử lại khoản đã thành công trong sổ demo.
- Đối soát vẫn dùng sổ demo cục bộ; không chứng minh tiền thật đã được thu/hoàn. Chưa có đối soát nguồn bên ngoài hoặc xử lý tranh chấp đồng thời trên MySQL.
