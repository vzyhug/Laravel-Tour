# BÁO CÁO KIỂM THỬ TOÀN DIỆN VÙNG NHẬP LIỆU (INPUT VALIDATION & BUSINESS LOGIC QA REPORT)

* **Dự án:** VietGo Booking Tour (Laravel + Tailwind CSS)
* **Người thực hiện kiểm thử:** Tester (Khải)
* **Nhánh làm việc (Branch):** `dev-Khai`
* **Ngày kiểm thử:** 21/10/2026
* **Bộ test case tự động đi kèm:** `tests/Feature/InputValidationBugTest.php`

---

## I. TỔNG QUAN (EXECUTIVE SUMMARY)

Thực hiện yêu cầu kiểm tra lại toàn bộ logic vùng nhập liệu trên toàn hệ thống (bao gồm cả phân hệ **Khách hàng** và **Quản trị viên - Admin**), đội ngũ QA đã tiến hành kiểm thử hộp đen (Black-box), kiểm thử biên (Boundary Value Analysis), kiểm thử phân vùng tương đương (Equivalence Partitioning) và rà soát mã nguồn (Code Review).

### Kết quả tổng hợp:
* **Tổng số lỗi phát hiện:** **14 lỗi**
* **Phân loại theo mức độ nghiêm trọng (Severity):**
  * 🔴 **Critical / High (Nghiêm trọng - Cần sửa ngay):** 5 lỗi (Lỗi sai lệch luồng đặt tour, ngày khởi hành quá khứ, đánh giá trước khi đi, giá vé đảo nghịch, mất dữ liệu form).
  * 🟡 **Medium (Trung bình - Ảnh hưởng logic & toàn vẹn dữ liệu):** 6 lỗi (Lọc giá đảo chiều âm thầm, SĐT không hợp lệ, thiếu xác minh tuổi trẻ em, lệch số ngày - đêm).
  * 🟢 **Low / UX (Nhẹ & Trải nghiệm người dùng):** 3 lỗi (Thông báo tiếng Anh mặc định, thiếu xác nhận mật khẩu, thiếu báo lỗi inline dưới ô input).

---

## II. CHI TIẾT CÁC LỖI LOGIC VÙNG NHẬP LIỆU (DETAILED BUG LIST)

---

### 1. Phân hệ Khách hàng: Tìm kiếm & Lọc Tour (`/tours` & `/`)

