"""Expose only SePay sandbox IPN, signed phone checkout, and a static return page, never the whole Laravel site.
Run: python scripts/sepay-sandbox-gateway.py --port 8010 --upstream-port 8001
Then: ngrok http http://127.0.0.1:8010 --inspect=false
The gateway binds loopback and never logs headers or callback bodies.
"""
import argparse
import http.client
import json
import re
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit

IPN = '/payments/sepay-sandbox/ipn'
RETURN = '/sepay-sandbox/return'
RETURN_HTML = (Path(__file__).resolve().parents[1] / 'resources/views/booking/sepay-return.blade.php').read_bytes()

class Gateway(BaseHTTPRequestHandler):
    server_version = 'VietGoSandbox'

    def log_message(self, *_):
        pass

    def respond(self, code, body, kind='application/json; charset=utf-8'):
        self.send_response(code)
        self.send_header('Content-Type', kind)
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('Referrer-Policy', 'no-referrer')
        self.send_header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; form-action https://pay-sandbox.sepay.vn; frame-ancestors 'none'")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if urlsplit(self.path).path == RETURN:
            self.respond(200, RETURN_HTML, 'text/html; charset=utf-8')
        elif re.fullmatch(r'/sepay-sandbox/checkout/[0-9A-HJKMNP-TV-Z]{26}', urlsplit(self.path).path, re.IGNORECASE):
            connection = http.client.HTTPConnection('127.0.0.1', self.server.upstream_port, timeout=15)
            try:
                connection.request('GET', self.path, headers={'Accept': 'text/html'})
                response = connection.getresponse()
                if response.status == 200:
                    body = response.read(32769)
                    if len(body) > 32768:
                        self.respond(502, b'{"success":false}')
                    else:
                        self.respond(200, body, 'text/html; charset=utf-8')
                else:
                    self.respond(response.status if response.status in (403, 404, 410, 429) else 502, b'{"success":false}')
            except (OSError, http.client.HTTPException):
                self.respond(502, b'{"success":false}')
            finally:
                connection.close()
        else:
            self.respond(404, b'{"success":false}')

    def do_POST(self):
        if self.path != IPN:
            self.respond(404, b'{"success":false}')
            return
        if self.headers.get_content_type() != 'application/json':
            self.respond(415, b'{"success":false}')
            return
        if self.headers.get('Transfer-Encoding'):
            self.respond(400, b'{"success":false}')
            return
        try:
            length = int(self.headers.get('Content-Length', '0'))
        except ValueError:
            length = -1
        if length < 1 or length > 32768:
            self.respond(413, b'{"success":false}')
            return
        secret = self.headers.get('X-Secret-Key', '')
        if not secret or len(secret) > 512:
            self.respond(401, b'{"success":false}')
            return
        connection = http.client.HTTPConnection('127.0.0.1', self.server.upstream_port, timeout=15)
        try:
            connection.request('POST', IPN, body=self.rfile.read(length), headers={
                'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Secret-Key': secret})
            response = connection.getresponse()
            status = response.status
            if status == 200:
                valid = json.loads(response.read(1024)).get('success') is True
                self.respond(200 if valid else 502, b'{"success":true}' if valid else b'{"success":false}')
            else:
                # No Laravel debug pages, session cookies or internal errors cross the public gateway.
                self.respond(status if status in (401, 404, 409, 413, 415, 422, 429) else 502, b'{"success":false}')
        except (OSError, ValueError, http.client.HTTPException):
            self.respond(502, b'{"success":false}')
        finally:
            connection.close()

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--port', type=int, default=8010)
    parser.add_argument('--upstream-port', type=int, default=8001)
    args = parser.parse_args()
    if not all(1024 <= port <= 65535 for port in (args.port, args.upstream_port)) or args.port == args.upstream_port:
        parser.error('Choose different valid local TCP ports')
    server = ThreadingHTTPServer(('127.0.0.1', args.port), Gateway)
    server.upstream_port = args.upstream_port
    server.daemon_threads = True
    print(f'SePay sandbox gateway on 127.0.0.1:{args.port}; only IPN POST, signed phone checkout GET, and static return GET are allowed.', flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
