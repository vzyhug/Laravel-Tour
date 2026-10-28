# Dữ liệu mẫu VietGo

## Chạy seeder

Sau khi cài dependency và cấu hình `.env` cho database phát triển:

```bash
php artisan migrate --seed
```

Cập nhật mã nguồn trên database đã tồn tại:

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` chạy ba seeder trong một transaction, theo thứ tự:

1. `DemoAccountSeeder`: vai trò, nhân viên và khách hàng.
2. `TourSeeder`: tour, loại tour, tỉnh/thành, ảnh, lịch trình và lịch khởi hành.
3. `DemoBookingSeeder`: đơn, hành khách, thanh toán, hóa đơn và đánh giá.

Có thể chạy riêng từng lớp bằng `php artisan db:seed --class=TourSeeder` khi dữ liệu phụ thuộc đã tồn tại. Factory dùng Faker trong dependency phát triển; cài `composer install` với dependency phát triển để chạy seeder.

## Số lượng trên database mới

| Dữ liệu | Số lượng |
| --- | ---: |
| Vai trò | 3 |
| Nhân viên | 11: 1 Admin, 2 Sale, 8 Guide |
| Khách hàng | 24 |
| Loại tour | 5 |
| Tỉnh/thành | 26 |
| Tour | 30 |
| Ảnh tour | 60, hai ảnh mỗi tour |
| Ngày lịch trình | 83 |
| Lịch khởi hành | 120, mỗi tour có ba đợt sắp tới và một đợt đã kết thúc |
| Đơn đặt tour | 120: 30 pending, 30 confirmed, 30 cancelled, 30 completed |
| Hành khách | 300 |
| Thanh toán | 60 |
| Hóa đơn | 60 |
| Đánh giá | 30 |

Năm tour cũ được bổ sung nội dung: Hạ Long, Sa Pa, Đà Nẵng–Hội An–Bà Nà, Đà Lạt và Phú Quốc. Mười tour bổ sung ban đầu: Ninh Bình, Hà Giang, Hà Nội, Huế, Nha Trang, Quy Nhơn, Cần Thơ, Côn Đảo, Phong Nha và Mũi Né.

Đợt bổ sung tiếp theo có 15 tour: Cao Bằng, Ba Bể, Mộc Châu, Mai Châu, Mù Cang Chải, Điện Biên, Cát Bà, Pù Luông, Lý Sơn, Măng Đen, Buôn Ma Thuột, Cúc Phương, Tây Ninh, Bến Tre và Cà Mau.

Danh mục nội dung được lưu ở `database/data/tours.json`. Các địa danh như Hà Giang, Quy Nhơn, Phong Nha và Mũi Né là tên điểm du lịch; trường tỉnh/thành dùng Tuyên Quang, Gia Lai, Quảng Trị và Lâm Đồng.

Các ngày khởi hành tính từ ngày chạy seeder. Tám hướng dẫn viên được phân công các lịch không chồng lấn trên database mới. Mỗi nhóm 16 tour dùng bốn hướng dẫn viên theo username cố định; việc bổ sung nhóm mới giữ nguyên hướng dẫn viên và ngày khởi hành của 15 tour ban đầu. Tiền đơn tính theo giá người lớn, trẻ em và số hành khách; đơn hủy không giữ chỗ. Đơn đã hoàn thành dùng lịch quá khứ, ngày đặt trước ngày đi, ngày đánh giá sau khi về. Đơn confirmed/completed có thanh toán và hóa đơn tương ứng.

Khi chạy lại cùng ngày, các bản ghi được nhận diện theo username, email, tên tour, số ngày lịch trình, ngày khởi hành và mã đơn cố định. ID, mật khẩu tài khoản đã có, hành khách, thanh toán và số chỗ đã giữ được bảo toàn. Nội dung của các tour mẫu và lịch trình được đồng bộ theo danh mục. Ảnh cũ được giữ trong gallery, ảnh mẫu đầu tiên trở thành ảnh đại diện. Nếu đã có năm tour cũ và ảnh cũ, tổng ảnh trong database có thể lớn hơn 60.

Khi seed vào ngày khác, seeder bổ sung lịch theo ngày mới; các lịch và đơn cũ tiếp tục được giữ. Các mã đơn `DEMO-<địa-danh>-<loại>` cố định để không nhân đôi đơn mẫu.

## Tài khoản demo

| Loại | Tài khoản | Mật khẩu | Trang đăng nhập |
| --- | --- | --- | --- |
| Admin | `admin` | `Admin@123` | `/login` |
| Sale | `sale_linh`, `sale_nam` | `Sale@123` | `/login` |
| Guide | `guide`, `guide_tuan`, `guide_huong`, `guide_long`, `guide_phuong`, `guide_hai`, `guide_mai`, `guide_bao` | `Guide@123` | `/login` |
| Khách hàng | `demo01@vietgo.test` đến `demo24@vietgo.test` | `Customer@123` | `/login` |

Các khách hàng đầu danh sách có đơn mẫu để xem lịch sử, thanh toán và hóa đơn.

Tất cả tài khoản dùng trang đăng nhập chung `/login`: nhập email của khách hàng hoặc username/email của nhân viên. Trường gửi lên là `login`; tài khoản nhân viên được chuyển tới `/admin/dashboard`.

## Dùng factory

Có factory cho `Role`, `Employee`, `Customer`, `Province`, `TourCategory`, `Tour`, `TourImage`, `TourSchedule`, `TourDeparture`, `Booking`, `BookingPassenger`, `Payment`, `Bill`, `Review`. Các model đều hỗ trợ `Model::factory()` và tạo các quan hệ phụ thuộc khi cần.

Ví dụ trong Tinker hoặc test:

```php
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourImage;

