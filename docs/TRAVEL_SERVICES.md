# VietGo: dịch vụ du lịch mở rộng

Ngày 23/10/2026. **[Đề xuất đã triển khai cho VietGo]** theo skill `design-traveloka-platform`. Đây là nghiệp vụ của đồ án; không phải API hoặc chính sách nội bộ Traveloka. Catalog, giá, thanh toán, xác nhận nhà cung cấp, hoàn tiền, cọc và chứng từ đều là **demo**. Ảnh địa danh có sẵn chỉ minh họa điểm đến.

## Chạy trên dự án đang có dữ liệu

Bật MySQL trong Laragon, kiểm tra cấu hình database trong `.env`, rồi chạy:

```powershell
composer install
npm.cmd install
php artisan migrate
php artisan db:seed --class=TravelServiceCatalogSeeder
php artisan db:seed --class=TravelServiceDemoBookingSeeder
npm.cmd run build
php artisan serve
```

Terminal khác chạy `php artisan schedule:work`. Scheduler dọn giữ chỗ tour và dịch vụ mới mỗi phút. Chạy thủ công bằng `php artisan bookings:expire` và `php artisan services:expire`.

Không dùng `migrate:fresh` hoặc đổi `APP_KEY` trên database có dữ liệu. `configuration`, người liên hệ và người sử dụng được mã hóa bằng application key. Cài mới hoàn toàn theo README (`migrate --seed`); tài khoản khách `demo01@vietgo.test` / `Customer@123`, Admin `admin` / `Admin@123`, Sale `sale_linh` / `Sale@123`.

## Chức năng và quy tắc

Tên model dùng theo nghiệp vụ: `Supplier`, `TravelService`, `ServiceResource`, `ServicePackage`, `ServiceInventoryDay`, `BusTrip`, `BusTripStop`, `ServiceQuote`, `ServiceBooking`, `ServiceAllocation`, `ServicePayment`, `ServicePaymentEvent`, `ServiceRefund`, `ServiceTransaction`, `ServiceVoucher`, `ServiceBookingEvent`, `VehicleInspection`.

Migration `2026_10_23_100002_rename_travel_service_tables` đổi tên các bảng cũ sang tên nghiệp vụ, giữ nguyên dữ liệu và khóa ngoại. Hai migration đã chạy trước đó giữ nguyên tên/nội dung để bảo toàn lịch sử nâng cấp; khi cài mới, Laravel tạo schema cũ rồi thực hiện bước đổi tên. URL khách hàng và quản trị vẫn giữ nguyên, tên route dùng `services.*` và `admin.services.*`.

| Dịch vụ | Cấu hình và tính giá | Tồn/sử dụng |
|---|---|---|
| Lưu trú | Ngày nhận/trả, số phòng, người lớn/trẻ em; tổng từng đêm × số phòng; phụ thu theo lịch | Các gói dùng chung quỹ phòng; đủ tồn mọi đêm; kiểm sức chứa từng phòng và tuổi trẻ em |
| Xe khách | Chuyến, điểm lên/xuống, nhãn ghế; giá theo số đoạn × ghế | Ghế trùng đoạn bị chặn; có thể bán lại ghế trên đoạn kế tiếp; ngày đến hiển thị cả chuyến qua đêm |
| Đưa đón | Chiều, giờ đón, sân bay, vùng/địa chỉ, người/hành lý; giá gói + phụ phí vùng | Quỹ xe theo khoảng giờ + buffer; không vượt người/hành lý của gói |
| Thuê xe | Tự lái/có tài xế; nhận/trả, số người; làm tròn lên mỗi 24 giờ | Mỗi xe là tài nguyên riêng, khoảng thuê + buffer; tự lái kiểm tuổi/GPLX; biên bản nhận/trả, cọc ghi riêng |
| Hoạt động | Ngày, slot, người lớn/trẻ em; đơn giá từng nhóm | Sức chứa theo slot; kiểm tuổi tại ngày dùng; ghi nhận một/nhiều lượt, chống ghi trùng |

