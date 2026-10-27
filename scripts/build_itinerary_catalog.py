"""Build the reviewed itinerary/photograph snapshot; never use a search result blindly."""
import concurrent.futures
import hashlib
import json
import re
import sys
import unicodedata
from pathlib import Path
import requests
from research_itinerary_places import query, plain, HEADERS

ROOT=Path(__file__).resolve().parents[1]
RAW=ROOT/'storage/app/itinerary-research'
TARGET=ROOT/'public/images/itinerary'
TARGET.mkdir(parents=True,exist_ok=True)
blueprint=json.loads((ROOT/'database/data/itinerary-blueprint.json').read_text(encoding='utf-8'))
reviews=json.loads((RAW/'places-review.json').read_text(encoding='utf-8'))
existing=json.loads((ROOT/'database/data/tour-images.json').read_text(encoding='utf-8'))

REUSE={
 'Sa Pa':'sapa-1.jpg','Hồ Ba Bể':'ba-be-1.jpg','Văn Miếu – Quốc Tử Giám':'ha-noi-2.jpg',
 'Núi Bà Đen':'tay-ninh-1.jpg','Nhà cổ Bình Thủy':'can-tho-2.jpg','Mai Châu':'mai-chau-1.jpg',
 'Đồi chè Mộc Châu':'moc-chau-1.jpg','Bảo tàng Chiến thắng Điện Biên Phủ':'dien-bien-1.jpg',
 'Nghĩa trang Điện Biên Phủ':'dien-bien-2.jpg',
 'Hồ Xuân Hương':'da-lat-1.jpg',
}
FILES={
 'Chợ đêm Phú Quốc':'Fish for sale in Phu Quoc night market Vietnam.jpg',
 'Cố đô Hoa Lư':'Temple at ancient Vietnamese capital of Hoa Lu (10) (38469010452).jpg',
 'Bãi biển Nha Trang':'Nha Trang Beach 10.jpg','Bản Lác':'Cánh đồng bản Lác.jpg',
 'Bản Pác Ngòi':'Pac Ngoi rice field.JPG','Dương Đông':'Duong Dong Phu quoc, Kien Giang vietnam - panoramio.jpg',
 'Eo Gió':'Eo Gió - Nhơn Lý.jpg','Hầm Đờ Cát':'Bunker of Colonel de Castries - Dien Bien Phu - Vietnam - 02 (48168824237).jpg',
 'Hẻm Tu Sản':'TuSan Canyon.jpg','Hồ Đăk Ke':'Chiec-thuyen-ngap-nuoc-o-ho-dak-ke-mang-den.jpg',
 'Kỳ Co':'Ky Co beach, Quy Nhon city, Binh Dinh province, Vietnam.jpg',
 'Làng Kon Pring':'Cuoc-song-nguoi-dong-bao-tai-lang-kon-pring-thi-tran-mang-den-kon-tum-23.jpg',
 'Nghĩa trang Hàng Dương':'Nghĩa Trang Hàng Dương Võ Thị Sáu Côn Đảo Vũng Tàu (02-09-2014) - panoramio.jpg',
 'Thác Pa Sỹ':'Pa Sĩ Waterfall, Mang Den Vietnam.jpg',
 'Đồi A1':'French Bunker at Hill A1 (Eliane 2) - Dien Bien Phu - Vietnam (48168819887).jpg',
 'Hoàng thành Thăng Long':'Central Sector of the Imperial Citadel of Thang Long - Hanoi.jpg',
 'Động Puông':'Puong Cave, Ba Be.jpg',
 'Tràng An':'Tour boats at Trang An Landscape Complex, Ninh Binh Province, Vietnam, 20240202 1446 5308.jpg',
 'Thung lũng Mường Hoa':'Mường Hoa Valley 06.jpg',
}
# Sources were read and captions checked. These are attributed reference photos,
# not claimed to have an open license (unlike the Commons records).
ARTICLES={
 'Chợ Sa Pa':('sapa-market-article','https://static.vinwonders.com/production/cho-phien-sapa.jpg','Nguồn công bố: VinWonders; chưa xác định người chụp'),
 'Làng đá Non Nước':('non-nuoc-article','https://cdn3.ivivu.com/2022/09/lang-da-my-nghe-non-nuoc-ivivu-3.jpg','Vinpearl, dẫn qua iVIVU'),
 'Bãi Đầm Trầu':('exact-article-0','https://vetauphuquocexpress.com/wp-content/uploads/2024/04/den-bai-dam-trau-ngam-may-bay.jpg','Nguồn ảnh Internet, dẫn trên Cổng thông tin du lịch Côn Đảo; chưa xác định người chụp'),
 'Làng Kon Pring':('place-article-1','https://static.vinwonders.com/production/lang-du-lich-cong-dong-kon-pring-banner.jpg','Nguồn công bố: VinWonders; chưa xác định người chụp'),
 'Bản Đôn Pù Luông':('place-article-2','https://luhanhvietnam.com.vn/du-lich/vnt_upload/news/06_2024/ban_don_pu_luong_thien_nhien__.jpg','@puluongexpressbus, dẫn qua Lữ Hành Việt Nam'),
 'Mường Phăng':('exact-article-1','https://img.webbanve.net/photos/resized/1000x/1578-1619833959-vhavn.png','VHA.vn'),
 'Làng đá Khuổi Ky':('exact-article-2','https://cdn3.ivivu.com/2023/10/lang-da-khuoi-ky-ivivu-10.jpg','VOV, dẫn qua iVIVU'),
 'Vườn quốc gia Phú Quốc':('exact-article-3','https://static.vinwonders.com/2022/04/ZUNLOM8C-vuon-quoc-gia-phu-quoc-1.jpg','Nguồn công bố: VinWonders; chưa xác định người chụp'),
 'Rừng thông bản Áng':('exact-article-4','https://pystravel.vn/uploads/posts/albums/3754/b09a2685510bbfec4f94fb6e80ed82af.jpg','Nguồn công bố: PYS Travel; chưa xác định người chụp'),
}
CAPTIONS={
 'Măng Đen':'Thác Pa Sỹ, Măng Đen — ảnh bối cảnh khu vực',
 'Mộc Châu':'Thác Dải Yếm, Mộc Châu — ảnh bối cảnh khu vực',
 'Quy Nhơn':'Nhà thờ chính tòa Quy Nhơn — ảnh bối cảnh thành phố',
 'Đồng Văn':'Dốc Thẩm Mã — ảnh bối cảnh khu vực Đồng Văn',
 'Quản Bạ':'Tam Sơn — ảnh bối cảnh khu vực Quản Bạ',
 'Bảo tàng Chiến thắng Điện Biên Phủ':'Hiện vật trong Bảo tàng Chiến thắng Điện Biên Phủ',
 'Chùa Hang Lý Sơn':'Biển tên Chùa Hang, Lý Sơn',
 'Đảo Lý Sơn':'Cổng chào trên đảo Lý Sơn',
}

