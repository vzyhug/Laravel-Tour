# Bàn giao nhánh dev-Loc — 28/10/2026

## Nội dung bàn giao

Nhánh chứa phần kho ảnh Supabase và đợt bổ sung danh mục, khách sạn, tour mới. Database nghiệp vụ vẫn dùng MySQL. Main đã kiểm tra tại commit `58e75be`; trước lần đẩy này, dev-Loc trên GitHub ở `0be8e69` và không có thay đổi từ thành viên khác.

- 31 nhóm điểm đến, 120 địa danh có ảnh và 64 khách sạn/resort/lodge. Vũng Tàu có 4 cơ sở, mỗi điểm đến khác có 2.
- Trang tìm kiếm, bộ lọc, phân trang, chi tiết và liên kết tour theo điểm đến: `/destinations`, `/places`, `/hotels`.
- Thêm hai tour Vũng Tàu 2N1Đ và 3N2Đ, đủ lịch trình và 6 lịch khởi hành tương lai. Tổng bộ mẫu: 32 tour, 88 ngày chương trình, 133 điểm dừng, 126 lịch khởi hành và 64 ảnh tour.
- Seeder/factory cho điểm đến, địa danh, khách sạn, ảnh khách sạn và hai chương trình mới; chạy lại không nhân bản các bản ghi mẫu. Lịch khởi hành neo theo ngày chạy nên có thể thêm đợt mới khi chạy vào ngày khác.
- 72 ảnh danh mục mới được tải về làm nguồn khôi phục. Web dùng URL Supabase: 244 tham chiếu nguồn ứng với 224 file theo nội dung. Mở rộng lệnh chuyển/kiểm tra/khôi phục ảnh để quản lý cả ảnh khách sạn.

## Cập nhật máy đã có dữ liệu tour cũ

Sao lưu database trước khi cập nhật. Lấy code nhánh dev-Loc hoặc merge nhánh này vào main theo quy trình của nhóm, sau đó:

```sh
composer install
npm ci
npm run build
php artisan config:clear
php artisan migrate
php artisan db:seed --class=DestinationDirectorySeeder
php artisan db:seed --class=DestinationTourSeeder
php artisan media:verify
php artisan itineraries:audit
```

Không chạy `migrate:fresh` trên database cần giữ. Hai seeder trên cần dữ liệu tour/lịch trình gốc đã có. Với database mới, cấu hình trước rồi chạy `php artisan migrate --seed`. Không cần chạy lại TourSeeder/DemoBookingSeeder chỉ để bổ sung danh mục và hai tour mới.

Cấu hình `.env` riêng của từng máy:

```dotenv
MEDIA_DRIVER=supabase
SUPABASE_URL=https://tgftmvgboogmmhzmafdc.supabase.co
SUPABASE_STORAGE_BUCKET=tour-images
SUPABASE_SECRET_KEY=
MEDIA_CA_BUNDLE=
```

Điền Secret của project bằng kênh riêng khi cần upload; tuyệt đối không đưa lên Git, frontend hoặc tài liệu. DB_* dùng MySQL của máy đích. Manifest công khai đã nằm trong code; dùng đúng project/bucket ở trên để dùng chung ảnh. Nếu chuyển sang project khác, cần quy trình chuyển ảnh và tạo manifest riêng cho môi trường mới; không chỉ đổi URL/bucket trong `.env` vì công cụ sẽ từ chối manifest hoặc bản ghi ảnh thuộc project cũ. Xem quy trình chuyển/khôi phục và CA trên Windows trong [SUPABASE_STORAGE.md](SUPABASE_STORAGE.md).

Máy demo hiện tại dùng MySQL `127.0.0.1:3308`, database `tour_booking`; website `http://127.0.0.1:8001`. Đây là cấu hình máy phát triển, không phải yêu cầu cổng/database cho mọi máy.

## Kết quả kiểm tra cuối

