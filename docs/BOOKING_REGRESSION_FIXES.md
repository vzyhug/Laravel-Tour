# Kiểm tra hồi quy đặt tour — 28/10/2026

## Nghiệp vụ hiện tại

Checkout dùng một mức giá cho mọi hành khách (`adult_price`), mỗi người phải có CCCD/Hộ chiếu. Ngày sinh không bắt buộc; nếu nhập phải là ngày hợp lệ trước hôm nay. Trường loại hành khách gửi từ client không quyết định giá: CreateBooking chuẩn hóa thành `adult`. Các cột trẻ em còn giữ để tương thích dữ liệu lịch sử.

Bộ kiểm thử trước đợt sửa đạt 68/76. Tám trường hợp còn lại dùng kỳ vọng cũ về giá trẻ em, giấy tờ, số hành khách seed và factory. Đã cập nhật kỳ vọng theo code/giao diện hiện tại, giữ kiểm tra tính tiền, giới hạn voucher, thanh toán, hóa đơn, hoàn tiền, trả ghế và dữ liệu form sau validation. Đây là cập nhật kiểm thử cho nghiệp vụ hiện có, không phải khôi phục chính sách vé trẻ em.

## Lỗi code đã sửa

Lịch khởi hành có ngày trước hôm nay nhưng vẫn ở trạng thái `open` trước đây có thể được đặt. BookingController hiện từ chối mở form; CreateBooking kiểm tra lại bên trong transaction, trước khi tạo đơn và trừ ghế. Ngày khởi hành là hôm nay vẫn được chấp nhận theo chính sách hiện tại; chưa bổ sung hạn chót theo giờ.

## Kiểm chứng

- PHPUnit SQLite in-memory: 78/78 đạt, 3.484 assertions, gồm hai test mới cho ngày quá khứ và ngày hiện tại.
- Pint cho các file thay đổi và `git diff --check` đạt.
- Không áp dụng migration, không sửa database đang dùng. Chưa kiểm chứng MySQL hoặc tranh chấp đồng thời.

Sau đợt sửa ban đầu, nhánh local duyhung đã fast-forward từ 5a9217b đến origin/duyhung tại 2254b9c, bổ sung các luồng khách sạn, giữ chỗ, thanh toán và điều hành trong bản bàn giao. Đã áp lại thay đổi của phiên làm việc và hợp nhất hai xung đột, giữ kiểm tra chuyến riêng của remote cùng kiểm tra ngày khởi hành của phiên này. Các kết quả 78 tests ở trên thuộc checkout trước khi đồng bộ.

Kiểm chứng sau đồng bộ origin/duyhung: {"tool":"phpunit","result":"passed","tests":133,"passed":133,"assertions":3801,"duration_ms":39264}
