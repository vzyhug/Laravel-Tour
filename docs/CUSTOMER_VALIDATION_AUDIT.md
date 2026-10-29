# Kiểm tra chức năng và dữ liệu nhập của khách hàng — 29/10/2026

Bản làm việc: `D:\Laravel-Tour-main-latest`, main sau commit tích hợp SePay `1152f4a`. Phạm vi bám sơ đồ chức năng khách hàng được cung cấp: tra cứu, tài khoản, hồ sơ/yêu thích, lịch trình/tư vấn, mã giảm giá, đặt tour, quản lý booking và đặt dịch vụ lưu trú. Không bao gồm rà soát toàn bộ form quản trị.

**Kết luận:** đã sửa các lỗi được xác nhận trong đợt này và kiểm thử lại. Chưa thể kết luận sơ đồ đã được triển khai đầy đủ: còn lỗi ranh giới ngày UTC/Việt Nam và thiếu hai loại chứng từ nêu bên dưới.

## Đối chiếu sơ đồ

| Nhóm chức năng | Kết quả kiểm tra | Giới hạn còn lại |
| --- | --- | --- |
| Tra cứu tour, địa danh, khách sạn | Có tìm kiếm, bộ lọc, phân trang, chi tiết; chặn sai kiểu dữ liệu và khoảng giá đảo; chặn đặt tour ẩn/đã khởi hành | Lọc theo ngày còn chịu ảnh hưởng múi giờ UTC |
| Kiểm tra mã giảm giá | Kiểm tra mã không tồn tại, chưa có hiệu lực, hết hạn, ngừng áp dụng, đơn tối thiểu, giới hạn sử dụng và sử dụng lại; tính lại tiền tại server | Mã giảm giá khác với voucher xác nhận dịch vụ |
| Đăng ký, đăng nhập, quên/đổi mật khẩu | Kiểm tra dữ liệu, mật khẩu xác nhận, độ mạnh, giới hạn bcrypt, email/điện thoại trùng, tài khoản khóa, phiên bị thu hồi và giới hạn đăng nhập | Gửi email thực cần cấu hình SMTP; kiểm thử dùng thông báo giả lập |
| Hồ sơ và tour yêu thích | Chỉ sửa hồ sơ hiện tại; không cho sửa trường quyền/trạng thái; chuẩn hóa email/điện thoại; thêm/bỏ yêu thích và chặn tour ẩn | Đổi email chưa có quy trình xác minh địa chỉ email mới |
| Lập lịch trình và nhận tư vấn | Lưu riêng, kiểm tra phiên bản, địa danh/ngày/giờ/thời lượng, chồng lịch, liên hệ và yêu cầu trùng; giữ snapshot; chấp nhận/từ chối báo giá theo quyền và phiên bản | Ranh giới ngày dự kiến còn dùng UTC |
| Đặt tour và quản lý booking | Kiểm tra hành khách, giấy tờ trùng, ngày sinh, điểm đón, chỗ trống; đổi lịch và xác nhận giá; trạng thái/giữ chỗ; chủ sở hữu; thanh toán; hủy; hóa đơn; đánh giá sau chuyến | Chưa có voucher/phiếu xác nhận tour riêng; hoàn tiền hiện là mô phỏng, chưa hoàn tiền thật qua cổng |
| Đặt dịch vụ và quản lý đơn | Có đặt khách sạn: ngày lưu trú, tối đa 30 đêm, phòng/khách/sức chứa, giá từng đêm, tồn phòng, giữ 15 phút, thanh toán mô phỏng, hủy và trả tồn | Chưa có trang chứng từ/hóa đơn khách sạn để in; chưa có đặt các dịch vụ riêng khác như xe/nhà hàng |

## Các lỗi đã sửa