Customer::factory()->count(10)->create();

$tour = Tour::factory()
    ->has(TourImage::factory()->thumbnail(), 'images')
    ->has(TourDeparture::factory()->count(3), 'departures')
    ->create();

$departure = TourDeparture::factory()->for($tour, 'tour')->create([
    'adult_price' => 3000000,
    'max_seats' => 24,
]);

$booking = Booking::factory()
    ->for($departure, 'departure')
    ->withPassengers()
    ->create(['total_adults' => 2, 'total_children' => 1]);
```

Các state: `Employee::factory()->admin()` / `guide()`, `TourDeparture::factory()->past()` / `full()` / `cancelled()`, `Booking::factory()->confirmed()` / `cancelled()` / `completed()`, `BookingPassenger::factory()->child()`, `TourImage::factory()->thumbnail()`.

Factory tính ngày về từ số ngày tour, giá trẻ em từ giá người lớn và tổng tiền đơn từ giá lịch khởi hành. `Booking::factory()->completed()` tạo lịch đã kết thúc. `withPassengers()` tạo đủ người lớn và trẻ em theo số lượng đơn. Việc giữ chỗ trong luồng đặt tour được thực hiện qua `App\Actions\CreateBooking`; factory phục vụ tạo bản ghi kiểm thử. Seeder xử lý giữ chỗ cho các đơn mẫu trong transaction.

`TourImageFactory` chọn ảnh theo tên tour trong danh mục. Khi tạo ảnh cho tour có tên tùy chỉnh, truyền `img_url` của ảnh tương ứng. Khi tạo nhiều ngày lịch trình cho cùng một tour, dùng `sequence()` để cấp `day_number` riêng.

## Ảnh thực tế và tải lại

Mặc định ảnh được tham chiếu từ Supabase Storage chung. Hoàn tất [cấu hình và chuyển ảnh](SUPABASE_STORAGE.md) trước khi seed lần đầu. Seeder chỉ đọc manifest cloud, không gọi mạng hoặc upload; máy mới không cần các file ảnh local.

Các file trong `public/images/tours/demo/` là nguồn để chuyển và khôi phục trong giai đoạn chuyển đổi. `MEDIA_DRIVER=local` chỉ dùng khi phát triển offline/kiểm thử; production bắt buộc Supabase. Các script bên dưới chỉ tải lại bộ nguồn, không quyết định nơi ứng dụng lưu ảnh upload.

- Danh sách Wikimedia được chọn: `database/data/tour-image-sources.json`.
- Nguồn, tác giả, giấy phép, mô tả và SHA-256 từng ảnh: `database/data/tour-images.json`.
- Giao diện học tập hiển thị ảnh bằng URL cloud; phần nguồn/quyền ảnh và trang ghi công đã được gỡ khỏi website.
- Ghi công dễ đọc: `public/images/tours/demo/ATTRIBUTION.md`.

Tải lại bằng Python 3, chỉ dùng thư viện chuẩn:

```bash
python -X utf8 scripts/download_tour_images.py
```

Ảnh đã tồn tại với đúng SHA-256 được bỏ qua. Dùng `--discover` để cập nhật metadata từ Wikimedia Commons theo danh sách file đã chọn:

```bash
python -X utf8 scripts/download_tour_images.py --discover
```

Công cụ kiểm tra chữ ký JPEG/PNG trước khi lưu, giữ thông tin tác giả và giấy phép. Ảnh được Wikimedia thu nhỏ; không có chỉnh sửa khác. Giữ thông tin ghi công và liên kết giấy phép tương ứng khi phân phối lại bộ ảnh. Nguồn được chọn theo địa danh, không dùng kết quả tìm kiếm ngẫu nhiên hay ảnh AI.

## Kiểm tra

```bash
php artisan test
php vendor/bin/pint --test app/Models database/factories database/seeders tests/Feature/DemoDataTest.php
```

`DemoDataTest` kiểm tra số lượng, quan hệ, ngày tháng, tiền và hành khách, số chỗ, lịch hướng dẫn viên, khả năng chạy lại khi có thêm đơn từ luồng đặt tour, các factory độc lập, chữ ký ảnh và SHA-256. Test dùng SQLite trong bộ nhớ theo `phpunit.xml`.


## Kiểm thử trên MySQL

Có cấu hình riêng `phpunit.mysql.xml`, chạy lại cùng bộ kiểm thử với MySQL. Cấu hình này cố định database `TourDB_codex_test` và tắt `DB_URL`; `tests/mysql-bootstrap.php` kiểm tra lại trước khi test chạy. `RefreshDatabase` có thể dựng lại các bảng trong database kiểm thử này.

Trên MySQL dành cho phát triển, tạo database kiểm thử một lần:

```sql
CREATE DATABASE TourDB_codex_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Mặc định cấu hình test kết nối `127.0.0.1:3307`, tài khoản `root`. Có thể đặt `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` qua biến môi trường cho server đang sử dụng. Ví dụ PowerShell khi Laragon chạy MySQL ở cổng 3306:

