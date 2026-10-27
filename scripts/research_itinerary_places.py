"""Resolve real itinerary landmarks and licensed lead photographs via Wikimedia APIs.

Outputs a review queue. Missing/ambiguous pages are never silently substituted.
Run: python -X utf8 scripts/research_itinerary_places.py
"""
import concurrent.futures
import hashlib
import html
import json
import re
import time
from pathlib import Path
import requests

ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT/'storage/app/itinerary-research'
CACHE.mkdir(parents=True,exist_ok=True)
HEADERS = {'User-Agent':'VietGoItinerary/1.0 (https://github.com/vzyhug/Laravel-Tour)'}
ALIASES = {
 'Hồ Xuân Hương':'Hồ Xuân Hương (Đà Lạt)',
 'Sông Hàn':'Sông Hàn (Đà Nẵng)',
 'Buôn Ma Thuột':'Buôn Ma Thuột (thành phố)', 'Sa Pa':'Sa Pa (thị xã)',
 'Cầu Vàng':'Cầu Vàng', 'Kỳ Co':'Bãi Kỳ Co', 'Suối Tiên Mũi Né':'Suối Tiên (Mũi Né)',
 'Thác Dray Nur':'Thác Đray Nur', 'Tràng An':'Quần thể danh thắng Tràng An',
 'Mù Cang Chải':'Ruộng bậc thang Mù Cang Chải', 'Mộc Châu':'Mộc Châu (thị xã)',
 'Nghĩa Lộ':'Nghĩa Lộ (thị xã)', 'Quy Nhơn':'Quy Nhơn (thành phố)',
 'Quản Bạ':'Quản Bạ (huyện)', 'Đồng Văn':'Đồng Văn (huyện)',
 'Bản Cát Cát':'Cát Cát', 'Bà Nà Hills':'Bà Nà',
 'Biển Mỹ Khê':'Bãi biển Mỹ Khê', 'Thiền viện Trúc Lâm Đà Lạt':'Thiền viện Trúc Lâm (Đà Lạt)',
 'Tam Cốc':'Tam Cốc – Bích Động', 'Dinh thự họ Vương':'Dinh thự họ Vương',
 'Văn Miếu – Quốc Tử Giám':'Văn Miếu – Quốc Tử Giám', 'Đại Nội Huế':'Hoàng thành Huế',
 'Tháp Bà Ponagar':'Tháp Po Nagar', 'Động Thiên Đường':'Động Thiên Đường',
 'Đồi cát Mũi Né':'Đồi cát Mũi Né',
 'Đảo Cát Bà':'Cát Bà (đảo)', 'Pù Luông':'Khu bảo tồn thiên nhiên Pù Luông',
 'Đảo Lý Sơn':'Lý Sơn', 'Chùa Hang Lý Sơn':'Chùa Hang (Quảng Ngãi)',
 'Nghĩa trang liệt sĩ A1':'Nghĩa trang liệt sĩ A1',
 'Bản Đôn Pù Luông':'Bản Đôn (Thanh Hóa)', 'Cà Mau':'Cà Mau (thành phố)',
 'Bến Tre':'Bến Tre (thành phố)', 'Cao Bằng':'Cao Bằng (thành phố)',
}

def plain(s):
 return html.unescape(re.sub('<[^>]+>','',s or '')).strip()

def query(host,**params):
 for attempt in range(3):
  try:
   response=requests.get('https://'+host+'/w/api.php',params={'action':'query','format':'json',**params},headers=HEADERS,timeout=25)
   response.raise_for_status()
   return response.json()['query']
  except Exception:
   if attempt==2:raise
   time.sleep(1)

def inspect(name):
 path=CACHE/(hashlib.sha256(name.encode()).hexdigest()[:16]+'.json')
 if path.exists():
  cached=json.loads(path.read_text(encoding='utf-8'))
  if cached.get('wiki_title')==ALIASES.get(name,name):return cached
 result={'name':name,'wiki_title':ALIASES.get(name,name),'status':'missing'}
 try:
  q=query('vi.wikipedia.org',titles=result['wiki_title'],redirects=1,prop='pageimages|coordinates|info|extracts|pageprops',
          inprop='url',piprop='name|thumbnail',pithumbsize=1000,exintro=1,explaintext=1,exchars=1400)
  page=next(iter(q['pages'].values()))
  result['raw_page']=page
  if 'missing' in page:return result
  result.update(status='needs_image',source_url=page['fullurl'],resolved_title=page['title'])
  if 'disambiguation' in page.get('pageprops',{}):result['status']='ambiguous';return result
  if 'pageimage' not in page:return result
  iq=query('commons.wikimedia.org',titles='File:'+page['pageimage'],prop='imageinfo',iiprop='url|extmetadata|mime',iiurlwidth=1000)
  image=next(iter(iq['pages'].values())).get('imageinfo',[None])[0]
  if not image:return result
  meta=image['extmetadata'];license_name=plain(meta.get('LicenseShortName',{}).get('value',''))
  if not re.search(r'CC BY|CC0|Public domain|GFDL',license_name,re.I) or re.search(r'\bNC\b|\bND\b|fair use',license_name,re.I):return result
  result.update(status='ready',image={'source_page':image['descriptionurl'],'download_url':image.get('thumburl',image['url']),
       'original_url':image['url'],'file_title':page['pageimage'],'author':plain(meta.get('Artist',{}).get('value','')),
       'license':license_name,'license_url':meta.get('LicenseUrl',{}).get('value',''),
       'caption':plain(meta.get('ImageDescription',{}).get('value',''))})
 except Exception as exc:result.update(status='error',error=str(exc))
 finally:path.write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
 return result

if __name__=='__main__':
 blueprint=json.loads((ROOT/'database/data/itinerary-blueprint.json').read_text(encoding='utf-8'))
 names=sorted({n for days in blueprint.values() for day in days for n in day})
 with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
  records=list(pool.map(inspect,names))
 (CACHE/'places-review.json').write_text(json.dumps(records,ensure_ascii=False,indent=2),encoding='utf-8')
 from collections import Counter
 print(json.dumps(dict(Counter(r['status'] for r in records))))
 for row in records:
  if row['status']!='ready':print(row['status'],row['name'],row.get('error',''))