1. Tìm kiếm nhận `q[]` gây HTTP 500; các bộ lọc loại tour, số ngày, sắp xếp và phân trang chưa được kiểm tra đầy đủ. Bổ sung ràng buộc trước khi truy vấn/render, giữ ràng buộc tỉnh–miền và giá từ–đến đã có.
2. Form khôi phục mật khẩu nhận `email[]` và form khôi phục dữ liệu cũ có thể gây lỗi khi Blade hiển thị một mảng như chuỗi. Kiểm tra email query và chỉ phục hồi giá trị vô hướng trong các ô văn bản, kể cả trường hành khách lồng nhau.
3. Đăng nhập khách hàng/nhân viên giữ lại danh tính của vai trò trước trong cùng session. Xóa danh tính còn lại khi đăng nhập/đăng ký thành công.
4. Email chưa được chuẩn hóa chữ thường. Bổ sung ở đăng ký, đăng nhập, hồ sơ và khôi phục mật khẩu.
5. Có thể dùng hai dạng `0...` và `+84...` để tránh kiểm tra điện thoại trùng. Chuẩn hóa số mới, đối chiếu cả hai dạng đối với dữ liệu cũ và bỏ qua đúng tài khoản hiện tại khi sửa hồ sơ.
6. Form tư vấn chấp nhận chuỗi như `--------` làm số điện thoại. Dùng cùng quy tắc số Việt Nam với tài khoản, kiểm tra kiểu dữ liệu và thêm ràng buộc trên giao diện.
7. Chính sách mật khẩu khác nhau giữa đăng ký và khôi phục/đổi mật khẩu; bcrypt có thể cắt mật khẩu sau 72 byte. Dùng chung chính sách chữ+số, tối thiểu 8 ký tự, tối đa 72 byte; thêm kiểm tra ký tự nhiều byte tại server và trình duyệt.
8. Chưa giới hạn độ dài ô đăng nhập và chưa giới hạn tần suất đăng ký. Bổ sung giới hạn tương ứng; kiểm thử cả khóa tạm sau nhiều lần đăng nhập sai.
9. Danh sách hành khách cho phép trùng CCCD/hộ chiếu. Chặn trùng không phân biệt hoa/thường ở cả tour thông thường và tour theo báo giá; ngày sinh phải đúng định dạng và trước hôm nay. Chỉ chuyển các thuộc tính hành khách được phép vào thao tác tạo bản ghi.
10. Có thể đặt tour đã ẩn hoặc lịch hôm nay đã qua giờ khởi hành bằng đường dẫn trực tiếp. Dùng chung điều kiện mở bán cho form đặt, tạo booking và kiểm tra coupon.
11. Đánh giá mở từ đầu ngày trả tour do so sánh thời gian với ngày lúc 00:00. Chỉ mở đánh giá với đơn hoàn thành hoặc đơn xác nhận có ngày kết thúc trước ngày hiện tại; giao diện và server cùng dùng một điều kiện.
12. Hóa đơn thông thường tính dòng tiền từ giá lịch khởi hành hiện tại. Chuyển sang số tiền đã chốt trong đơn và khoản giảm giá để sửa giá tour sau này không làm đổi chi tiết hóa đơn cũ.
13. Trang tài khoản coi mọi bản ghi Payment là đã thanh toán, kể cả `pending`/`failed`. Hiển thị đúng trạng thái, khôi phục liên kết thử lại, thêm nội dung khi chưa có mã giao dịch hoàn tất.
14. Mở chi tiết booking hết hạn vẫn chưa giải phóng chỗ khi scheduler chưa chạy. Gọi thao tác hết hạn sau khi kiểm tra chủ sở hữu; mở lại không trả chỗ hai lần.
15. Nút tạo lại QR demo gửi `bank_transfer` nên đi vào VNPAY. Đổi đúng `vietqr_demo`; chặn giữ chỗ hết hạn và thống nhất thứ tự khóa lịch khởi hành → booking → intent.
16. Một số phương thức mô phỏng vẫn xác nhận được khi tắt cấu hình demo. Khóa cash/card mô phỏng, xử lý kết quả nội bộ và thanh toán khách sạn; đồng bộ trạng thái khả dụng trên giao diện.
17. VNPAY xử lý mã tham chiếu dạng mảng trước khi kiểm tra, dẫn đến lỗi 500. Kiểm tra tham chiếu ngay tại controller.
18. MoMo/VNPAY có thể xác minh chữ ký tính bằng khóa rỗng khi dữ liệu cấu hình bị thiếu. Từ chối xác minh khi chưa có khóa/định danh; kiểm thử chứng minh phản hồi giả mạo không xác nhận thanh toán. MoMo cũng từ chối cấu trúc dữ liệu lồng nhau trước khi tính chữ ký.
19. Giao diện thanh toán không hiện nút tiếp tục phiên SePay/QR đang chờ sau một Payment thất bại. Cho phép tiếp tục đúng phiên hiện tại.
20. Số khách khách sạn trên form chỉ chọn được 10 trong khi server cho phép 100; thanh toán thất bại/hết hạn lại hiện thông báo thành công. Đồng bộ giới hạn và loại thông báo.
21. Giao diện khách sạn mới mất liên kết mở ảnh lớn. Khôi phục viewer và cập nhật liên kết khi đổi ảnh thu nhỏ.

