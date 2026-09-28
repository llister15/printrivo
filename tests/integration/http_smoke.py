"""Run against a disposable local instance: python3 tests/integration/http_smoke.py URL.
Requires MBER_TEST_EMAIL / MBER_TEST_PASSWORD for an active test subscriber.
Creates temporary QR logos in that instance; do not target production.
"""
import base64
import http.cookiejar
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

base = sys.argv[1].rstrip('/') + '/'
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def request(path, data=None, headers=None, opener=client):
    req = urllib.request.Request(urllib.parse.urljoin(base, path.lstrip('/')), data=data, headers=headers or {})
    try:
        response = opener.open(req)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read(), response.headers

def multipart(fields, filename, content, mime, field='image'):
    boundary = 'mber-' + uuid.uuid4().hex
    parts = []
    for key, value in fields.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    return b''.join(parts), {'Content-Type': 'multipart/form-data; boundary=' + boundary}

# Supply a disposable subscriber fixture; registration never creates paid access.
email = os.environ.get('MBER_TEST_EMAIL')
password = os.environ.get('MBER_TEST_PASSWORD')
assert email and password, 'Set MBER_TEST_EMAIL and MBER_TEST_PASSWORD for a test subscriber.'
code, body, _ = request('login')
token = re.search(rb'name="mber_csrf" value="([^"]+)"', body)[1].decode()
credentials = urllib.parse.urlencode({'email': email, 'password': password, 'mber_csrf': token}).encode()
code, body, _ = request('login', credentials, {'Content-Type': 'application/x-www-form-urlencoded'})
assert code == 200 and b'Your dashboard' in body, 'Test subscriber login failed.'

for path in ['', 'dashboard', 'tools/qr', 'tools/gang-sheet', 'tools/image-studio', 'qrcode/', 'qrcode/index.php', 'dtf/', 'dtf/index.php', 'dtf/builder.php', 'cleanimg/', 'cleanimg/index.php']:
    code, body, _ = request(path)
    assert code == 200, (path, code)
    assert b'Printrivo' in body, path
    for asset in re.findall(rb'(?:src|href)="([^"]+/assets/[^"?]+)', body):
        assert request(asset.decode())[0] == 200, asset
print('PASS: canonical and legacy pages, redirects and assets')
code, body, _ = request('tools/qr')
csrf = re.search(rb'name="csrf-token" content="([^"]+)"', body)[1].decode()
header = re.search(rb'name="csrf-header" content="([^"]+)"', body)[1].decode()
csrf_headers = {header: csrf, 'X-Requested-With': 'XMLHttpRequest'}
for path in ['tools/qr', 'qrcode/index.php', 'dtf/upload.php', 'api/tools/gang-sheet/inspect']:
    assert request(path, b'content=test')[0] == 403, path
print('PASS: CSRF rejection on all POST aliases')
for path in ['tools/qr', 'qrcode/index.php']:
    data = urllib.parse.urlencode({'content': 'https://mberdigital.com/', 'logo_select': 'instagram.svg'}).encode()
    code, body, _ = request(path, data, {**csrf_headers, 'Content-Type': 'application/x-www-form-urlencoded'})
    assert code == 200 and b'data:image' in body and b'base64,' in body, (path, code)
print('PASS: QR generation and built-in icon')
# A valid 1x1 PNG is sufficient to exercise the actual multipart metadata path.
png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=')
for path in ['dtf/upload.php', 'api/tools/gang-sheet/inspect']:
    data, headers = multipart({}, 'sample.png', png, 'image/png')
    code, body, _ = request(path, data, {**headers, **csrf_headers})
    result = json.loads(body)
    assert code == 200 and result['ok'] and result['image']['widthPx'] == 1, (code, body)
    code, body, _ = request(path, b'', csrf_headers)
    assert code == 400 and json.loads(body)['ok'] is False
print('PASS: multipart upload and missing-file JSON errors')
assert request('dtf/upload.php')[0] == 405
for path in ['api/tools/gang-sheet/rate', 'dtf/builder.php']:
    assert request(path, b'{}', {**csrf_headers, 'Content-Type': 'application/json'})[0] == 404
print('PASS: retired pricing endpoints return 404')
svg = b'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><script>alert(1)</script><rect width="24" height="24" fill="red"/></svg>'
data, headers = multipart({'content': 'Mber Digital', 'custom_logo_checkbox': '1'}, 'logo.svg', svg, 'image/svg+xml', 'custom_logo')
code, body, _ = request('tools/qr', data, {**headers, **csrf_headers})
assert code == 200
logo = re.search(rb'src="([^"]+/files/qr-logo/[^"?]+)', body)
assert logo, body[:1000]
code, body, headers = request(logo[1].decode())
assert code == 200 and b'<script' not in body and b'<svg' in body
assert headers['X-Content-Type-Options'] == 'nosniff'
assert b'Log in' in request(logo[1].decode(), opener=urllib.request.build_opener())[1]
print('PASS: custom SVG sanitization, preview serving and session isolation')
for path in ['app/Config/App.php', '.env', 'dtf/customers.json', 'vendor/composer/installed.json', 'gangsheet/rate']:
    assert request(path)[0] in (403, 404), path
print('PASS: private files and automatic controller routing blocked')
