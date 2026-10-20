"""Download destination photos and retain Wikimedia attribution (Python 3, stdlib only)."""
import argparse
import hashlib
import html
import json
import re
import time
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
HEADERS = {"User-Agent": "VietGoDemoAssets/1.0 (https://github.com/vzyhug/Laravel-Tour)"}


def fetch(url):
    for attempt in range(4):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=HEADERS), timeout=40) as response:
                return response.read()
        except Exception:
            if attempt == 3:
                raise
            time.sleep(2 ** attempt)


def query(host, **params):
    url = "https://" + host + "/w/api.php?" + urllib.parse.urlencode({"action": "query", "format": "json", **params})
    return json.loads(fetch(url))["query"]


def plain(value):
    return html.unescape(re.sub("<[^>]+>", "", value)).strip()


def discover():
    sources = json.loads((ROOT / "database/data/tour-image-sources.json").read_text(encoding="utf-8"))
    previous = ROOT / "database/data/tour-images.json"
    cached = json.loads(previous.read_text(encoding="utf-8")) if previous.exists() else []
    entries = []
    for destination, titles in sources.items():
        for number, title in enumerate(titles, 1):
            if isinstance(title, dict):
                image_title = title["file"]
                title = title["landmark"]
            else:
                pages = query("en.wikipedia.org", titles=title, prop="pageimages", redirects=1, piprop="name")["pages"]
                page = next(iter(pages.values()))
                if "pageimage" not in page:
                    raise RuntimeError("No lead photo: " + title)
                image_title = "File:" + page["pageimage"]
            pages = query("commons.wikimedia.org", titles=image_title, prop="imageinfo", iiprop="url|extmetadata|mime", iiurlwidth=1200)["pages"]
            info = next(iter(pages.values()))["imageinfo"][0]
            meta = info["extmetadata"]
            license_name = plain(meta.get("LicenseShortName", {}).get("value", ""))
            if not license_name or any(part in license_name.lower() for part in ["noncommercial", "fair use", "nc-", "-nc", "-nd"]):
                raise RuntimeError("Unsupported license: " + image_title + " " + license_name)
            url = info.get("thumburl", info["url"])
            ext = ".png" if info["mime"] == "image/png" else ".jpg"
            entry = {
                "destination": destination,
                "landmark": title,
                "path": "/images/tours/demo/" + destination + "-" + str(number) + ext,
                "source_page": info["descriptionurl"],
                "download_url": url,
                "original_url": info["url"],
                "author": plain(meta.get("Artist", {}).get("value", "Unknown")),
                "license": license_name,
                "license_url": meta.get("LicenseUrl", {}).get("value", ""),
                "description": plain(meta.get("ImageDescription", {}).get("value", title)),
                "modifications": "Wikimedia thumbnail; no other edits",
            }
            old = next((item for item in cached if item["path"] == entry["path"] and item["original_url"] == entry["original_url"]), {})
            if "sha256" in old:
                entry["sha256"] = old["sha256"]
            entries.append(entry)
            print(destination, number, image_title, license_name, flush=True)
    return entries


def download(entries):
    for entry in entries:
        target = ROOT / "public" / entry["path"].lstrip("/")
        target.parent.mkdir(parents=True, exist_ok=True)
        if target.exists() and entry.get("sha256") == hashlib.sha256(target.read_bytes()).hexdigest():
            continue
        data = fetch(entry["download_url"])
        if not (data.startswith(bytes.fromhex("ffd8ff")) or data.startswith(bytes.fromhex("89504e47"))):
            raise RuntimeError("Expected JPEG/PNG photo: " + entry["download_url"])
        target.write_bytes(data)
        entry["sha256"] = hashlib.sha256(data).hexdigest()
        print("Downloaded", target.name, len(data), "bytes", flush=True)
        time.sleep(0.5)
    (ROOT / "database/data/tour-images.json").write_text(json.dumps(entries, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    lines = ["# Nguồn ảnh địa danh", "", "Ảnh được tải từ Wikimedia Commons; đường dẫn bản gốc, tác giả và giấy phép được giữ trong `database/data/tour-images.json`.", "Ảnh chỉ được thu nhỏ bởi Wikimedia. Khi tái sử dụng, giữ thông tin tác giả, liên kết nguồn và giấy phép tương ứng.", ""]
    for entry in entries:
        lines += ["## " + Path(entry["path"]).name, "", "- Địa danh: " + entry["landmark"], "- Tác giả: " + entry["author"], "- Nguồn: " + entry["source_page"], "- Giấy phép: " + entry["license"] + " " + entry["license_url"], ""]
    (ROOT / "public/images/tours/demo/ATTRIBUTION.md").write_text("\n".join(line.rstrip() for line in lines), encoding="utf-8")
    cards = []
    for entry in entries:
        esc = html.escape
        filename = Path(entry["path"]).name
        cards.append(
            '<article><img loading="lazy" src="' + esc(filename) + '" alt="' + esc(entry["landmark"]) + '">'
            + '<h2>' + esc(entry["landmark"]) + '</h2><p>Tác giả: ' + esc(entry["author"]) + '</p>'
            + '<p><a href="' + esc(entry["source_page"]) + '">Nguồn trên Wikimedia Commons</a> · '
            + '<a href="' + esc(entry["license_url"] or entry["source_page"]) + '">' + esc(entry["license"]) + '</a></p>'
            + '<p class="note">Ảnh thu nhỏ bởi Wikimedia; không chỉnh sửa thêm.</p></article>'
        )
    page = '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    page += '<title>Nguồn ảnh địa danh · VietGo</title><style>body{font:16px/1.6 system-ui,sans-serif;margin:0;background:#f4f7fa;color:#15283b}main{max-width:1150px;margin:auto;padding:32px 20px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}article{background:white;padding:16px;border-radius:16px}img{width:100%;height:200px;object-fit:cover;border-radius:8px}h1{font-size:30px}h2{font-size:20px}a{color:#0a647b}.note{font-size:13px;color:#526477}</style>'
    page += '<main><a href="/">← VietGo</a><h1>Nguồn ảnh địa danh</h1><p>Thông tin tác giả, nguồn gốc và giấy phép của các ảnh địa danh được sử dụng trong bộ tour mẫu.</p><div class="grid">' + ''.join(cards) + '</div></main></html>'
    (ROOT / "public/images/tours/demo/credits.html").write_text(page, encoding="utf-8")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--discover", action="store_true", help="Refresh sources from Wikipedia/Commons before downloading")
    args = parser.parse_args()
    manifest = ROOT / "database/data/tour-images.json"
    entries = discover() if args.discover or not manifest.exists() else json.loads(manifest.read_text(encoding="utf-8"))
    download(entries)