Tiền là số nguyên VND; lịch đầu vào/hiển thị Asia/Ho_Chi_Minh (UTC+7), thời điểm giao dịch lưu UTC. Báo giá sống 10 phút, giữ chỗ 15 phút. Chưa tạo đơn thì chưa giữ tồn. Server kiểm lại giá, policy, cấu hình và tồn trong transaction khi đặt; thay đổi báo giá yêu cầu khách xác nhận lại. Thiếu ngày tồn không được mặc định còn hàng.

Đơn có snapshot giá/chính sách và danh sách người sử dụng độc lập người đặt. Khóa customer, quote, resource và ràng buộc duy nhất ngăn retry tạo đơn trùng. Tài nguyên dùng chung giữa các gói; cập nhật giá không đổi đơn đã chốt. Ngày đóng bán ngăn đơn mới, không tự hủy đơn đã xác nhận.

## Trạng thái, thanh toán và hậu mãi

- Reservation: `held → confirmed / confirming / expired / cancelled`; đơn xác nhận có thể `cancellation_pending → cancelled` hoặc bị từ chối trở lại `confirmed`; sử dụng xong `completed`.
- Payment riêng: `unpaid / pending / failed / captured / partially_refunded / refunded`. Mỗi lần thử có UUID riêng và số tiền lấy từ server.
- Fulfillment riêng: chưa cấp, đã cấp, đang sử dụng, đã dùng hoặc vô hiệu. Chỉ cấp khi thu tiền và nhà cung cấp demo xác nhận thành công. Kết quả chưa rõ giữ trạng thái xác nhận; thất bại tạo khoản hoàn bù.
- Giữ chỗ hết hạn trả tồn đúng một lần. Tiền về muộn vẫn được ghi nhận và tạo hoàn bù, không cấp lại dịch vụ hết hạn. Callback lặp hoặc sai thứ tự không cấp chứng từ/thu tiền hai lần. Hai lần thu hợp lệ khác nhau được ghi sổ và đánh dấu thu thừa để Admin hoàn phần dư.
- Hủy toàn đơn theo snapshot, thời hạn và tỷ lệ hoàn của gói. Đơn đã trả cần duyệt hủy rồi xử lý hoàn riêng; phê duyệt không đồng nghĩa đã hoàn tiền. Khi duyệt hủy mới trả tồn/vô hiệu chứng từ. Refund không vượt số đã thu còn lại.
- Rental: cọc là ledger riêng khỏi doanh thu thuê. Biên bản nhận/trả kiểm thời gian, công-tơ-mét và tình trạng; trả xe giữ buffer vệ sinh. Trả muộn đụng lịch tiếp theo khóa bán tài nguyên và đánh dấu nhân viên kiểm tra.
- Chứng từ và biên nhận yêu cầu đúng chủ đơn. Giấy tờ không nằm trên chứng từ hoặc lịch sử sự kiện; GPLX trên trang đơn chỉ hiện bốn ký tự cuối.

Admin/Sale quản trị catalog, lịch, chuyến, xác nhận/duyệt hủy và sử dụng. **Chỉ Admin** xử lý hoàn dịch vụ/thu thừa. Guide không có quyền module mới. Chưa có hoàn từng khách/chặng, đổi lịch, tài khoản đối tác, PSP/supplier thật, email queue hay xử lý tự động phụ phí/hư hỏng xe. Coupon tour hiện hữu không áp dụng sang các dịch vụ mới.

## Các đường dẫn

| Route | Mục đích |
|---|---|
| `/services/stay`, `/services/bus`, `/services/transfer`, `/services/rental`, `/services/activity` | Danh sách/tìm dịch vụ |
| `/service-products/{id}` | Chi tiết và cấu hình gói |
| `POST /service-options/{id}/quotes` | Tạo báo giá khi đã đăng nhập |
| `/service-quotes/{token}` | Xác nhận, nhập liên hệ/người dùng; POST `/{token}/orders` tạo đơn |
| `/account/services` | Lịch sử các dịch vụ mới; lịch sử tour vẫn ở tài khoản cũ |
| `/account/services/{reference}` | Thanh toán/hủy, trạng thái, chứng từ, sự kiện |
| `/account/services/{reference}/documents/{voucher\|receipt}` | Chứng từ/biên nhận demo riêng tư |
| `/service-trips/{id}/seats` | Ghế trống/đang giữ/đã bán theo hai stop ID, không trả PII |
| `/admin/services/products` | Nhà cung cấp, sản phẩm, tài nguyên, gói, lịch, chuyến/trạm |
| `/admin/services/orders` | Tra cứu đơn và thao tác vận hành |