```powershell
$env:DB_PORT = '3306'
php vendor/bin/phpunit --configuration phpunit.mysql.xml
Remove-Item Env:DB_PORT
```

Chạy trực tiếp PHPUnit như trên để cấu hình MySQL được nạp trước cấu hình ứng dụng. File `.env` của ứng dụng vẫn giữ kết nối hiện tại; kiểm thử MySQL không yêu cầu đổi sang MySQL trong `.env`.

Đã kiểm tra ngày 20/10/2026 với MySQL 8.4.3 thực, chế độ strict, `utf8mb4_unicode_ci`, trên phiên riêng `127.0.0.1:3307`:

- Toàn bộ 15 kiểm thử đạt, 2.305 phép kiểm tra.
- `migrate --seed` và lần `db:seed` tiếp theo trên `TourDB_codex_demo` thành công; nội dung của cả 14 bảng nghiệp vụ không đổi sau lần seed lại.
- Đủ 30 tour, 60 ảnh, 83 ngày lịch trình, 120 lịch khởi hành, 24 khách hàng, 120 đơn, 300 hành khách, 60 thanh toán, 60 hóa đơn và 30 đánh giá.
- Có 19 ràng buộc khóa ngoại (gồm hai khóa của bảng favorites); số lịch có số chỗ không khớp đơn là 0; chuỗi tiếng Việt được lưu và đọc lại đúng.

Phiên MySQL kiểm tra sử dụng thư mục dữ liệu riêng `.mysql-sandbox/` (đã bỏ qua trong Git), tách khỏi database `TourDB` của Laragon. Sau kiểm thử, phiên MySQL đã được khởi động lại để phục vụ việc kiểm tra website. Bản clone hiện dùng MySQL tại `127.0.0.1:3307`, database `TourDB_codex_demo`; website chạy tại `http://127.0.0.1:8000`. Cấu hình SQLite trước đó được giữ trong `.env.backup`.


Đã mở rộng trực tiếp database demo từ 15 lên 30 tour. Các bản ghi cũ trong cả 14 bảng nghiệp vụ được đối chiếu và giữ nguyên; ảnh mới có nguồn, giấy phép và SHA-256. Danh sách công khai được phân trang chín tour mỗi trang, tổng cộng bốn trang với 30 tour.


## Tương thích với main mới

Đã ghép với main `fc34001` và giữ cả hỗ trợ factory, quan hệ yêu thích, đánh giá tour và liên kết ghi công ảnh. Chạy migration trước khi mở website để bổ sung bảng `favorites`. `DemoDataIntegrationTest` kiểm tra tài khoản demo trên đăng nhập chung, lưu/bỏ tour yêu thích và lọc giá/số ngày với danh mục 30 tour.
