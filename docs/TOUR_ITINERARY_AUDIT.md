# Rà soát lịch trình toàn bộ tour

Đối chiếu database ngày 28/10/2026. Chương trình giữ theo lịch có sẵn; nguồn địa danh và ảnh nằm trong `database/data/tour-itineraries.json`.

| ID | Tour | Số ngày | Lượt ghé |
|---:|---|---:|---:|
| 1 | Khám phá Vịnh Hạ Long | 2 | 3 |
| 2 | Sapa – Thành phố trong sương | 3 | 5 |
| 3 | Đà Nẵng – Hội An – Bà Nà Hills | 4 | 9 |
| 4 | Đà Lạt – Thành phố ngàn hoa | 3 | 5 |
| 5 | Phú Quốc – Thiên đường biển đảo | 4 | 5 |
| 6 | Ninh Bình – Tràng An – Tam Cốc | 2 | 3 |
| 7 | Hà Giang – Đồng Văn – Mã Pí Lèng | 3 | 6 |
| 8 | Hà Nội – Hồ Gươm – Văn Miếu – Phố cổ | 2 | 5 |
| 9 | Huế – Đại Nội – Chùa Thiên Mụ – Sông Hương | 3 | 6 |
| 10 | Nha Trang – Vịnh biển – Tháp Bà Ponagar | 3 | 3 |
| 11 | Quy Nhơn – Kỳ Co – Eo Gió – Tháp Bánh Ít | 3 | 5 |
| 12 | Cần Thơ – Chợ nổi Cái Răng – Nhà cổ Bình Thủy | 2 | 3 |
| 13 | Côn Đảo – Biển xanh – Di tích lịch sử | 3 | 4 |
| 14 | Phong Nha – Động Thiên Đường – Sông Son | 3 | 3 |
| 15 | Mũi Né – Đồi cát – Tháp Po Sah Inư | 3 | 4 |
| 16 | Cao Bằng – Bản Giốc – Pác Bó – Động Ngườm Ngao | 3 | 5 |
| 17 | Ba Bể – Hồ xanh – Động Puông – Bản Pác Ngòi | 3 | 4 |
| 18 | Mộc Châu – Đồi chè – Thác Dải Yếm – Rừng thông | 3 | 4 |
| 19 | Mai Châu – Bản Lác – Thung lũng lúa – Nhà sàn | 2 | 2 |
| 20 | Mù Cang Chải – Khau Phạ – Chế Cu Nha | 3 | 5 |
| 21 | Điện Biên – Đồi A1 – Bảo tàng Chiến thắng – Mường Phăng | 3 | 5 |
| 22 | Cát Bà – Vịnh Lan Hạ – Vườn quốc gia | 3 | 3 |
| 23 | Pù Luông – Bản Đôn – Đồng lúa – Bản làng | 2 | 3 |
| 24 | Lý Sơn – Đảo núi lửa – Chùa Hang – Biển xanh | 3 | 4 |
| 25 | Măng Đen – Hồ Đăk Ke – Thác Pa Sỹ – Rừng thông | 3 | 4 |
| 26 | Buôn Ma Thuột – Thác Dray Nur – Văn hóa cà phê | 3 | 3 |
| 27 | Cúc Phương – Rừng nguyên sinh – Khám phá sinh thái | 2 | 2 |
| 28 | Tây Ninh – Núi Bà Đen – Tòa Thánh | 2 | 2 |
| 29 | Bến Tre – Sông nước xứ dừa – Làng nghề | 2 | 2 |
| 30 | Cà Mau – Đất Mũi – Bãi bồi – Rừng ngập mặn | 3 | 3 |

Tổng: **30 tour, 83 ngày, 120 lượt ghé, 112 ảnh có attribution**. Số lỗi audit: **0**.

Chương trình gốc giữ nguyên. Nội dung chung chưa nêu cơ sở cụ thể (bữa ăn, nhận phòng, mua đặc sản, điểm đón) giữ thành ghi chú ngày; không giả danh địa điểm hoặc tự gán ảnh một cơ sở khác.

Chạy lại: `php artisan itineraries:audit --json`.
