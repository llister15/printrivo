"""Local-only account smoke test. Creates one disposable member and removes it.
Usage: python3 tests/integration/account_smoke.py http://localhost/tools/ writable/mber.sqlite
"""
import http.cookiejar
import json
import re
import secrets
import sqlite3
import sys
import urllib.error
import urllib.parse
import urllib.request

base = sys.argv[1].rstrip('/') + '/'
assert urllib.parse.urlparse(base).hostname in ('localhost', '127.0.0.1'), 'Local disposable instance only'
db = sqlite3.connect(sys.argv[2])
db.execute('PRAGMA foreign_keys=ON')
email = 'smoke-' + secrets.token_hex(8) + '@example.test'
password = secrets.token_urlsafe(24) + '9!aZ'
jar = http.cookiejar.CookieJar()
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())
def request(path, fields=None, headers=None):
    url = urllib.parse.urljoin(base, path)
    data = None if fields is None else urllib.parse.urlencode(fields).encode()
    try: response = client.open(urllib.request.Request(url, data, headers or {}))
    except urllib.error.HTTPError as error: response = error
    return response.status, response.read().decode(), response.headers

def token(page):
    code, body, _ = request(page)
    assert code == 200, (page, code)
    match = re.search(r'name="mber_csrf" value="([^"]+)"', body)
    assert match, page
    return match[1]

try:
    code, body, _ = request('')
    assert code == 200 and 'Professional Print Tools.' in body and 'Payment setup required' in body
    assert 'debugbar_loader' not in body
    for page in ['dashboard', 'tools/qr', 'qrcode/index.php', 'dtf/builder.php', 'cleanimg/index.php']:
        code, _, headers = request(page)
        assert code == 302 and headers['Location'].endswith('/login'), (page, code)
    print('PASS: public sales page and anonymous dashboard/tool redirects')
    csrf = token('register')
    code, _, _ = request('register', {'email': email, 'password': password, 'password_confirm': password, 'first_name': 'Smoke', 'last_name': 'Member'})
    assert code == 403
    code, _, _ = request('register', {'mber_csrf': csrf, 'email': email, 'password': password, 'password_confirm': password, 'first_name': 'Smoke', 'last_name': 'Member', 'promo_code': 'invalid-code'})
    assert code in (302,303)
    assert db.execute('SELECT COUNT(*) FROM auth_identities WHERE secret=?', (email,)).fetchone()[0] == 0
    csrf = token('register')
    promo = '  aLpHa  ' if len(sys.argv) < 4 else ''
    code, _, headers = request('register', {'mber_csrf': csrf, 'email': email, 'password': password, 'password_confirm': password, 'first_name': 'Smoke', 'last_name': 'Member', 'promo_code': promo})
    assert code in (302, 303) and headers['Location'].endswith('/account/subscription'), (code, headers.get('Location'))
    user_id, stored_hash = db.execute("SELECT user_id,secret2 FROM auth_identities WHERE type='email_password' AND secret=?", (email,)).fetchone()
    assert db.execute('SELECT promo_code FROM users WHERE id=?', (user_id,)).fetchone()[0] == ('ALPHA' if promo else None)
    assert stored_hash != password and stored_hash.startswith('$')
    assert db.execute('SELECT COUNT(*) FROM subscriptions WHERE user_id=?', (user_id,)).fetchone()[0] == 0
    code, body, _ = request('dashboard')
    assert code == 200 and 'Welcome, Smoke' in body and 'Subscription required' in body
    print('PASS: registration, password hashing and private dashboard; no subscription fabricated')
    for page in ['tools/qr', 'qrcode/index.php', 'tools/gang-sheet', 'dtf/builder.php', 'tools/image-studio', 'cleanimg/index.php']:
        code, _, headers = request(page)
        assert code == 302 and headers['Location'].endswith('/account/subscription'), page
    csrf = token('dashboard')
    code, body, _ = request('api/tools/gang-sheet/inspect', {'mber_csrf': csrf}, {'X-Requested-With': 'XMLHttpRequest'})
    assert code == 403 and json.loads(body)['ok'] is False
    assert request('logout', {})[0] == 403
    assert request('logout', {'mber_csrf': csrf})[0] in (302, 303)
    assert request('dashboard')[0] == 302
    print('PASS: unsubscribed users blocked, API JSON denial and CSRF-protected logout')
    csrf = token('login')
    code, _, headers = request('login', {'mber_csrf': csrf, 'email': email, 'password': 'Wrong!Password987'})
    assert code in (302, 303) and headers['Location'].endswith('/login')
    csrf = token('login')
    code, _, headers = request('login', {'mber_csrf': csrf, 'email': email, 'password': password})
    assert code in (302, 303) and headers['Location'].endswith('/dashboard')
    assert request('dashboard')[0] == 200
    db.execute('UPDATE users SET active=0 WHERE id=?', (user_id,));db.commit()
    assert request('dashboard')[0] == 302
    print('PASS: invalid/valid login and disabled-account denial')
finally:
    row = db.execute('SELECT user_id FROM auth_identities WHERE secret=?', (email,)).fetchone()
    if row:
        db.execute('DELETE FROM users WHERE id=?', (row[0],))
    db.execute('DELETE FROM auth_logins WHERE identifier=?', (email,))
    db.commit();db.close()
