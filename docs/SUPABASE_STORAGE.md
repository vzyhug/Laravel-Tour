# Kho ảnh dùng chung trên Supabase Storage

File ảnh được lưu tập trung trên Supabase Storage; dữ liệu tour, tài khoản, đơn đặt và thanh toán vẫn nằm trong MySQL. Không cần chuyển sang PostgreSQL hoặc Supabase Auth. Database giữ URL công khai bền vững và các trường `storage_disk`, `storage_bucket`, `storage_path`, `sha256`.

## 1. Tạo project và bucket

1. Vào https://supabase.com/dashboard, tạo project trong organization dùng gói Free nếu phù hợp. Chọn vùng gần người dùng, ví dụ Singapore. Mật khẩu database Supabase không phải khóa upload và không dùng cho MySQL của ứng dụng.
2. Trong Storage, tạo bucket `tour-images`, bật Public. Đây là ảnh địa danh công khai: ai có URL đều xem được. Giữ nguyên bảo vệ ghi; không tạo policy upload/update/delete cho `anon` hoặc người dùng công khai.
3. Trong Project Settings → API Keys → Publishable and secret API keys, tạo Secret key riêng tên `laravel-tour` hoặc dùng khóa Secret mặc định. Cần khóa bắt đầu bằng `sb_secret_`, không dùng `sb_publishable_`, JWT signing secret, mật khẩu database hoặc khóa S3.
4. Chỉ điền Secret vào `.env` trên server, không gửi qua chat, đưa vào `VITE_*`, JavaScript hoặc commit. Khóa server có quyền cao và bỏ qua RLS; ứng dụng chỉ dùng nó sau khi Laravel đã kiểm tra quyền Admin/Staff và ảnh upload.

SQL tham khảo tạo bucket mới với giới hạn JPEG/PNG/WebP và 5 MB: `supabase/storage.sql`. Nếu bucket đã tồn tại, SQL không đổi quyền của bucket đó; kiểm tra Public và hạn mức trong Dashboard.

Supabase hiện có Free plan gồm 1 GB file storage; bộ nguồn hiện khoảng 220 MiB (gồm ảnh tour, danh mục và thư viện địa danh). Free project có thể bị tạm dừng sau một tuần không hoạt động; xem điều kiện hiện hành trước triển khai dài hạn: https://supabase.com/pricing
Public bucket chỉ bỏ kiểm soát quyền tải ảnh, các thao tác ghi vẫn chịu kiểm soát: https://supabase.com/docs/guides/storage/buckets/fundamentals

## 2. Cấu hình Laravel

```dotenv
MEDIA_DRIVER=supabase
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_STORAGE_BUCKET=tour-images
SUPABASE_SECRET_KEY=sb_secret_your_server_key
# Để trống khi PHP đã có hệ thống CA hợp lệ.
MEDIA_CA_BUNDLE=
```

Giữ MySQL trong `DB_*`. `FILESYSTEM_DISK` không quyết định backend ảnh. Không cần cài thêm SDK: lớp Supabase dùng HTTP client có sẵn trong Laravel và API Storage chính thức.

Secret mới đi trong header `apikey`, không gửi `Authorization: Bearer sb_secret_...`. Các download công khai không mang khóa server. Upload và download không theo HTTP redirect. Backend chỉ chấp nhận Project URL HTTPS dạng `https://project-ref.supabase.co`, không gửi khóa đến host ngoài Supabase. Tham khảo: https://supabase.com/docs/guides/getting-started/migrating-to-new-api-keys

Trên Windows, nếu PHP báo `cURL error 60`, cấu hình CA hợp lệ qua `MEDIA_CA_BUNDLE` (ví dụ bundle đi kèm Git cho Windows nếu đã được cài và cập nhật). Tùy chọn này chỉ áp dụng kết nối kho ảnh; không tắt xác minh TLS. Server Linux thông thường để trống và dùng hệ thống CA của PHP.

```bash
composer install
php artisan config:clear
php artisan media:doctor
php artisan media:doctor --probe
```

`--probe` upload một PNG nhỏ theo hash rồi tải bằng URL công khai để so SHA-256. Không in khóa vào output. Không tiếp tục nếu probe thất bại. Ảnh probe có khóa theo nội dung nên chạy lại không tạo nhiều bản trùng.