def slug(name):
 value=unicodedata.normalize('NFKD',name.replace('đ','d').replace('Đ','D')).encode('ascii','ignore').decode().lower()
 return re.sub('[^a-z0-9]+','-',value).strip('-')

def from_commons(filename):
 p=next(iter(query('commons.wikimedia.org',titles='File:'+filename,prop='imageinfo',iiprop='url|extmetadata|mime',iiurlwidth=1000)['pages'].values()))
 info=p['imageinfo'][0];meta=info['extmetadata']
 license_name=plain(meta.get('LicenseShortName',{}).get('value',''))
 if not re.search(r'CC BY|CC0|Public domain|GFDL',license_name,re.I) or re.search(r'\bNC\b|\bND\b|fair use',license_name,re.I):raise ValueError('Unreviewed image license: '+filename)
 if info['mime'] not in ['image/jpeg','image/png']:raise ValueError('Not a photograph: '+filename)
 return {'source_page':info['descriptionurl'],'download_url':info.get('thumburl',info['url']),
         'original_url':info['url'],'author':plain(meta.get('Artist',{}).get('value','')),
         'license':license_name,'license_url':meta.get('LicenseUrl',{}).get('value',''),
         'original_caption':plain(meta.get('ImageDescription',{}).get('value',''))}

def build(row):
 name=row['name'];key=slug(name)
 record={'slug':key,'name':name,'verified_on':'2026-10-28',
         'description':'Địa điểm có thật được đưa vào chương trình hiện có. Ngày, giờ và thứ tự tham quan là phương án cần thống nhất khi tư vấn.',
         'source_url':row.get('source_url'),'latitude':None,'longitude':None}
 coordinates=row.get('raw_page',{}).get('coordinates',[])
 if coordinates and row['status']!='ambiguous':record.update(latitude=coordinates[0]['lat'],longitude=coordinates[0]['lon'])
 if name in REUSE:
  old=next(x for x in existing if x['path'].endswith('/'+REUSE[name]))
  image={**old,'original_caption':old.get('description',old['landmark'])}
  source=(ROOT/'public'/old['path'].lstrip('/')).read_bytes()
  if name=='Hồ Xuân Hương':record['source_url']=old['source_page'];record['latitude']=record['longitude']=None
 elif name in ARTICLES:
  file,url,author=ARTICLES[name];article=json.loads((RAW/(file+'.json')).read_text(encoding='utf-8'))
  image={'source_page':article['url'],'download_url':url,'original_url':url,'author':author,
         'license':'Bản quyền chủ ảnh; chưa xác minh giấy phép mở','license_url':'','original_caption':name}
  record['source_url']=article['url'];record['latitude']=record['longitude']=None
  source=None
 else:
  image=from_commons(FILES[name]) if name in FILES else row.get('image')
  if not image or image['download_url'].lower().endswith('.svg') or re.search(r'location.map|relief.location|map\.png',image.get('file_title',''),re.I):raise ValueError('Missing reviewed photograph: '+name)
  source=None
 if not record['source_url'] or row['status'] in ['missing','ambiguous']:
  record['source_url']=image['source_page']
 # Do not reuse an administrative centroid as coordinates for a specific village/site.
 if row['status'] in ['missing','ambiguous']:record['latitude']=record['longitude']=None
 path=TARGET/(key+'.jpg')
 if not path.exists():
  if source is None:
   r=requests.get(image['download_url'],headers=HEADERS,timeout=40);r.raise_for_status();source=r.content
  if not (source.startswith(bytes.fromhex('ffd8ff')) or source.startswith(bytes.fromhex('89504e47'))):raise ValueError('Not a JPEG/PNG: '+name)
  path.write_bytes(source)
 image.update(path='/images/itinerary/'+path.name,sha256=hashlib.sha256(path.read_bytes()).hexdigest(),caption=CAPTIONS.get(name,name))
 record['image']=image
 return record

