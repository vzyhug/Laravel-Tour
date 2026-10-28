# VietGo Booking Tour — Laravel + Tailwind CSS

Project đã được chuyển từ Spring Boot/Thymeleaf sang Laravel 13, Blade và Tailwind CSS 4. Mã Java cũ được giữ tại `legacy-spring/` để đối chiếu.

## Chức năng

Phần lịch trình ở trang tour hỗ trợ tùy chỉnh và gửi Admin/Sale tư vấn báo giá. Xem [luồng sử dụng và quan hệ dữ liệu](docs/PERSONAL_TOUR_ITINERARIES.md), [báo cáo rà toàn bộ tour](docs/TOUR_ITINERARY_AUDIT.md).

- Trang chủ, tìm kiếm/lọc tour, tour theo vùng và chi tiết lịch trình.
- Đăng ký, đăng nhập, hồ sơ và lịch sử đặt tour của khách hàng.
- Đặt tour nhiều hành khách, kiểm soát số chỗ bằng database transaction và row lock.
- Thanh toán mô phỏng, hóa đơn và đánh giá tour.
- Trang quản trị: dashboard, tour, lịch khởi hành, khách hàng, nhân viên, thanh toán và tỉnh/thành.
- Giao diện responsive, accessible bằng Blade components + Tailwind CSS 4.

## Cài đặt

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
# Cấu hình Supabase theo docs/SUPABASE_STORAGE.md trước khi seed lần đầu:
php artisan media:doctor --probe
php artisan media:migrate --apply
php artisan db:seed
npm run build
php artisan serve
```

Trên Windows PowerShell bị chặn `npm.ps1`, dùng `npm.cmd install` và `npm.cmd run build`.

Mặc định `.env.example` dùng MySQL database `tour_booking`. Có thể đổi sang SQLite bằng `DB_CONNECTION=sqlite` và tạo file `database/database.sqlite`.

## Tài khoản demo sau khi seed

- Admin: `admin` / `Admin@123`
- Guide: `guide` / `Guide@123`
- Sale: `sale_linh` / `Sale@123`
- Khách hàng: `demo01@vietgo.test` / `Customer@123`

Đăng nhập chung cho nhân viên và khách hàng tại `/login`; trang quản trị tại `/admin/dashboard`.

## Dữ liệu mẫu, factory và ảnh địa danh

Seeder hiện có **30 tour**, **83 ngày lịch trình**, **120 lịch khởi hành**, **24 khách hàng**, **120 đơn đặt tour**, **300 hành khách**, **60 thanh toán**, **60 hóa đơn** và **30 đánh giá**. Có 14 factory nghiệp vụ, bên cạnh `UserFactory` có sẵn.

Ảnh dùng **Supabase Storage chung**; dữ liệu nghiệp vụ giữ ở MySQL. Danh mục có **60 ảnh tour và 112 ảnh địa điểm**, được chuyển một lần bằng `media:migrate --apply`. Seeder/factory đọc manifest cloud, không upload và không cần ảnh local sau chuyển. Ảnh nguồn tạm được giữ để chuyển/khôi phục; nguồn, tác giả và giấy phép tiếp tục được bảo toàn. Xem [cấu hình, chuyển ảnh và nghiệm thu Supabase](docs/SUPABASE_STORAGE.md).

Để phát triển offline, đặt `MEDIA_DRIVER=local` trước khi seed; chế độ này bị chặn trong production.

```bash
# Sau khi có manifest Supabase hợp lệ (hoặc MEDIA_DRIVER=local khi phát triển):
php artisan migrate --seed
# Cập nhật dự án đã có dữ liệu:
php artisan migrate
php artisan db:seed
```

Phần kho ảnh có 17 kiểm thử mới đạt trên SQLite và MySQL 8.4.3. Bộ kiểm thử toàn dự án còn 8 lỗi đã có trên main; xem báo cáo trong `docs/SUPABASE_STORAGE.md`. Cấu hình kiểm thử MySQL: `phpunit.mysql.xml`.

Xem [hướng dẫn seeder, factory và tải lại ảnh](docs/SEEDING.md) để biết tài khoản demo, cấu trúc dữ liệu và ví dụ sử dụng. Seeder giữ các đơn đã tồn tại và số chỗ đã giữ; chạy lại cùng ngày không tạo thêm dữ liệu trùng.

## Dữ liệu Spring cũ

File SQL ban đầu nằm tại `legacy-spring/src/main/resources/mysql_query/Java_data_query.sql`. Schema Laravel giữ tên bảng và khóa chính tương thích để thuận tiện tái sử dụng dữ liệu hiện có. Các password dạng BCrypt của Spring có thể được Laravel xác thực; các chuỗi demo như `hash123` phải được reset/hash lại.