## 3. Chuyển ảnh

Với database đã có dữ liệu: sao lưu database, ảnh upload và cấu hình trước khi chuyển; bảo trì trong thời gian chuyển để có snapshot nhất quán. Dùng cache có lock chung giữa các server (database hoặc Redis).

```bash
php artisan migrate
php artisan media:migrate
php artisan media:migrate --apply
php artisan media:verify --remote
php artisan itineraries:audit --verify-cloud
```

- `media:migrate` mặc định chỉ kiểm kê, không upload hoặc ghi database. Đọc 60 ảnh tour gốc + 112 ảnh địa điểm gốc + 72 ảnh danh mục điểm đến/khách sạn + 215 ảnh thư viện địa danh và các ảnh upload dưới `/storage/` đang được database tham chiếu. URL local tuyệt đối theo `APP_URL` cũng được nhận diện. Thiếu file, checksum sai, đường dẫn ra ngoài thư mục nguồn, hoặc URL ngoài kho quản lý làm lệnh dừng để xử lý rõ ràng.
- `--apply` tạo object theo SHA-256, không upsert/ghi đè nội dung có sẵn. Các đường dẫn trùng nội dung dùng chung file. 459 nguồn ảnh hiện tương ứng 438 file khác nội dung; hai tour Vũng Tàu tái sử dụng ảnh trong danh mục.
- Backend nhận upload thành công hoặc lỗi Duplicate hợp lệ, sau đó tải ảnh công khai và so SHA-256 trước khi chấp nhận URL. Bucket Private, lỗi upload hoặc checksum sai đều làm bước chuyển dừng.
- Nhật ký `storage/app/private/media-migration.json` lưu sau từng tham chiếu đã xác minh. Nếu gián đoạn, database chưa đổi; chạy lại để tiếp tục. Nhật ký gắn với database, Project URL và bucket, tránh dùng nhầm môi trường.
- Chỉ sau khi tất cả ảnh đã xác minh mới cập nhật ba bảng `images_tour`, `itinerary_place_images`, `hotel_images` trong một database transaction. Lệnh kiểm tra lại URL cũ để tránh ghi đè thay đổi đồng thời; không thay dữ liệu tour/booking hoặc tác giả/giấy phép.
- Manifest `database/data/media-cloud.json` được tạo sau chuyển. Phân phối file này cùng bản triển khai để các máy mới seed cùng kho ảnh. Manifest chỉ có URL ảnh công khai, hash và vị trí lưu trữ; không chứa Secret.
- Seeder/factory đọc manifest ở chế độ Supabase, không upload và không yêu cầu ảnh local. Manifest chưa đủ hoặc không thuộc đúng project/bucket sẽ báo lỗi trước khi seed tour.
- Nếu ghi manifest cuối cùng lỗi sau khi database đã commit, sửa quyền ghi và chạy lại; nhật ký đã lưu tham chiếu cần thiết.

Với database mới: chạy `migrate`, `media:migrate --apply`, sau đó `db:seed`. Lệnh vẫn chuyển toàn bộ danh mục khi chưa có bản ghi ảnh nào trong database. Không dùng `migrate:fresh` trên database hiện có.

## 4. Triển khai và nghiệm thu

Phân phối mã nguồn + manifest, cấu hình cùng Project URL/bucket và Secret riêng của môi trường. Chạy migration rồi seed nếu cần dữ liệu mẫu. Không cần sao chép ảnh local hoặc `storage:link` cho ảnh cloud.

Kiểm tra trang chủ, chi tiết tour, ảnh lịch trình, hóa đơn, danh mục điểm đến và khách sạn; upload ảnh từ Admin/Staff. `media:verify --remote` phải đạt. Thử khi không có `public/images/tours/demo/`, `public/images/itinerary/`, `public/images/directory/` và ảnh local trong `storage/app/public`: các ảnh ứng dụng vẫn phải tải từ Supabase.

Chỉ bỏ các file ảnh nguồn khỏi bản triển khai/Git sau khi mọi môi trường dùng cloud và đã xác minh backup. Lệnh chuyển giữ nguồn local để khôi phục; không sửa lịch sử Git. Nguồn và giấy phép tiếp tục nằm trong các manifest dữ liệu gốc để kiểm tra nội bộ.

## 5. Khôi phục và lỗi upload

