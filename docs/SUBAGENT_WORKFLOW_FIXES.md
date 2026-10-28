# Sửa hồi quy các luồng nghiệp vụ trên duyhung

Code nền: `2254b9c` từ `origin/duyhung`. Sáu tác nhân đã sửa code theo phạm vi được giao; phiên chính rà soát, bổ sung kiểm thử và tích hợp khi các tác nhân bị ngắt do giới hạn sử dụng.

## Nội dung

- Giữ chỗ/đổi lịch: từ chối đổi từ hoặc sang chuyến đang chạy/đã hoàn thành; so giá khách đã xem với giá được khóa khi xác nhận, báo lỗi và rollback nếu giá thay đổi.
- Thanh toán/hoàn tiền: kiểm tra loại giao dịch, số tiền và trạng thái với sổ mô phỏng trước khi xác nhận; từ chối số tiền hoàn không hữu hạn hoặc quá hai chữ số thập phân; mở rộng đối soát cả giao dịch pending/failed, hóa đơn và tổng hoàn.
- Tour riêng: dùng số ngày trong snapshot để tính ngày về; giữ nguyên điểm đón null đã báo giá, kể cả khi tour gốc được sửa sau đó. Chi tiết đơn và hóa đơn cũng dùng snapshot.
- Khách sạn: xác thực ngày/số phòng/số khách ngay tại service; giữ phòng không vượt sang ngày sau ngày check-in; thanh toán/hết hạn xử lý được cả đơn cũ có ngày check-in đã qua.
- Điều hành: bắt đầu/hoàn thành chuyến đóng bán; checkout và đổi lịch kiểm tra trạng thái vận hành; chặn sửa lịch chuyến đã chạy/hủy; tính lại tồn từ sức chứa và đơn hiện có, kể cả khi chưa có đơn. Admin không được hoàn thành booking trước ngày về.
- Tài khoản/thông báo: mail transport lỗi vẫn trả phản hồi quên mật khẩu chung; quét lại outbox cũ quá bảy ngày, tiếp tục batch khi một lần dispatch lỗi và trả exit code thất bại để giám sát.

## Phạm vi kiểm chứng

Kiểm thử SQLite in-memory, mail/queue giả lập và cổng thanh toán demo; không gọi chuyển tiền hoặc gửi thư thật. Không migrate database đang dùng. Chưa kiểm chứng khóa đồng thời trên MySQL hay tương tác trình duyệt.

Kết quả cuối: PHPUnit 156/156 tests đạt, 3.950 assertions. Pint, build Vite, biên dịch Blade và kiểm tra diff đều đạt.
