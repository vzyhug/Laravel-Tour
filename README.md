# VietGo Booking Tour — Laravel + Tailwind CSS

Project đã được chuyển từ Spring Boot/Thymeleaf sang Laravel 13, Blade và Tailwind CSS 4. Mã Java cũ được giữ tại `legacy-spring/` để đối chiếu.

## Chức năng

- Trang chủ, tìm kiếm/lọc tour, tour theo vùng và chi tiết lịch trình.
- Đăng ký, đăng nhập, hồ sơ và lịch sử đặt tour của khách hàng.
- Đặt tour nhiều hành khách, kiểm soát số chỗ bằng database transaction và row lock.
- Thanh toán mô phỏng, hóa đơn và đánh giá tour.
- Trang quản trị: dashboard, tour, lịch khởi hành, khách hàng, nhân viên, thanh toán và tỉnh/thành.
- Giao diện responsive, accessible bằng Blade components + Tailwind CSS 4.
- Tìm tour theo ngày/số khách; báo giá có hạn, chống submit trùng và giữ snapshot giá/chính sách.
- Giữ chỗ có hạn thanh toán; chứng từ tour demo riêng với hóa đơn; lịch sử sự kiện đặt/tiền/hủy.

Phạm vi áp dụng skill `design-traveloka-platform`, các giới hạn demo và lộ trình mở rộng: [docs/OTA_IMPLEMENTATION.md](docs/OTA_IMPLEMENTATION.md).

Sau cập nhật, chạy `php artisan migrate` và `npm.cmd run build`. Chạy thêm `php artisan schedule:work` để giải phóng chỗ quá hạn mỗi phút; hoặc `php artisan bookings:expire` thủ công. Thanh toán, hoàn tiền và chứng từ đều là mô phỏng, không có giá trị giao dịch thật.

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
- Sale: `sale_linh` / `Sale@123`
- Khách hàng: `demo01@vietgo.test` / `Customer@123`

Đăng nhập chung cho nhân viên và khách hàng tại `/login`; trang quản trị tại `/admin/dashboard`.

## Dữ liệu mẫu, factory và ảnh địa danh

Seeder hiện có **30 tour**, **83 ngày lịch trình**, **120 lịch khởi hành**, **24 khách hàng**, **120 đơn đặt tour**, **300 hành khách**, **60 thanh toán**, **60 hóa đơn** và **30 đánh giá**. Có 14 factory nghiệp vụ, bên cạnh `UserFactory` có sẵn.

Bộ **60 ảnh thực tế** được lưu tại `public/images/tours/demo/`, dùng trực tiếp sau khi clone, không cần tải ảnh qua mạng khi seed. Tác giả, nguồn, giấy phép và mã SHA-256 nằm trong `database/data/tour-images.json`; bản ghi nguồn dễ đọc tại `public/images/tours/demo/ATTRIBUTION.md`.

```bash
php artisan migrate --seed
# Cập nhật dự án đã có dữ liệu:
php artisan migrate
php artisan db:seed
```

Bộ dữ liệu đã vượt qua cùng 15 kiểm thử trên SQLite và MySQL 8.4.3. Cấu hình kiểm thử MySQL: `phpunit.mysql.xml`.

Xem [hướng dẫn seeder, factory và tải lại ảnh](docs/SEEDING.md) để biết tài khoản demo, cấu trúc dữ liệu và ví dụ sử dụng. Seeder giữ các đơn đã tồn tại và số chỗ đã giữ; chạy lại cùng ngày không tạo thêm dữ liệu trùng.

## Dữ liệu Spring cũ

File SQL ban đầu nằm tại `legacy-spring/src/main/resources/mysql_query/Java_data_query.sql`. Schema Laravel giữ tên bảng và khóa chính tương thích để thuận tiện tái sử dụng dữ liệu hiện có. Các password dạng BCrypt của Spring có thể được Laravel xác thực; các chuỗi demo như `hash123` phải được reset/hash lại.