```bash
php artisan media:rollback
php artisan media:rollback --apply
```

Mặc định chỉ kiểm tra. `--apply` khôi phục URL, metadata lưu trữ và manifest đã có trong nhật ký; không xóa object cloud dùng chung. Cần còn ảnh nguồn/backup. Dừng nếu database/project/bucket khác hoặc tham chiếu đã được sửa sau chuyển. Không khôi phục/xóa các bản ghi được tạo sau thời điểm snapshot; dùng backup database nếu cần phục hồi toàn môi trường.

Sau khôi phục ở môi trường phát triển, đặt `MEDIA_DRIVER=local` và clear config; production dùng phiên bản trước tương ứng nếu cần rollback. Local driver chỉ dùng phát triển/kiểm thử.

Upload mới lưu và xác minh cloud trước, sau đó đổi thumbnail và cập nhật tour trong một transaction. Lỗi upload giữ nguyên dữ liệu cũ. Lỗi ghi database không tự xóa file theo hash vì file có thể được dùng chung; object không còn tham chiếu cần quy trình rà soát riêng. Ẩn tour không xóa ảnh.

## 6. Kiểm thử

```bash
php vendor/bin/phpunit
php vendor/bin/phpunit -c phpunit.mysql.xml --filter "CloudMediaTest|SupabaseImageStorageTest"
```

PHPUnit ép local driver để các kiểm thử cũ không gọi mạng. Các kiểm thử Supabase giả lập HTTP/backend: header Secret, public GET không khóa, không theo redirect, duplicate, sai checksum, sai host/project, lỗi upload/DB, dry-run, chạy tiếp sau gián đoạn, dùng chung ảnh, reseed, rollback và seed/hiển thị khi public root không có ảnh.

Có 8 kiểm thử dữ liệu/đặt tour thất bại sẵn trên main `58e75be` (đã đối chiếu bằng snapshot main riêng); theo dõi riêng các lỗi đó khi đánh giá kết quả toàn bộ bộ kiểm thử. Nghiệm thu cloud thật cần kết quả `media:doctor --probe` và `media:verify --remote` trên project của nhóm.

## Kết quả kiểm tra mã nguồn hiện tại

- 33 kiểm thử liên quan kho ảnh, danh mục điểm đến/khách sạn và tour mới đạt trên SQLite và MySQL 8.4.3 (439 assertions mỗi hệ quản trị), bao gồm HTTP Supabase giả lập và luồng dữ liệu.
- Toàn bộ bộ kiểm thử: 61/69 đạt. 8 trường hợp thất bại trùng với snapshot main gốc (34/42 đạt), không có trường hợp thất bại mới. Chi tiết bàn giao: `docs/HANDOFF_DEV_LOC.md`.
- `composer validate`, Pint và kiểm tra diff đạt; không thêm SDK/dependency ngoài Laravel.
- Khóa Supabase chỉ nằm trong `.env` bị Git bỏ qua; đã kiểm tra khóa không xuất hiện trong các file Git theo dõi hoặc file mới dự kiến commit.

## Môi trường demo đã kết nối

- Project URL: `https://tgftmvgboogmmhzmafdc.supabase.co`, bucket Public `tour-images`.
- 459 tham chiếu trong danh mục đã được chuyển thành 438 file dùng chung; mỗi file được tải công khai và đối chiếu SHA-256 khi chuyển. Bucket có thêm ảnh probe rất nhỏ phục vụ kiểm tra kết nối.
- Bản main dùng MySQL riêng trên `127.0.0.1:3308`, database `tour_booking`; dữ liệu mẫu có 32 tour, 64 ảnh tour, 335 ảnh địa danh, 64 ảnh khách sạn và 120 booking cũ. Kiểm thử MySQL dùng database khác là `TourDB_codex_test`.
- Web main tại `http://127.0.0.1:8001/`. Đã kiểm tra HTTP 200 trên trang chủ, danh sách, trang ghi nguồn và 30 trang chi tiết; tất cả src ảnh dùng URL Supabase, không có khóa server trong HTML.
- Manifest cloud được đưa cùng mã nguồn; Secret, cấu hình máy cá nhân và nhật ký khôi phục nằm ngoài Git. Ảnh nguồn local tạm được giữ để khôi phục trong giai đoạn nhóm chuyển các môi trường sang cloud.