#### 🔴 BUG-01: Lọc giá không validate `price_min > price_max`, âm thầm đảo ngược giá trị (Silent Swap)
* **Vùng nhập liệu:** Ô `GiA t` (`price_min`) và `GiA `n` (`price_max`) tại `/tours`.
* **Mức độ:** 🟡 Medium
* **Các bước tái hiện:**
  1. Truy cập trang `/tours`.
  2. Nhập `Giá từ`: `10.000.000` đ.
  3. Nhập `Giá đến`: `2.000.000` đ.
  4. Bấm **Lọc tour**.
* **Kết quả thực tế (Actual):**
  * Hệ thống không hề hiển thị thông báo lỗi.
  * Trong mã nguồn `TourController.php` (dòng 18-20), code đang âm thầm tự hoán đổi 2 giá trị:
    ```php
    if ($min !== '' && $max !== '' && (int) $min > (int) $max) {
        [$min, $max] = [$max, $min]; // Tự tráo đổi 10tr và 2tr thành 2tr và 10tr
    }
    ```
  * Kết quả trả về danh sách các tour từ 2 triệu đến 10 triệu.
* **Kết quả kỳ vọng (Expected):**
  * Khi `price_min > price_max`, hệ thống phải chặn lại hoặc báo lỗi validation đỏ: *"Giá từ không được lớn hơn Giá đến"* để người dùng nhận biết được thao tác sai của mình.
* **Phương án sửa gợi ý:**
  * Validate request với rule:
    ```php
    $request->validate([
        'price_max' => ['nullable', 'numeric', 'gte:price_min'],
    ], [
        'price_max.gte' => 'Giá đến phải lớn hơn hoặc bằng Giá từ.',
    ]);
    ```
  * Đồng thời thêm validate client-side bằng JavaScript trước khi submit form.

---

#### 🟡 BUG-02: Nhập số âm hoặc ký tự lạ vào ô giá bị chuyển đổi sai lệch
* **Vùng nhập liệu:** `price_min`, `price_max`.
* **Mức độ:** 🟢 Low
* **Mô tả:** Code dùng `preg_replace('/\D/', '', $request->input('price_min'))`. Khi người dùng cố tình nhập số âm `-500000`, regex tự động bỏ dấu trừ `-`, biến thành số dương `500000` mà không hề cảnh báo giá tiền không được âm.
* **Phương án sửa:** Kiểm tra `(int) $rawMin >= 0`, nếu có dấu trừ báo lỗi không hợp lệ.

---

### 2. Phân hệ Khách hàng: Đăng ký & Tài khoản (`/register` & `/account/profile`)

#### 🔴 BUG-03: Đăng ký tài khoản không ràng buộc số điện thoại duy nhất (`unique:customers,phone`)
* **Vùng nhập liệu:** Ô `Số điện thoại` (`phone`) tại `/register`.
* **Mức độ:** 🟡 Medium
* **Mô tả:** Trong `RegisterCustomerRequest.php`, trường `email` có kiểm tra `unique:customers,email`, nhưng trường `phone` chỉ kiểm tra regex mà KHÔNG kiểm tra `unique:customers,phone`. Một kẻ xấu có thể dùng 1 số điện thoại để đăng ký hàng trăm tài khoản khác nhau.
* **Phương án sửa:** Thêm `'unique:customers,phone'` vào `RegisterCustomerRequest`.

---

#### 🔴 BUG-04: Cập nhật hồ sơ (`/account/profile`) chấp nhận số điện thoại sai định dạng tùy tiện
* **Vùng nhập liệu:** Ô `Số điện thoại` (`phone`) tại `/account/profile`.
* **Mức độ:** 🔴 High (Bất nhất dữ liệu)
* **Các bước tái hiện:**
  1. Khách hàng đăng nhập vào `/account/profile`.
  2. Tại ô Số điện thoại, xóa số cũ đi và nhập: `abc-xyz-1234` hoặc `000000000`.
  3. Bấm **Lưu thay đổi**.
* **Kết quả thực tế:** Cập nhật thành công vào Database! Mã nguồn `CustomerDashboardController::updateProfile` chỉ kiểm tra `'phone' => ['required', 'string', 'max:20']`.
* **Kết quả kỳ vọng:** Báo lỗi số điện thoại không đúng định dạng chuẩn Việt Nam.
* **Phương án sửa:** Đồng bộ rule regex giống trang đăng ký:
  ```php
  'phone' => ['required', 'regex:/^(0|\+84)[0-9]{9}$/']
  ```

---

### 3. Phân hệ Khách hàng: Đặt Tour (`/booking/{departure}`)

#### 🔴 BUG-05: Hành khách "Trẻ em" không bắt buộc nhập ngày sinh & không kiểm tra tuổi (< 12 tuổi)
* **Vùng nhập liệu:** Ô `Ngày sinh` (`date_of_birth`) và `Loại hành khách` (`passenger_type`) tại form đặt tour.
* **Mức độ:** 🔴 High (Thất thoát doanh thu)
* **Các bước tái hiện:**
  1. Người lớn đặt tour, thêm hành khách thứ 2 chọn là `Trẻ em` (được giảm 50% giá vé).
  2. Để trống ô `Ngày sinh` -> Bấm tiếp tục thanh toán. Hoặc nhập ngày sinh năm 1980 (46 tuổi).
* **Kết quả thực tế:**
  * Hệ thống vẫn chấp nhận cho người 46 tuổi đi vé Trẻ em vì `StoreBookingRequest` định nghĩa `'date_of_birth' => ['nullable', 'date', 'before:today']`.
* **Kết quả kỳ vọng:**
  * Nếu chọn `Trẻ em`, bắt buộc phải có ngày sinh (`required_if:passengers.*.passenger_type,child`).
  * Tuổi tính theo ngày sinh phải nhỏ hơn 12 tuổi so với ngày khởi hành.
* **Phương án sửa:**
  ```php
  'passengers.*.date_of_birth' => [
      'required_if:passengers.*.passenger_type,child',
      'nullable',
      'date',
      'before:today',
  ]
  ```
  Và bổ sung custom validation rule kiểm tra `Carbon::parse($dob)->age < 12`.

---

#### 🟡 BUG-06: Hành khách "Người lớn" không bắt buộc nhập CCCD / Hộ chiếu
* **Vùng nhập liệu:** `id_card` tại form đặt tour.
* **Mức độ:** 🟡 Medium
* **Mô tả:** Trong quy định du lịch và lữ hành, hành khách người lớn bắt buộc cần CCCD (12 số) hoặc Hộ chiếu để mua bảo hiểm du lịch và đặt vé máy bay/tàu hỏa. Hiện tại `id_card` là `nullable` cho tất cả đối tượng, dẫn đến đơn đặt tour không có thông tin định danh của hành khách.
* **Phương án sửa:** Bắt buộc nhập `id_card` đối với người lớn (`required_if:passengers.*.passenger_type,adult`), regex 12 chữ số hoặc số hộ chiếu hợp lệ.

---

#### 🔴 BUG-07: Mất toàn bộ danh sách hành khách đã nhập khi form bị lỗi validation (UX Flaw)
* **Vùng nhập liệu:** Toàn bộ form `/booking/{departure}`.
* **Mức độ:** 🟡 Medium (Trải nghiệm người dùng)
* **Mô tả:** Form hành khách được render bằng thẻ JavaScript `<template>`. Khi submit lên backend bị lỗi validation (ví dụ chưa có người lớn), trang load lại và JavaScript chỉ render lại đúng 1 dòng hành khách trắng trơn. Người dùng mất sạch dữ liệu của 5-10 người vừa cất công gõ xong.
* **Phương án sửa:** Bổ sung logic render lại dữ liệu cũ từ `old('passengers')` trong Blade view khi có validation errors.

---

### 4. Phân hệ Khách hàng: Đánh giá Tour (`/account/bookings/{booking}`)

#### 🔴 BUG-08: Cho phép khách hàng gửi đánh giá trước khi chuyến đi thực sự diễn ra
* **Vùng nhập liệu:** Form `Đánh giá chuyến đi` tại `/account/bookings/{booking}`.
* **Mức độ:** 🔴 High (Sai nghiệp vụ)
* **Các bước tái hiện:**
  1. Khách đặt tour có ngày khởi hành vào 1 tháng sau (`departure_date = 2026-11-20`).
  2. Khách thực hiện thanh toán xong -> Trạng thái đơn đổi thành `confirmed`.
  3. Mở trang chi tiết booking: Form đánh giá sao và chia sẻ trải nghiệm hiển thị ngay lập tức. Khách có thể gửi đánh giá 5 sao hoặc 1 sao dù chưa hề bước chân lên tour!
* **Nguyên nhân code:** `CustomerDashboardController::review` chỉ kiểm tra:
  ```php
  abort_unless(in_array($booking->status, ['confirmed', 'completed'], true), 403);
  ```
* **Kết quả kỳ vọng:** Chỉ cho phép khách hàng đánh giá khi:
  * Trạng thái là `completed` HOẶC ngày về của chuyến đi đã qua (`departure->return_date < today()`).
* **Phương án sửa:**
  ```php
  abort_unless($booking->departure->return_date->isPast(), 403, 'Chuyến đi chưa kết thúc, bạn chưa thể đánh giá.');
  ```

---

### 5. Phân hệ Quản trị (Admin): Lịch khởi hành (`/admin/departures`)

#### 🔴 BUG-09: Cho phép Admin tạo lịch khởi hành với ngày trong quá khứ
* **Vùng nhập liệu:** Ô `Ngày khởi hành` (`departure_date`) tại `/admin/departures/create`.
* **Mức độ:** 🔴 High
* **Mô tả:** Admin có thể chọn ngày khởi hành là năm 2020. Hệ thống lưu thành công vào cơ sở dữ liệu.
* **Nguyên nhân code:** Trong `StoreDepartureRequest`:
  ```php
  'departure_date' => ['required', 'date'], // Thiếu after_or_equal:today
  ```
* **Phương án sửa:** Đổi thành:
  ```php
  'departure_date' => ['required', 'date', 'after_or_equal:today'],
  ```

---

#### 🔴 BUG-10: Cho phép giá vé Trẻ em đắt hơn giá vé Người lớn
* **Vùng nhập liệu:** `Giá trẻ em` (`child_price`) và `Giá người lớn` (`adult_price`) tại `/admin/departures`.
* **Mức độ:** 🟡 Medium
* **Mô tả:** Admin có thể nhập Giá người lớn = 1.000.000 đ nhưng Giá trẻ em = 9.000.000 đ. Hệ thống vẫn lưu bình thường mà không hề có kiểm tra tương quan logic giữa 2 mức giá.
* **Phương án sửa:** Thêm rule `'lte:adult_price'` cho `child_price`:
  ```php
  'child_price' => ['required', 'numeric', 'min:0', 'lte:adult_price'],
  ```

---

#### 🟡 BUG-11: Lệch số ngày giữa Tour và Lịch khởi hành
* **Vùng nhập liệu:** `departure_date` và `return_date`.
* **Mức độ:** 🟡 Medium
* **Mô tả:** Một Tour được tạo với thời lượng 3 ngày (`duration_days = 3`). Khi Admin tạo lịch khởi hành, Admin có thể chọn `departure_date` là ngày 01 và `return_date` là ngày 20 (chênh nhau 20 ngày), hệ thống vẫn cho phép lưu.
* **Phương án sửa:** Tính toán tự động `return_date = departure_date + (duration_days - 1)` hoặc validate khoảng cách giữa 2 ngày phải khớp với `duration_days` của tour.

---

### 6. Phân hệ Quản trị (Admin): Quản lý Tour & Nhân viên

#### 🟡 BUG-12: Cho phép số đêm lớn hơn số ngày một cách phi thực tế (`duration_nights > duration_days`)
* **Vùng nhập liệu:** `duration_days` và `duration_nights` tại `/admin/tours/create`.
* **Mức độ:** 🟡 Medium
* **Mô tả:** Trong du lịch, tour 1 ngày không thể có 5 đêm. Hiện tại hệ thống cho phép nhập `Số ngày = 1` nhưng `Số đêm = 10`.
* **Phương án sửa:** Trong `StoreTourRequest`:
  ```php
  'duration_nights' => ['required', 'integer', 'min:0', 'lte:duration_days'],
  ```

---

#### 🟢 BUG-13: Tạo mới nhân viên thiếu xác nhận lại mật khẩu (`password_confirmation`)
* **Vùng nhập liệu:** Ô `Mật khẩu` tại `/admin/employees/create`.
* **Mức độ:** 🟢 Low / UX
* **Mô tả:** Form tạo nhân viên chỉ có 1 ô nhập mật khẩu duy nhất, không có ô nhập lại mật khẩu xác nhận. Nếu người tạo gõ nhầm một ký tự thì nhân viên đó sẽ không thể đăng nhập được vào hệ thống.
* **Phương án sửa:** Thêm ô `Xác nhận mật khẩu` và rule `'confirmed'`.

---

#### 🟢 BUG-14: Thông báo lỗi validation hiển thị tiếng Anh mặc định và thiếu lỗi inline
* **Vùng nhập liệu:** Toàn bộ các form trên hệ thống.
* **Mức độ:** 🟢 Low / UX
* **Mô tả:** Khi vi phạm validation (ví dụ để trống, sai email, sai regex), hệ thống ném ra câu thông báo tiếng Anh chuẩn của Laravel: *"The email has already been taken"*, *"The phone field format is invalid"*. Toàn bộ website là tiếng Việt, điều này làm giảm độ chuyên nghiệp.
* **Phương án sửa:** Tạo file ngôn ngữ tiếng Việt `lang/vi/validation.php` hoặc cấu hình custom messages tiếng Việt cho các FormRequest.

---

## III. HƯỚNG DẪN KIỂM CHỨNG BẰNG TEST CASE TỰ ĐỘNG

Tester đã viết sẵn bộ kiểm thử tự động gồm 7 test case mô phỏng chính xác các lỗ hổng trên tại:
`tests/Feature/InputValidationBugTest.php`

Để chạy và kiểm chứng:
```bash
php artisan test --filter=InputValidationBugTest
```

---
*Báo cáo được tổng hợp đầy đủ và sẵn sàng để lập trình viên (Developer) xem xét và đưa ra kế hoạch vá lỗi.*