if __name__=='__main__':
 for row in reviews:
  if row['name']=='Nghĩa trang liệt sĩ A1':row['name']='Nghĩa trang Điện Biên Phủ'
 with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:places=list(pool.map(build,reviews))
 tours=json.loads((ROOT/'database/data/tours.json').read_text(encoding='utf-8'))
 audit=json.loads((ROOT/'storage/app/tour-itinerary-audit.json').read_text(encoding='utf-8'))
 known={t['title'] for t in tours};unknown=[t['title'] for t in audit if t['title'] not in known]
 if unknown:raise ValueError('Database contains unaudited tours: '+str(unknown))
 snapshots=[]
 for tour in tours:
  days=blueprint[tour['slug']]
  if len(days)!=len(tour['days']):raise ValueError('Day count mismatch: '+tour['slug'])
  live=next(t for t in audit if t['title']==tour['title'])
  if len(live['schedules'])!=len(days):raise ValueError('Live schedule mismatch: '+tour['slug'])
  snapshots.append({'slug':tour['slug'],'title':tour['title'],'days':[{'number':i+1,'title':d['title'],'activities':d['activities'],'places':[slug(n) for n in days[i]]} for i,d in enumerate(tour['days'])]})
 result={'verified_on':'2026-10-28','program_origin':'Phân ngày theo 30 tour/83 ngày đã có; không khẳng định là lịch bán của đơn vị nguồn.', 'places':places,'tours':snapshots}
 (ROOT/'database/data/tour-itineraries.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
 print(json.dumps({'tours':len(snapshots),'days':sum(len(t['days']) for t in snapshots),'places':len(places),'stops':sum(len(d['places']) for t in snapshots for d in t['days'])}))