- 33 kiểm thử liên quan đạt trên SQLite và MySQL 8.4.3, 439 assertions mỗi hệ quản trị; MySQL kiểm thử dùng database riêng `TourDB_codex_test`, cổng máy demo 3308.
- Toàn bộ bộ kiểm thử: 61/69 đạt. 8 trường hợp thất bại đã tồn tại trên main `58e75be`, không có tên kiểm thử thất bại mới. Các trường hợp này liên quan logic trẻ em/giá và dữ liệu validation đặt tour cũ; nhánh này chưa sửa các nghiệp vụ đó.
- Pint, build giao diện và kiểm tra diff đạt. Kiểm tra SHA-256 đủ 72 ảnh mới, không có khóa thật trong các file dự kiến đưa lên Git.
- Các trang chính, hai tour mới và trang nguồn ảnh trả HTTP 200, tất cả ảnh hiển thị dùng Supabase. Kiểm tra dữ liệu cũ xác nhận không đổi 30 tour gốc và dữ liệu đặt tour đã có.
- `media:verify` xác nhận không còn tham chiếu local; `itineraries:audit` không có vấn đề ở 32 tour. Lệnh verify mặc định kiểm tra tham chiếu/metadata; khi cần nghiệm thu toàn bộ byte trên cloud, dùng `php artisan media:verify --remote` (tải và so hash).

Tám kiểm thử cũ cần nhóm theo dõi riêng:

1. `BranchIntegrationTest::test_new_ui_preserves_coupon_pickup_payment_invoice_and_cancellation_flow`
2. `BranchIntegrationTest::test_invalid_child_dates_and_adult_identity_return_validation_errors_without_a_500`
3. `DemoDataTest::test_seed_builds_complete_bookable_tours_without_network_access`
4. `DemoDataTest::test_factory_overrides_keep_dates_prices_and_passenger_counts_consistent`
5. `InputValidationBugTest::test_booking_child_passenger_requires_date_of_birth`
6. `InputValidationBugTest::test_admin_departure_rejects_child_price_greater_than_adult_price`
7. `TravelPortalTest::test_booking_validation_restores_each_passenger_and_the_estimated_cost`
8. `BookingTourTest::test_customer_can_book_available_departure`

## Giới hạn cần nắm khi nghiệm thu

- Đặt tour chưa có lựa chọn khách sạn; danh mục chỉ phục vụ tra cứu. Muốn thêm cần cấu hình khách sạn cho tour, phụ thu/số đêm và lưu lựa chọn/giá vào đơn đặt.
- Giá của hai tour mới là giá mẫu kiểm thử, chưa phải báo giá thương mại.
- Nhóm điểm đến là nhóm hành trình, không phải đơn vị hành chính. Khách sạn cùng nhóm không bảo đảm ở gần mọi địa danh hoặc có phòng trống.
- Secret, `.env`, database, bản sao lưu và báo cáo nội bộ không được đưa lên Git. Ảnh nguồn được giữ để có thể khôi phục/chuyển kho; ảnh ứng dụng hiển thị từ Supabase.

Đợt sửa giao diện sau bàn giao: bỏ phần nguồn/quyền ảnh trên web và dữ liệu trả cho trình chỉnh lịch; gỡ trang ghi công. Sửa các chuỗi tiếng Việt bị chuyển sai bảng mã trong `resources/js/itinerary.js` (24 dòng bị ảnh hưởng trước sửa), thêm nhãn “Mã đặt tour” ở danh sách, chi tiết và thanh toán. Mã BK... là booking_code, không phải mã lịch trình.

Sau đợt sửa này, 40 kiểm thử liên quan trên MySQL đạt (1.026 assertions). Toàn bộ 69 kiểm thử SQLite vẫn có 61 đạt, 8 trường hợp lỗi cũ, không có trường hợp lỗi mới. Build và kiểm tra cú pháp JavaScript đạt. Rà soát các tệp văn bản mã nguồn/dữ liệu được Git theo dõi không tìm thấy chuỗi sai bảng mã tương tự. Trình duyệt đã kiểm tra trang địa danh, khách sạn, tour, tài khoản và chi tiết đơn; các nhãn tiếng Việt và trạng thái lưu/gửi tư vấn hiển thị đúng, bố cục không tràn ngang trên desktop/mobile. Kiểm tra thông báo bằng phản hồi giả lập trong trình duyệt, không tạo yêu cầu tư vấn thật hoặc sửa lịch riêng đang lưu.

Chi tiết dữ liệu: [DESTINATION_DIRECTORY.md](DESTINATION_DIRECTORY.md) và [DESTINATION_TOURS.md](DESTINATION_TOURS.md).
