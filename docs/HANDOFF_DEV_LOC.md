# Bàn giao nhánh dev-Loc — 28/10/2026

## Nội dung bàn giao

Nhánh chứa phần kho ảnh Supabase và đợt bổ sung danh mục, khách sạn, tour mới. Database nghiệp vụ vẫn dùng MySQL. Main đã kiểm tra tại commit `58e75be`; trước lần đẩy này, dev-Loc trên GitHub ở `0be8e69` và không có thay đổi từ thành viên khác.

- 31 nhóm điểm đến, 120 địa danh có giới thiệu riêng và 335 ảnh (2–3 ảnh/địa danh), 64 khách sạn/resort/lodge. Vũng Tàu có 4 cơ sở, mỗi điểm đến khác có 2.
- Trang tìm kiếm, bộ lọc, phân trang, chi tiết và liên kết tour theo điểm đến: `/destinations`, `/places`, `/hotels`.
- Thêm hai tour Vũng Tàu 2N1Đ và 3N2Đ, đủ lịch trình và 6 lịch khởi hành tương lai. Tổng bộ mẫu: 32 tour, 88 ngày chương trình, 133 điểm dừng, 126 lịch khởi hành và 64 ảnh tour.
- Seeder/factory cho điểm đến, địa danh, khách sạn, ảnh khách sạn và hai chương trình mới; chạy lại không nhân bản các bản ghi mẫu. Lịch khởi hành neo theo ngày chạy nên có thể thêm đợt mới khi chạy vào ngày khác.
- 72 ảnh danh mục mới được tải về làm nguồn khôi phục. Sau đợt thư viện ảnh, web dùng URL Supabase: 459 tham chiếu nguồn ứng với 438 file theo nội dung. Mở rộng lệnh chuyển/kiểm tra/khôi phục ảnh để quản lý cả ảnh khách sạn.

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

## Sửa tìm kiếm tỉnh từ trang chủ

Nút Tìm hành trình trước đây gửi GET về trang chủ; kết quả lọc nằm dưới phần giới thiệu nên dễ bị hiểu là không có phản hồi. Form đã chuyển sang `/tours`; trang danh sách xử lý và kiểm tra tỉnh, hiển thị tên tỉnh/số kết quả ngay ở đầu trang, giữ tỉnh khi kết hợp từ khóa, sắp xếp và phân trang. Trang tour theo tỉnh cũng gửi bộ lọc về cùng trang kết quả. Xóa bộ lọc đưa về tất cả tour.

Hai kiểm thử HomeSearchTest kiểm tra luồng này, kết hợp từ khóa, tour ẩn/khác tỉnh, trang thứ hai, không có kết quả và tỉnh không hợp lệ. Nhóm kiểm thử liên quan đạt 13/13 trên SQLite (187 assertions), 5/5 trên MySQL (93 assertions). Build giao diện đạt. Trình duyệt đã thử chọn Lâm Đồng từ trang chủ, thấy đúng hai tour trong database demo; sắp xếp giữ tỉnh, tìm không có kết quả hiện thông báo, xóa lọc trở về danh sách chung, mobile không tràn ngang.

## Ràng buộc tỉnh và vùng miền

Chọn tỉnh sẽ tự chọn đúng miền theo dữ liệu tỉnh và khóa ô miền; đổi tỉnh sẽ đổi miền tương ứng. Bỏ chọn tỉnh mở lại ô miền và xóa lựa chọn miền cũ. Giải thích hiển thị ngay dưới ô miền, hoạt động cả khi tải trang đã có tỉnh và khi quay lại trang bằng trình duyệt.

Máy chủ kiểm tra tỉnh/miền cho cả `/tours` và đường dẫn tour theo tỉnh. Nếu URL gửi hai giá trị lệch nhau, web chuyển về miền đúng theo tỉnh và trang đầu, giữ từ khóa/sắp xếp/các bộ lọc khác, đồng thời hiện cảnh báo rõ. Yêu cầu JSON sai lệch trả 422 với lỗi `region`. Tìm riêng theo miền hoặc tìm tỉnh/miền đúng vẫn hoạt động.

Kiểm tra liên quan: 15/15 trên SQLite (215 assertions), 7/7 trên MySQL (121 assertions), build và cú pháp JavaScript đạt. Đã thử trên trình duyệt desktop/mobile: đổi tỉnh, khóa/mở ô miền, URL “Lào Cai + miền Nam” được cảnh báo và sửa về miền Bắc, vẫn có kết quả tìm Sapa.

## Ràng buộc khoảng giá

Đã bỏ hành vi tự đổi chỗ hai mức giá khi nhập ngược. Ô Giá từ/Giá đến có nhãn rõ ràng, báo lỗi đỏ ngay khi nhập, ngăn tìm kiếm nếu giá đến nhỏ hơn giá từ. Sửa giá sẽ tự xóa lỗi. Giá bằng nhau, bằng 0 hoặc chỉ nhập một đầu vẫn hợp lệ; không chấp nhận giá âm, chữ hoặc giá vượt giới hạn 13 chữ số nguyên của cột giá tour.

Máy chủ kiểm tra lại cả khi JavaScript không chạy hoặc URL bị sửa. Khi lỗi, web bỏ hai giá khỏi URL kết quả để tránh vòng lặp chuyển hướng, giữ nguyên giá nhập trong form cùng tỉnh/từ khóa/sắp xếp và hiện lỗi; JSON trả 422 cho trường sai. Kiểm thử cũ kỳ vọng tự đảo khoảng giá đã được cập nhật theo yêu cầu mới.

