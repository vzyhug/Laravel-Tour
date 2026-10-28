# Danh mục điểm đến, địa danh và khách sạn

## Phạm vi

- 31 nhóm điểm đến: 30 nhóm theo tour hiện có và Vũng Tàu. Đây là nhóm du lịch, không phải danh mục đơn vị hành chính.
- 120 địa danh: tái sử dụng 112 bản ghi gốc, thêm 8 tại Vũng Tàu. Một địa danh có thể thuộc nhiều nhóm điểm đến qua bảng liên kết.
- Phân loại 120 địa danh theo bảo tàng, biển đảo, di tích, tâm linh, thiên nhiên, làng bản, chợ và khu vực khám phá.
- 64 khách sạn/resort/lodge trên toàn bộ 31 điểm đến: Vũng Tàu có 4, mỗi điểm đến còn lại có 2. Bao gồm 50 cơ sở bổ sung để phủ 25 điểm đến trước đây chưa có khách sạn.
- 72 ảnh mới: đủ 8 địa danh Vũng Tàu và 64 khách sạn. Ảnh hiện vật Bảo tàng Robert Taylor lấy từ bài giới thiệu của Vietnam Tourism.
- Có thêm 2 tour Vũng Tàu (2N1Đ và 3N2Đ), 5 ngày lịch trình và 6 lịch khởi hành tương lai. 30 tour cũ và dữ liệu đơn đặt trước đó được giữ nguyên. Chưa có tính năng chọn khách sạn khi đặt tour.

## Trang sử dụng

- `/destinations`: tìm tên và phân trang điểm đến.
- `/destinations/vung-tau`: tour có địa danh thuộc Vũng Tàu, địa danh và khách sạn của điểm đến.
- `/places`: tìm theo tên/địa chỉ, lọc điểm đến và loại địa danh.
- `/hotels`: tìm theo tên/địa chỉ, lọc theo điểm đến và sắp xếp theo đánh giá Google hoặc số lượt đánh giá. Có lối tắt tới Vũng Tàu, Hà Nội, Đà Nẵng, Đà Lạt, Phú Quốc và Hạ Long; danh sách đầy đủ vẫn có trong bộ lọc.
- `/places/{slug}`, `/hotels/{slug}`: ảnh có nguồn và giấy phép, thông tin liên hệ, bản đồ. Chi tiết khách sạn nối tiếp tới tour và địa danh thuộc cùng điểm đến.

## Dữ liệu và cách chạy

Dữ liệu có thể xem trực tiếp tại `database/data/destination-directory.json`. SerpApi Google Maps đã đối chiếu đủ 64/64 khách sạn theo 31 điểm đến ngày 28/10/2026. Seeder lưu Google Place ID, rating, số đánh giá, tọa độ và URL bản đồ; địa chỉ/số điện thoại cập nhật theo kết quả khớp tên hoặc địa chỉ. Nếu không xác định chắc chắn cùng một cơ sở thì kết quả không được gán. `google_verified_on` là ngày truy vấn, không phải khảo sát thực địa. Các ảnh khách sạn tiếp tục dùng bộ ảnh hiện có với nguồn và giấy phép đã ghi; không nhập ảnh Google Maps vì phản hồi không cung cấp giấy phép tái sử dụng.

Rating và lượt đánh giá là thông tin tham khảo của Google Maps, không phải điểm đánh giá trên VietGo. Giá, loại phòng và tồn kho không được suy ra từ kết quả Maps. Chức năng đặt phòng nội bộ chỉ hiện khi nhân viên đã tạo loại phòng; nếu chưa có tồn kho VietGo, khách được dẫn tới website khách sạn. Thanh toán/giữ phòng nội bộ vẫn theo giới hạn trong `HOTEL_BOOKING_MVP.md`.

