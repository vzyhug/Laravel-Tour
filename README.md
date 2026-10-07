# VietGo Booking Tour — Laravel + Tailwind CSS

Project đã được chuyển từ Spring Boot/Thymeleaf sang Laravel 13, Blade và Tailwind CSS 4. Mã Java cũ được giữ tại `legacy-spring/` để đối chiếu.

## Chức năng

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
php artisan migrate --seed
npm run build
php artisan storage:link
php artisan serve
```

Trên Windows PowerShell bị chặn `npm.ps1`, dùng `npm.cmd install` và `npm.cmd run build`.

Mặc định `.env.example` dùng MySQL database `TourDB`. Có thể đổi sang SQLite bằng `DB_CONNECTION=sqlite` và tạo file `database/database.sqlite`.

## Tài khoản demo sau khi seed

- Admin: `admin` / `Admin@123`
- Guide: `guide` / `Guide@123`

Trang quản trị: `/admin/login`.

## Dữ liệu Spring cũ

File SQL ban đầu nằm tại `legacy-spring/src/main/resources/mysql_query/Java_data_query.sql`. Schema Laravel giữ tên bảng và khóa chính tương thích để thuận tiện tái sử dụng dữ liệu hiện có. Các password dạng BCrypt của Spring có thể được Laravel xác thực; các chuỗi demo như `hash123` phải được reset/hash lại.
