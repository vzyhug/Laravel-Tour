# VietGo: áp dụng design-traveloka-platform

Ngày triển khai: 23/10/2026. Thị trường: Việt Nam; B2C; website responsive.

Tài liệu này mô tả luồng **tour hiện hữu**. Phần mở rộng lưu trú, xe khách, đưa đón, thuê xe và hoạt động đã được triển khai; xem [MULTI_SERVICE_PLATFORM.md](MULTI_SERVICE_PLATFORM.md) để chạy và quản trị.

**[Đề xuất đã triển khai cho VietGo]**: đây là thiết kế của đồ án, không phải schema, API hay chính sách nội bộ của Traveloka. Không dùng logo, font độc quyền hoặc ảnh chiến dịch Traveloka. Ảnh và nguồn hiện hữu của project được giữ lại.

## Phạm vi và mốc hoàn thành

Theo hướng dẫn skill dành cho đồ án đặt tour, P0 tập trung vào **tour có lịch khởi hành**. Mỗi đơn chứa một departure và nhiều hành khách. Danh mục, lịch/tồn, báo giá, đơn, payment mô phỏng và chứng từ được phân biệt.

| Nhóm | Trạng thái |
|---|---|
| Tour có lịch khởi hành | Tìm kiếm → ngày/số khách → thông tin liên hệ/người đi → báo giá → giữ chỗ → thanh toán mô phỏng → chứng từ demo → hủy/hoàn mô phỏng |
| Lưu trú | Đã có phòng, rate plan, tồn/giá từng đêm và phân khách vào phòng |
| Xe khách | Đã có chuyến, trạm, ghế và tồn theo đoạn tuyến |
| Đưa đón sân bay | Đã có vùng/phụ phí, giờ đón, hành lý và quỹ xe |
| Thuê xe | Đã có khoảng thời gian + buffer, giấy tờ, cọc và biên bản xe |
| Hoạt động/vé tham quan | Đã có ngày/khung giờ, nhóm tuổi và ghi nhận lượt sử dụng |

Không bổ sung dịch vụ ngoài năm nhóm của skill (hoạt động cùng nhóm tour). Các dịch vụ mới dùng bảng và luồng riêng; tính năng hiện hữu của admin, coupon, favorites, pickup và review được giữ.

## Tác nhân và quyền

| Actor | Quyền | Giới hạn |
|---|---|---|
| Khách chưa đăng nhập | Tìm, xem chi tiết, xem trợ giúp | Đặt tour yêu cầu tài khoản theo luồng hiện tại |
| Người đặt | Nhập contact, quản lý đơn và tải chứng từ của mình | Được đặt thay; không đồng nhất với hành khách |
| Hành khách | Có tên/nhóm giá trong đơn | Không tự có quyền hủy hoặc xem tài khoản |
| Admin / Sale | Quản lý tour, lịch, đơn và thanh toán theo middleware hiện có | Thao tác trạng thái có lịch sử; không bổ sung nút thu tiền thật |
| Admin | Quản lý mã giảm giá, nhân viên, địa điểm | Không thay RBAC của các nhánh đã tích hợp |
| Guide | Theo quyền hiện có | Không được mở thêm quyền quản trị tài chính |

Thông tin người liên hệ lưu riêng trong `service_snapshot.contact`. Giấy tờ hành khách không được đưa vào chứng từ demo hoặc lịch sử sự kiện.

## Quy tắc và luồng

### FR-SEARCH: khám phá, tìm và chọn lịch

- Homepage có form điểm đến/từ khóa, ngày khởi hành và số người. Query nằm trong URL.
- Search kiểm ngày không ở quá khứ, số người 1–20 và tỉnh tồn tại.
- Khi có ngày/số khách, chỉ trả tour có departure mở bán, còn đủ chỗ, đúng ngày nếu được chọn.
- Ngày/số người đi tiếp từ card → detail → form booking. Form booking mặc định tạo số dòng người lớn tương ứng; khách có thể đổi nhóm giá.
- Giữ hai ô giá Từ/Đến. Nhập 5 triệu → 2 triệu tìm trong [2, 5] triệu, mặc định giá giảm dần; tùy chọn sắp xếp rõ ràng vẫn được ưu tiên.
- Trang theo tỉnh dùng cùng bộ lọc thay vì bỏ qua query.

### FR-QUOTE: xác nhận giá và điều kiện