Riêng Azerai Cần Thơ đã đổi tên thành Legacy Mekong; danh mục dùng tên hiện hành, còn slug cũ để giữ liên kết. Nguồn: [Cổng du lịch Cần Thơ](https://canthotourism.vn/vi/azeraicantho), [Marriott](https://www.marriott.com/vi/hotels/vcalm-legacy-mekong-can-tho-autograph-collection/overview/).

Máy đã có dữ liệu tour/lịch trình:

```sh
php artisan migrate
php artisan db:seed --class=DestinationDirectorySeeder
```

Database mới: chạy `php artisan migrate --seed` sau khi cấu hình database và kho ảnh. DatabaseSeeder gọi DestinationDirectorySeeder sau TourItinerarySeeder, rồi gọi DestinationTourSeeder để tạo các tour mới. Seeder chạy lại không nhân bản điểm đến, địa danh, khách sạn hay ảnh; không xóa các bản ghi ngoài bộ mẫu. Nếu thiếu địa danh gốc, toàn bộ transaction của seeder danh mục sẽ được rollback.

Nếu bổ sung hoặc thay ảnh trong JSON, tải ảnh nguồn vào đường dẫn tương ứng, ghi SHA-256 và ghi công, rồi chạy:

```sh
php artisan media:migrate --apply
php artisan db:seed --class=DestinationDirectorySeeder
php artisan media:verify
php artisan itineraries:audit
```

Chế độ Supabase dùng URL công khai trong manifest `database/data/media-cloud.json`. Seeder và factory không cần đọc file ảnh local trong chế độ này. Bí mật SUPABASE_SECRET_KEY chỉ nằm ở `.env`, không đưa lên Git hoặc frontend. `media:verify`, `media:migrate` và rollback đã quản lý thêm bảng `hotel_images`.

## Ảnh địa điểm

Ảnh là tư liệu tại thời điểm chụp, không bảo đảm mô tả hiện trạng mới nhất. Một số ảnh là phòng nghỉ, sảnh hoặc khuôn viên của cơ sở. Premier Pearl sử dụng ảnh phòng Classic Twin Ocean View, được ghi rõ chú thích; không dùng ảnh tòa nhà đang xây năm 2021.

Danh mục khách sạn ưu tiên website chính thức, website thương hiệu hoặc hồ sơ trên Cơ sở dữ liệu ngành Du lịch/cổng thông tin địa phương. Danh mục gồm cả resort/farmstay/lodge tại các vùng sinh thái; không tự gán hạng sao. Giá phòng VietGo hiển thị là giá tham khảo riêng, không phải giá xác nhận của khách sạn. Tình trạng phòng chỉ có khi nhân viên quản lý tồn theo ngày. Một số website cơ sở không truy cập được tại lúc thu thập; hồ sơ nguồn của cơ quan du lịch được dùng để đối chiếu. Thông tin chưa xác minh được như số điện thoại riêng của một vài cơ sở được để trống.

Các nhóm Hà Giang, Bến Tre, Mai Châu, Mù Cang Chải… là nhóm hành trình cũ; địa chỉ cơ sở có thể ghi tỉnh/phường mới theo website nguồn. Địa danh cùng nhóm hành trình có thể cách xa nhau, vì vậy gợi ý “cùng điểm đến” không phải cam kết ở gần hoặc phù hợp với mọi lịch trình.

Mỗi ảnh giữ URL trang nguồn, URL tải, tác giả/đơn vị công bố, giấy phép, chú thích và SHA-256. Ảnh mới được đưa lên bucket Supabase `tour-images` và kiểm tra nội dung trước khi seed. Giao diện dự án học tập chỉ hiển thị ảnh địa điểm; đã bỏ trang ghi công, liên kết nguồn, ngày đối chiếu và thông tin quyền ảnh. Metadata gốc vẫn nằm trong dữ liệu nội bộ để kiểm tra ảnh và khôi phục.

## Factory và kiểm thử

Có DestinationFactory, ItineraryPlaceFactory, HotelFactory, HotelImageFactory; TourFactory có state `destinationProgram($slug)` cho các chương trình mới. Factory dùng dữ liệu thật đã tuyển chọn với slug riêng để tạo bản ghi thử; HotelImageFactory chọn đúng ảnh theo tên khách sạn. Các factory phục vụ phát triển/kiểm thử; seeder dùng slug cố định để có thể chạy lại.

```sh
php vendor/bin/phpunit --filter 'DestinationDirectoryTest|CloudMediaTest|Supabase'
```

Kiểm thử bao gồm bảo toàn ID địa danh/lịch trình, tính lặp lại của seeder, phân loại và bộ lọc, phân trang, dữ liệu sai/404, ảnh đúng khách sạn, độ phủ không đồng đều giữa các điểm đến, và hiển thị bằng URL Supabase khi không có thư mục ảnh local. MySQL dùng cấu hình kiểm thử riêng `phpunit.mysql.xml`, database `TourDB_codex_test`.