Đã thử trên trình duyệt đúng ví dụ 5.000.000 → 111.111, chặn gửi form, sửa thì hết lỗi, khoảng bằng nhau/một đầu, giá âm/chữ và giữ lại dữ liệu sau lỗi máy chủ; desktop/mobile không tràn ngang. MySQL: 11/11 kiểm thử liên quan đạt, 166 assertions. Toàn bộ SQLite: 68/76 đạt, 8 trường hợp lỗi cũ trùng báo cáo trước, không có tên kiểm thử thất bại mới. Build, kiểm tra cú pháp JavaScript và diff đạt.

## Bổ sung giới thiệu và thư viện ảnh địa danh

- Thay 112 đoạn mô tả chung bằng giới thiệu riêng; giữ 8 giới thiệu Vũng Tàu đã có. Bổ sung 215 ảnh thực tế cho toàn bộ 120 địa danh, tổng 335 ảnh. Snapshot mới: `database/data/place-galleries.json`, seeder: `PlaceGallerySeeder`.
- Bấm ảnh để phóng to, dùng Trước/Sau hoặc phím mũi tên; Escape, nút Đóng hoặc nền ngoài đóng ảnh và trả focus về ảnh đã chọn. Dùng được trên điện thoại. Ảnh trong lịch tour/lịch riêng cũng có thể phóng to. Không thêm phần nguồn/quyền ảnh trên giao diện.
- 25 kiểm thử liên quan đạt trên MySQL (2.931 assertions); toàn bộ SQLite có 70/78 đạt (5.344 assertions). 8 lỗi còn lại trùng bộ lỗi cũ, không có trường hợp lỗi mới. Kiểm thử mới xác nhận đủ địa danh, checksum/file ảnh, tính lặp lại của seeder và giữ nguyên ảnh đại diện/lịch trình.
- Hướng dẫn cập nhật: [PLACE_GALLERIES.md](PLACE_GALLERIES.md). Chạy chuyển ảnh trước khi chạy seeder; không reset database đang có đơn.

Nghiệm thu thư viện ảnh trên máy demo: đủ 459 mapping/438 object đã tải công khai và so SHA-256 khi chuyển; `media:verify` xác nhận không còn tham chiếu local; audit 32 tour/88 ngày/133 điểm dừng/335 ảnh không có vấn đề. Snapshot dữ liệu trước/sau xác nhận giữ nguyên tour, đơn, hành khách, thanh toán, lịch khởi hành và lịch chương trình đã có. Trình duyệt kiểm tra Phong Nha, Cầu Rồng, chợ Sa Pa, khách sạn Pullman và ảnh trong lịch tour; mở/đóng/chuyển ảnh, phím mũi tên/Escape, focus, URL cloud và bố cục 390px đạt. Web demo ở cổng 8001.

## Thanh toán QR demo

Thêm luồng VietQR mô phỏng dành cho trình diễn học tập: QR SVG riêng theo đơn/số tiền sau giảm giá, thời hạn 10 phút, chờ kết quả rồi mới ghi payment/xác nhận booking/tạo hóa đơn. Tab ngân hàng mô phỏng có ba kết quả thành công, thất bại và hết hạn. Có polling trạng thái, tạo lại mã, tải QR và tiếp tục QR từ chi tiết đơn. Dùng BIN/tài khoản giả, không kết nối hoặc chuyển tiền qua ngân hàng thật. Flag `PAYMENT_DEMO_ENABLED` mặc định tắt và bị chặn trong production.

Chạy `composer install`, `php artisan migrate`, `npm install` và `npm run build`; môi trường local bật flag theo [QR_PAYMENT_DEMO.md](QR_PAYMENT_DEMO.md). Đã thêm bảng `payment_intents`, không reset bảng cũ. Thêm BaconQrCode 3.1.1, dasprid/enum và yêu cầu PHP XMLWriter.

13 kiểm thử liên quan thanh toán đạt trên MySQL (176 assertions). Toàn bộ SQLite có 82/90 đạt (5.512 assertions), 8 trường hợp lỗi trùng baseline trước đợt QR. Trình duyệt kiểm tra trên database riêng `tour_qr_demo_preview`: tạo đơn qua luồng web, giải mã ảnh SVG bằng công cụ độc lập, chờ thanh toán, kết quả thành công tự cập nhật, kết quả lặp, chỉ phát hành hóa đơn sau thành công, thất bại/hết hạn/tạo lại QR, CSRF, quyền chủ đơn, lỗi mạng và bố cục điện thoại. Web chính cổng 8001 đã bật demo và migration mới; dữ liệu tour/booking đã có được đối chiếu không thay đổi.


## SePay Sandbox và QR mở thanh toán trên Android

Đã bổ sung cổng SePay Sandbox với HMAC checkout, callback IPN, QR liên kết điện thoại có chữ ký/hạn dùng, cập nhật trạng thái tự động và hóa đơn demo. Luồng đã nhận IPN thật từ SePay Sandbox trên MySQL kiểm thử. Cấu hình và kịch bản trình diễn: [SEPAY_SANDBOX.md](SEPAY_SANDBOX.md). Không gửi `.env` hoặc các khóa tích hợp vào Git.
