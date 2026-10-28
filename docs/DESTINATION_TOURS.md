# Tour mới cho điểm đến Vũng Tàu

Điểm đến bổ sung chưa có tour là Vũng Tàu. `database/data/destination-tours.json` chứa hai chương trình do dự án tự biên soạn dựa trên tám địa danh đã đối chiếu nguồn, không sao chép lịch bán của đơn vị du lịch.

| Chương trình | Thời gian | Giá mẫu/người | Số điểm dừng |
| --- | --- | ---: | ---: |
| Vũng Tàu – Biển xanh & dấu ấn di sản | 2 ngày 1 đêm | 1.990.000đ | 5 |
| Vũng Tàu – Văn hóa, bảo tàng & biển đảo | 3 ngày 2 đêm | 3.290.000đ | 8 |

Giá và dịch vụ là dữ liệu minh họa để thử luồng đặt tour, chưa phải báo giá thương mại hoặc cam kết từ các khách sạn trong danh mục. Cả mô tả và dịch vụ bao gồm trên web đều nêu rõ điều này. Chương trình dài đi qua đủ tám địa danh: Bạch Dinh, Bảo tàng – Thư viện Bà Rịa – Vũng Tàu, Tháp Tam Thắng, Tượng Chúa Kitô, Hòn Bà, Bãi Sau, Hải đăng và Bảo tàng Vũ khí cổ Robert Taylor. Tham quan Hòn Bà từ bờ là phương án mặc định; chỉ ra đảo khi thủy triều, thời tiết và hướng dẫn tại chỗ cho phép. Giờ tham quan thực tế cần xác nhận khi tổ chức.

## Dữ liệu tạo ra

- Tổng sau khi seed đầy đủ: 32 tour, 88 ngày chương trình, 133 điểm dừng, 126 lịch khởi hành, 64 ảnh tour. Kho địa danh vẫn có 120 địa danh, đủ 120 ảnh.
- Mỗi tour mới có 3 đợt khởi hành tương lai, 24 chỗ/đợt, giờ khởi hành mẫu 07:00. Không tự tạo đánh giá hay đơn đặt đã hoàn thành cho tour mới.
- Ngày bắt đầu dự kiến là hôm nay +10/+40/+70 ngày, cộng thêm 7 ngày với chương trình thứ hai. Seeder tìm hướng dẫn viên không trùng lịch (tính cả ngày đi/ngày về), có thể dời tối đa 14 ngày để có người rảnh; nếu không tìm được thì transaction được rollback.
- Khi chạy lại trong cùng khung ngày, seeder giữ lịch khởi hành đã tồn tại, bao gồm giá, số chỗ và trạng thái hủy. Ngày neo trượt theo ngày chạy, nên chạy vào ngày khác có thể bổ sung lịch mới, tương tự dữ liệu mẫu cũ.
- Tái sử dụng các bản ghi địa danh và ảnh Supabase đã có; thêm ảnh hiện vật Bảo tàng Robert Taylor từ https://vietnam.travel/things-to-do/vung-tau-family-holiday/ (trang nguồn chưa công bố giấy phép tái sử dụng).
- Trang điểm đến có thêm danh sách tour đi qua các địa danh thuộc nhóm đó. Tìm kiếm “Vũng” trong danh sách tour cũng hiển thị hai chương trình.

## Cách chạy

Database mới dùng `php artisan migrate --seed` sau khi cấu hình database và kho ảnh. Với database đã có 30 tour cũ, chạy:

```sh
php artisan migrate
php artisan db:seed --class=DestinationDirectorySeeder
php artisan db:seed --class=DestinationTourSeeder
php artisan itineraries:audit
php artisan media:verify
```

Nếu manifest chưa có ảnh Robert Taylor, chạy `php artisan media:migrate --apply` trước các seeder. Không cần chạy lại TourSeeder hoặc DemoBookingSeeder khi chỉ muốn bổ sung hai tour mới trên database đang sử dụng.

Factory:

```php
$tour = Tour::factory()->destinationProgram('vung-tau-bien-di-san-2n1d')->create();
$image = TourImage::factory()->for($tour)->thumbnail()->create();
```

Factory trên tạo bản ghi tour/ảnh; DestinationTourSeeder tạo đầy đủ các ngày, điểm dừng và đợt khởi hành.

## Khách sạn khi đặt tour

Luồng đặt tour hiện tại chưa có lựa chọn khách sạn. StoreBookingRequest, CreateBooking và bảng bookings chỉ lưu đợt khởi hành, hành khách, hình thức đón, mã giảm giá và số tiền; không có hotel_id. Các tour mới mô tả lưu trú do đơn vị tổ chức bố trí. Danh mục 64 khách sạn là dữ liệu tra cứu, không phải danh sách phòng trống đã kết nối.

Muốn thêm lựa chọn khách sạn cần một thay đổi nghiệp vụ riêng: cấu hình khách sạn được phép cho mỗi tour/đợt, phụ thu và số đêm, lưu lựa chọn cùng giá tại thời điểm đặt, kiểm tra lại trên máy chủ và hiển thị trong xác nhận/hóa đơn. Không nên cho chọn tự do mọi khách sạn trong cùng nhóm điểm đến vì có thể khác cung đường, giá và điều kiện phòng.

## Kiểm thử

DestinationTourTest kiểm tra đủ địa danh, thời lượng/ngày về, hướng dẫn viên không trùng, giá và số chỗ, đặt được tour mới, bảo toàn 30 chương trình cũ, chạy lại không đặt lại số chỗ và factory ảnh đúng chương trình. CloudMediaTest kiểm tra seeder/trang mới hoạt động bằng ảnh Supabase khi không có thư mục ảnh local.
