# Giới thiệu và thư viện ảnh địa danh

- 112 mô tả chung trong bộ lịch trình được thay bằng đoạn giới thiệu riêng, ngắn gọn. 8 địa danh Vũng Tàu đã có giới thiệu riêng và được giữ lại.
- Thêm 215 ảnh thực tế cho toàn bộ 120 địa danh, tổng 335 ảnh: 95 nơi có 3 ảnh, 25 nơi có 2 ảnh. File ảnh gốc không bị ghép hoặc chỉnh sửa; metadata và SHA-256 được giữ trong dữ liệu nội bộ. Không hiển thị phần nguồn/quyền ảnh trên giao diện.
- Trang chi tiết địa danh/khách sạn dùng thư viện ảnh chung. Bấm ảnh để mở bộ xem lớn, chuyển bằng nút Trước/Sau hoặc phím mũi tên, đóng bằng nút Đóng, Escape hoặc bấm nền ngoài. Hỗ trợ bàn phím, trả focus về ảnh đã bấm và khóa cuộn nền khi mở. Không có JavaScript: liên kết vẫn mở ảnh ở tab mới.
- Ảnh địa danh trong chương trình tour, trình chỉnh lịch và bản lịch đã gửi cũng có thể bấm phóng to. Chương trình tour dùng ảnh đại diện hiện có; bộ ảnh đầy đủ ở trang chi tiết địa danh.

## Áp dụng trên database đang có

Cấu hình Supabase theo `SUPABASE_STORAGE.md`, giữ bản sao lưu database trước khi cập nhật. Chạy tại thư mục dự án:

```sh
php artisan media:migrate --apply
php artisan db:seed --class=TourItinerarySeeder
php artisan db:seed --class=DestinationDirectorySeeder
php artisan db:seed --class=PlaceGallerySeeder
php artisan media:verify
php artisan itineraries:audit
npm install
npm run build
```

`media:migrate --apply` kiểm tra byte/checksum của mọi object trước khi ghi manifest. Với bộ dữ liệu này: 459 đường dẫn nguồn dùng chung 438 object trong Supabase. Seeder không upload ảnh; sau khi có manifest không cần thư mục ảnh local. `PlaceGallerySeeder` kiểm tra toàn bộ mapping trước khi ghi, upsert theo địa danh + checksum trong transaction, chạy lại không nhân bản hoặc xóa ảnh cũ. Không tạo đơn, không sửa giá/lịch khởi hành/chỗ trống hoặc lịch riêng của khách. Database mới có thể dùng `php artisan migrate --seed` sau bước chuyển ảnh.

Hai snapshot dữ liệu chính: `tour-itineraries.json` chứa giới thiệu và ảnh đại diện; `place-galleries.json` chứa ảnh bổ sung. `scripts/build_itinerary_catalog.py` giữ lại phần giới thiệu đã biên soạn khi dựng lại bộ dữ liệu. Khi thêm ảnh sau này, cập nhật snapshot, tải file đúng đường dẫn rồi chạy chuyển ảnh trước khi seed. Ảnh bổ sung nằm tại `public/images/places/gallery/` để có thể tái tạo kho ảnh từ nguồn đã kiểm tra.

## Kiểm tra

```sh
php artisan test --filter="PlaceGalleryTest|CloudMediaTest|DestinationTourTest"
php vendor/bin/phpunit -c phpunit.mysql.xml --filter="PlaceGalleryTest|CloudMediaTest|DestinationTourTest"
```

MySQL test phải dùng database riêng `TourDB_codex_test`; đặt `DB_PORT` phù hợp máy phát triển. Bộ kiểm thử xác nhận phủ đủ địa danh, ảnh thật đọc được và khớp checksum, không trùng trong cùng địa danh, giữ ID/ảnh đại diện/lịch trình khi seed lại, không ghi dở khi thiếu mapping, hiển thị cloud khi public root không có ảnh local. Kiểm thử trình duyệt kiểm tra điều khiển ảnh, bàn phím, trạng thái đóng/mở và bố cục điện thoại.