Các ca kiểm thử cũ dùng `bank_transfer` để giả lập QR hoặc bỏ qua `demo_outcome` đã được cập nhật sang hợp đồng hiện tại. Kiểm thử danh mục được đối chiếu thay đổi có chủ đích của main: 58 khách sạn sau khi loại 6 cơ sở Mường Thanh, 25 điểm đến có ít nhất 2 cơ sở và 6 điểm đến có 1. Không đưa các khách sạn bị loại trở lại. Các kỳ vọng giao diện/ảnh và dữ liệu lịch trình được cập nhật theo thành phần mới; giữ các kiểm tra giá, quyền truy cập, snapshot và idempotency. Kiểm thử quan sát khóa truy vấn đã sửa để chạy được cả dấu nháy SQLite và MySQL.

## Lỗi còn lại: ngày Việt Nam và UTC

**Mức ưu tiên cao cho nghiệm thu.** `config/app.php` hiện cố định `UTC`. Một số ràng buộc `today`, giới hạn ngày trên form và so sánh ngày nghiệp vụ vì vậy theo UTC. Chẳng hạn ở thời điểm `2026-10-28 18:00 UTC` (01:00 ngày 29/10 tại Việt Nam), ngày `2026-10-28` vẫn vượt qua `after_or_equal:today`.

Không đổi múi giờ mặc định trong đợt này vì các datetime cũ như `hold_expires_at`, ngày thanh toán và thời điểm tạo đơn đang được lưu/diễn giải theo UTC. Đổi thẳng sang giờ Việt Nam có thể làm hạn giữ chỗ và thời điểm giao dịch cũ lệch 7 giờ.

Phương án thực hiện:

1. Thống nhất ngày nghiệp vụ theo `Asia/Ho_Chi_Minh`, tiếp tục lưu thời điểm tuyệt đối theo UTC.
2. Tách helper cho ngày nghiệp vụ và chuyển đổi giờ khởi hành/hạn hủy từ giờ Việt Nam sang UTC; thay các phép so sánh ngày và ràng buộc `today` tương ứng ở tour, khách sạn, lịch trình và báo giá.
3. Đồng bộ min/max ngày trên Blade và trình duyệt với cùng ngày nghiệp vụ; format các thời điểm hiển thị theo giờ Việt Nam.
4. Thêm kiểm thử tại 23:59/00:00/06:59/07:00 giờ Việt Nam, hạn báo giá cuối ngày và lịch khởi hành cùng ngày. Giữ nguyên timestamp UTC đã lưu, không cập nhật hàng loạt dữ liệu cũ.

## Các phần chưa được triển khai đủ theo sơ đồ

