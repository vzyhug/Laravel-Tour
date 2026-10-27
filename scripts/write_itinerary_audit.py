"""Format the JSON returned by `php artisan itineraries:audit --json`."""
import json
from pathlib import Path

root=Path(__file__).resolve().parents[1]
report=json.loads((root/'storage/app/itinerary-research/final-audit.json').read_text(encoding='utf-8-sig'))
lines=['# Rà soát lịch trình toàn bộ tour','',
       'Đối chiếu database ngày 28/10/2026. Chương trình giữ theo lịch có sẵn; nguồn địa danh và ảnh nằm trong `database/data/tour-itineraries.json`.','',
       '| ID | Tour | Số ngày | Lượt ghé |','|---:|---|---:|---:|']
lines += [f"| {row['tour_id']} | {row['title']} | {row['days']} | {row['stops']} |" for row in report['rows']]
lines += ['', f"Tổng: **{report['tours']} tour, {report['days']} ngày, {report['stops']} lượt ghé, {report['images']} ảnh có attribution**. Số lỗi audit: **{len(report['problems'])}**.",'',
          'Chương trình gốc giữ nguyên. Nội dung chung chưa nêu cơ sở cụ thể (bữa ăn, nhận phòng, mua đặc sản, điểm đón) giữ thành ghi chú ngày; không giả danh địa điểm hoặc tự gán ảnh một cơ sở khác.','',
          'Chạy lại: `php artisan itineraries:audit --json`.']
(root/'docs/TOUR_ITINERARY_AUDIT.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')
catalog=json.loads((root/'database/data/tour-itineraries.json').read_text(encoding='utf-8'))
print(json.dumps({'photos':len(catalog['places']),'non_open_license':sum('chưa xác minh' in row['image']['license'] for row in catalog['places'])}))