- `TourQuote` tạo báo giá mã hóa/xác thực bằng application key, gắn customer và departure; hết hạn sau `booking.quote_minutes` (mặc định 10 phút).
- Báo giá chụp tên tour, lịch/giờ, đơn giá người lớn/trẻ em, dịch vụ bao gồm, điểm tập trung, policy, VND và Asia/Ho_Chi_Minh.
- Khi tạo đơn, khóa departure và so sánh báo giá với dữ liệu hiện tại. Giá, lịch hoặc policy đổi / token sai / token thuộc người khác / hết hạn → không giữ chỗ; yêu cầu xác nhận lại.
- Tổng tiền server = đơn giá VND × nhóm khách − coupon hợp lệ; so sánh `expected_total` để không âm thầm đổi tổng tiền đã xem. Frontend không quyết định số tiền thanh toán.
- Contact, danh sách người đi và xác nhận điều kiện là trường bắt buộc. Khi báo giá thay đổi, checkbox xác nhận được bỏ chọn để khách đọc lại.
- Coupon copy chỉ sao chép. Preview và lúc đặt đều kiểm điều kiện; số người thay đổi thì preview bị hủy và cần tính lại.
- Nhóm trẻ em dưới 12 tuổi là quy tắc của catalog tour hiện tại của đồ án, không phải quy tắc cho các dịch vụ khác hay của Traveloka.

### FR-HOLD: tồn và chống tạo đơn trùng

- Mỗi lần mở checkout có UUID. Unique `(customer_id, checkout_key)` và khóa customer bảo vệ submit/retry cùng chủ thể.
- `request_hash` lưu fingerprint cấu hình đã xác nhận. Retry cùng key/cùng dữ liệu trả cùng đơn; đổi dữ liệu trên key đã dùng bị chặn.
- Việc giữ chỗ, tạo hành khách, snapshot và sự kiện cùng transaction. Departure được khóa trước kiểm/trừ tồn; hết chỗ thì không tạo đơn mới.
- Đơn mới có `payment_expires_at` cố định, mặc định 15 phút. Tải lại trang không làm mới hạn.
- `ExpireBooking` chỉ giải phóng đơn pending đã hết hạn, chưa có payment. Khóa booking + departure; cập nhật hủy/hết hạn và trả chỗ đúng một lần.
- Job `bookings:expire` chạy mỗi phút. Mở checkout cũng dọn đơn hết hạn của departure; xem đơn/thanh toán kiểm lại expiry để tránh nhận tiền sau hạn.

### FR-PAY: payment và chứng từ demo

- Chỉ có adapter mô phỏng: ngân hàng, ví, thẻ, tiền mặt. Không kết nối PSP, không thu hoặc lưu số thẻ/CVV.
- Chọn kết quả mô phỏng thành công hoặc thất bại. Thất bại không tạo payment completed; được thử lại trước deadline. Có sự kiện để theo dõi lần thử thất bại.
- Thành công ghi một payment, xác nhận đơn và tạo hóa đơn mô phỏng cùng transaction. Unique booking trên payments và row lock chặn ghi nhận tiền hai lần.
- UI tách đặt chỗ / thanh toán / chứng từ. Chỉ đơn confirmed/completed có payment completed được xem chứng từ demo; đơn hủy, hết hạn hoặc chưa trả tiền không được cấp.
- Chứng từ tour `/voucher` và hóa đơn `/invoice` là hai tài liệu khác nhau, cùng kiểm quyền sở hữu. Chứng từ demo không phải vé thực, hóa đơn không phải hóa đơn thuế hợp pháp.
- Không tự gán supplier reference, QR thật hoặc tuyên bố đã có xác nhận từ nhà cung cấp.

### FR-AFTERSALES: lịch sử, hủy và hoàn

- Đơn mới dùng policy và ngày khởi hành đã chụp khi tính hạn hủy/hoàn; sửa config/catalog không sửa ngược điều kiện đơn cũ.
- Hủy và hoàn vẫn là mô phỏng theo chức năng hiện có. Snapshot policy áp dụng cho toàn bộ đơn; chưa có hoàn một hành khách/chặng hoặc đổi lịch.
- Refund amount tính trên payment đã ghi nhận; hủy lặp không trả chỗ/hoàn tiền thêm.
- Voucher của đơn hủy bị từ chối ở backend, kể cả khách còn giữ URL cũ.
- `booking_events` lưu sự kiện held, expired, payment failed/captured demo, voucher available, canceled, refund demo và admin status change. Sự kiện không lưu payload giấy tờ, email, khóa hay thông tin thẻ.

## Dữ liệu và thời gian

Hai migration chỉ bổ sung metadata checkout và bảng sự kiện; không chạy fresh/reset/seed lại dữ liệu có sẵn.

- `bookings.checkout_key`: nullable UUID cho dữ liệu cũ; unique theo customer.
- `request_hash`: SHA-256 cấu hình gửi lên, không lưu raw token.
- `service_snapshot`: JSON có cấu trúc đã chốt; không liên kết động để tính lại tiền đơn mới.
- `payment_expires_at`, `expired_at`: thời điểm giao dịch theo UTC của ứng dụng.
- Ngày/giờ dịch vụ mang Asia/Ho_Chi_Minh; deadline hiển thị đổi sang UTC+7. Đếm ngược dùng deadline server và hiệu chỉnh đồng hồ trình duyệt.
- Tiền checkout mới là số nguyên VND; cột DECIMAL hiện có được giữ để tương thích dữ liệu.
- FK sự kiện giữ booking; không có endpoint sửa/xóa lịch sử xử lý.