- **Voucher/phiếu xác nhận tour riêng:** đã có booking và hóa đơn, chưa có chứng từ riêng với mã, lịch trình, hành khách và điều kiện sử dụng. Kế hoạch: endpoint chỉ chủ đơn đã thanh toán được xem/in; lấy dữ liệu từ đơn/snapshot đã chốt; xác định rõ hiệu lực sau hủy/hoàn tiền.
- **Chứng từ/hóa đơn khách sạn:** đã có HotelPayment và chi tiết đơn, chưa có route để xem/in chứng từ. Kế hoạch: snapshot thông tin khách sạn/phòng, từng đêm, số phòng/khách, số tiền đã thu và hoàn; chỉ chủ đơn được truy cập; không dùng giá tồn phòng hiện tại để tính lại.
- Các dịch vụ riêng ngoài khách sạn và việc hoàn tiền thật qua cổng chưa nằm trong chức năng hiện có. Hoàn tiền mô phỏng không phải bằng chứng nhà cung cấp đã hoàn tiền.

## Kết quả kiểm thử

- SQLite toàn bộ: **214/214 đạt, 6.880 assertions**.
- MySQL 8.4.3: **105/105 đạt, 1.011 assertions**, gồm audit mới, MoMo/VNPAY xác minh chữ ký, SePay, đặt khách sạn, vòng đời booking, thanh toán, báo giá tùy chỉnh và khôi phục tài khoản.
- Bổ sung **28 phương thức kiểm thử mới** trong `CustomerValidationAuditTest` và `CustomerPaymentGatewayValidationTest`; nhiều phương thức chạy ma trận dữ liệu sai/biên.
- Pint và build Vite đạt. Build còn cảnh báo dung lượng bundle bản đồ trên 500 kB đã có ở main.
- Trình duyệt thực ở 1440 px và 390 px: kiểm tra ràng buộc điện thoại/mật khẩu, giấy tờ trùng, yêu thích, thanh toán thất bại → thử lại → thành công, hóa đơn, hủy tour, tìm/giữ/thanh toán/hủy khách sạn, lưu lịch trình/gửi tư vấn và mở/đóng ảnh khách sạn. Các trang đã kiểm tra không tràn ngang.
- Giao dịch SePay Sandbox thực đã được thử thành công trong lần tích hợp main trước đợt này; đợt audit chạy thêm kiểm thử tự động. MoMo/VNPAY trong đợt này dùng khóa kiểm thử cố định và phản hồi có chữ ký trong test, **chưa kiểm chứng kết nối Sandbox thật** vì chưa có cấu hình chạy thật trên máy này.
- Cơ sở dữ liệu kiểm thử: SQLite trong bộ nhớ và `TourDB_codex_test`. Thử giao diện dùng `tour_main_merge_preview`, không chạy reset/migration trên `tour_booking`.
- Báo cáo XML/JSON, ảnh kiểm tra và tài khoản tạo thử chỉ lưu trong `storage/app/private`, không đưa lên Git. Test không gửi email thật.

Các số liệu trên xác nhận những ca đã chạy; lỗi ranh giới UTC/Việt Nam vẫn được ghi nhận riêng, không bị coi là đã sửa chỉ vì bộ kiểm thử hiện tại đạt.

## Chạy lại

```powershell
php vendor/bin/phpunit
$env:DB_PORT='3308' # đổi theo MySQL của máy; giữ nguyên DB test riêng
php vendor/bin/phpunit -c phpunit.mysql.xml --filter='CustomerValidationAuditTest|CustomerPaymentGatewayValidationTest|SePaySandboxTest|HotelBookingTest|BookingLifecycleTest|PaymentWorkflowTest|CustomTourCheckoutTest|CustomerRecoveryTest'
php vendor/bin/pint --dirty
npm run build
```

Các thay đổi của đợt audit được lưu trong commit tiếp theo trên main cục bộ cùng chính sách thanh toán và phần tinh gọn giao diện. Bản tích hợp SePay `1152f4a` được giữ nguyên trong lịch sử. Việc đẩy lên GitHub được thực hiện riêng.

## Thay đổi sau đợt audit: lựa chọn thanh toán tour

Giữ SePay, tiền mặt mô phỏng, thẻ mô phỏng và VietQR demo; chỉ bỏ VNPAY/MoMo theo lựa chọn cuối cùng. Xem chính sách hiện tại tại [SEPAY_SANDBOX.md](SEPAY_SANDBOX.md). Các số liệu kiểm thử phía trên là kết quả đợt audit trước thay đổi này.