Luồng quản trị: tạo supplier → sản phẩm đúng loại → tài nguyên → gói và chính sách → lịch tồn; xe khách cần thêm chuyến với trạm tăng dần. Xe thuê dùng capacity 1, các xe khác nhau tạo tài nguyên khác nhau. Trạm nhập từng dòng `Tên|Địa chỉ|YYYY-MM-DDTHH:mm`; vùng đưa đón `Tên vùng|Phụ phí VND`. Calendar nhập ngày đầu/cuối, sức chứa và điều chỉnh giá; không được giảm dưới lượng đang giữ/xác nhận.

## Seed và factory

`TravelServiceCatalogSeeder`: 13 sản phẩm (3 lưu trú, 2 xe khách, 2 đưa đón, 3 thuê xe, 3 hoạt động), gói giá/chính sách, lịch 91 ngày và chuyến xe 28 ngày. Dùng `firstOrCreate`/`insertOrIgnore`, không ghi đè chỉnh sửa của quản trị khi chạy lặp. Chạy lại bổ sung lịch cho các ngày mới, không xóa lịch sử.

`TravelServiceDemoBookingSeeder`: 9 mã đơn cố định cho giữ chỗ, đã trả, payment pending, supplier unknown/failed và yêu cầu hủy. Dùng dịch vụ đặt/thanh toán thật của ứng dụng ở chế độ mô phỏng, không sửa đơn có sẵn. Cần khách demo có sẵn; gói thiếu tồn hoặc đã sửa không còn hợp lệ sẽ được bỏ qua và báo ra terminal.

Các model dịch vụ du lịch có factory. Ví dụ trong Tinker hoặc test database:

```php
$order = App\Models\ServiceBooking::factory()->paid()->create();
$resource = App\Models\ServiceResource::factory()->rental()->create();
$option = App\Models\ServicePackage::factory()->for($resource, 'resource')->create();
```

Factory tài nguyên tạo lịch cơ bản; trạng thái `bus()`, `rental()`, `transfer()`, `activity()` có trên resource/product factory. Order factory tạo đơn/allocations hợp lệ; `paid()` chạy service payment để có ledger/voucher tương ứng. Factory riêng cho ledger/refund/voucher là fixture cấp thấp, không thay thế checkout khi dựng kịch bản nghiệp vụ. Không chạy factory ngẫu nhiên trực tiếp trên database thật.

## Callback demo và kiểm thử

`POST /demo/provider/callback` nhận JSON: `event_id` UUID, `payment_reference`, `amount` nguyên VND, `currency=VND`, `result=success|failed`, `supplier_result=success|failed|unknown`, `occurred_at` ISO8601. Header `X-Demo-Signature` là HMAC SHA256 dạng hex của **raw body** với `config('travel_services.demo_callback_secret')`. Có thể cấu hình `TRAVEL_SERVICES_DEMO_CALLBACK_SECRET`; mặc định suy ra từ APP_KEY riêng cho demo. Không gửi khóa ra frontend. Chỉ endpoint này được miễn CSRF, vẫn kiểm chữ ký và rate limit. Đây không phải hợp đồng webhook của cổng thanh toán thật.

```powershell
php artisan test --filter=TravelServicesTest
php artisan test
npm.cmd run build
```

Kiểm thử bao gồm tồn theo đêm/đoạn/slot/khoảng giờ, nhóm tuổi/phòng/GPLX, mã hóa, retry, báo giá đổi/hết hạn, callback sai tiền/tiền tệ/chữ ký/lặp, thu muộn/thu thừa, xác nhận chưa rõ, hủy/hoàn, cọc, quyền và seed lặp. SQLite kiểm hành vi, không chứng minh tải đồng thời; MySQL dùng khóa tài nguyên để tuần tự hóa đặt chỗ. Tích hợp supplier/PSP thật cần hợp đồng, đối soát và kiểm thử riêng.
