"""Run after local_http_journey.py against its disposable loopback fixture only."""
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://127.0.0.1:18081'
client = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path, data=None):
    encoded = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = client.open(urllib.request.Request(BASE + path, encoded))
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read().decode(), response.geturl(), response.headers

def token(body):
    return re.search(r'name="_token" value="([^"]+)"', body)[1]

credentials = {'username':'disposable-smoke-admin', 'password':'Disposable-Fixture-Only-82!'}
status, page, _, headers = request('/login.php')
assert status == 200 and 'no-store' in headers.get('Cache-Control', '')
old_token = token(page)
status, page, url, _ = request('/login.php', dict(credentials, _token='invalid'))
assert status == 200 and 'session=expired' in url and 'Anmeldeformular ist abgelaufen' in page
assert request('/index.php')[2].endswith('/login.php'), 'Invalid token must not authenticate'
status, _, url, _ = request('/login.php', dict(credentials, _token=token(page)))
assert status == 200 and url.endswith('/index.php')
assert request('/login.php')[2].endswith('/index.php'), 'Authenticated GET must not show another login form'
_, current_page, _, _ = request('/index.php')
status, _, url, _ = request('/login.php', {'_token':token(current_page), 'username':credentials['username'], 'password':'not-the-fixture-password'})
assert status == 200 and url.endswith('/index.php'), 'Duplicate authenticated POST must not damage the current session'
status, page, url, _ = request('/login.php', dict(credentials, _token=old_token))
assert status == 200 and url.endswith('/index.php'), 'Stale duplicate login must retain the current authenticated session'
status, _, _, _ = request('/terminals.php', {'_token':'invalid', 'action':'toggle'})
assert status == 403, 'Protected mutation must still reject invalid CSRF'
_, page, _, _ = request('/index.php')
status, _, url, _ = request('/logout.php', {'_token':token(page)})
assert status == 200 and url.endswith('/login.php')
status, page, url, _ = request('/login.php', dict(credentials, _token=old_token))
assert 'session=expired' in url and request('/index.php')[2].endswith('/login.php')
assert credentials['password'] not in page, 'Recovery must never echo submitted credentials'
print('PASS anonymous expiry, fresh login, duplicate login, logout expiry, no-store, no credential echo and protected CSRF rejection')