**Giới hạn dữ liệu cũ:** các đơn đã có trước migration không có snapshot/expiry. Không bịa giá/policy lịch sử hoặc tự hủy các đơn này. Hiển thị fallback theo quan hệ hiện hữu; chỉ đơn mới có bảo đảm snapshot và deadline. Cần đối chiếu tài liệu gốc nếu muốn backfill lịch sử.

## Route và UI

| Route | UI / hành động |
|---|---|
| `GET /` | Hero VietGo, tìm tour, dịch vụ đang/chưa mở bán, ưu đãi, khám phá |
| `GET /tours` | Filter ngày/khách/giá/vùng, sort/pagination, empty state |
| `GET /tours/{tour}` | Gallery, itinerary, lịch/nhóm khách, giá theo đơn vị |
| `GET /booking/{departure}` | Quote có hạn, contact riêng, hành khách, pickup, coupon, consent |
| `POST /bookings` | Validate quote, idempotency, snapshot, giữ chỗ |
| `GET/POST /payments/{booking}` | Deadline + payment mô phỏng success/failure |
| `GET /account/bookings/{booking}` | Trạng thái riêng, lịch sử, hủy/hoàn mô phỏng |
| `GET /account/bookings/{booking}/voucher` | Chứng từ tour demo có quyền truy cập, in được |
| `GET /account/bookings/{booking}/invoice` | Hóa đơn mô phỏng riêng |
| `GET /support` | FAQ theo đúng khả năng đồ án, không hotline giả |

Các route trên là của VietGo, không phải API Traveloka. Không thêm route webhook nhận tiền từ trình duyệt.

## Vận hành và nghiệm thu

Sau pull:

```powershell
php artisan migrate
npm.cmd run build
php artisan serve
# Terminal khác, để giải phóng chỗ đúng hạn trong nền:
php artisan schedule:work
# Hoặc chạy thủ công khi cần:
php artisan bookings:expire
```

`OtaCheckoutTest` kiểm: retry/key đổi dữ liệu; quote hết hạn/giá đổi/token sai/khác chủ; consent và total; expiry/late payment/release một lần; payment fail/retry/paid không hết hạn; snapshot; voucher sở hữu/đã hủy; ngày/khách còn đủ tồn; thiếu quote không được bỏ qua xác nhận giá.

Các test tích hợp hiện hữu tiếp tục kiểm coupon/pickup/payment/invoice/cancel, quyền admin/guide, review, profile, dữ liệu demo và hồi quy bộ lọc giá. Row lock là cơ chế MySQL; test SQLite kiểm hành vi, không phải chứng minh tải đồng thời hoặc hợp đồng tồn của supplier.

## P1 / P2 còn lại và giới hạn tích hợp

- PSP thật: hợp đồng sandbox, payment attempts/events, webhook xác minh chữ ký + merchant/reference/amount/currency, chống callback trùng/lệch thứ tự, đối soát và khoản tiền về muộn. Không dùng endpoint mô phỏng hiện tại làm cổng thu thật.
- Supplier thật: capability hold/confirm/issue/cancel/refund, trạng thái đang xác nhận/cấp, outbox/jobs, exception queue và compensation khi đã thu nhưng không cấp được dịch vụ.
- Hoàn thật: request, phê duyệt, reservation cho pending refunds, ledger/refund transaction; không coi hủy đơn là hoàn thành chuyển tiền.
- Email queue, đổi lịch, hoàn từng phần, tenant đối tác/CSKH/finance và quyền/audit chuyên biệt.
- Các module mới đã có tồn/chính sách riêng; triển khai đối tác thật và các yêu cầu vận hành nâng cao vẫn cần hợp đồng và kiểm thử tích hợp.

## Nguồn tham chiếu

Đã áp dụng bản skill và các references được cung cấp trong workspace ngày 23/10/2026. Không khảo sát lại website hoặc thử checkout Traveloka trong lần triển khai này. Mọi kiến trúc và quy tắc trong tài liệu là **[Đề xuất cho VietGo]**.

Nguồn công khai mà skill dẫn: [Trang chủ](https://www.traveloka.com/vi-vn), [Tour/hoạt động](https://www.traveloka.com/vi-vn/activities), [Trợ giúp](https://www.traveloka.com/vi-vn/help). Các URL chỉ là liên kết tham khảo tại đây; không dùng làm bằng chứng rằng đã kiểm tra hiện trạng, giá hay điều kiện bán tại thời điểm triển khai.
