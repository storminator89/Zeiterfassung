"""Read-only render checks using the known disposable account from local_http_journey.py."""
import argparse, http.cookiejar, urllib.request, urllib.parse, re, json
from html.parser import HTMLParser
parser=argparse.ArgumentParser();parser.add_argument('--base',default='http://127.0.0.1:18081');args=parser.parse_args()
client=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(path):
    with client.open(args.base+path,timeout=15) as r:
        assert r.status==200
        return r.read().decode()
page=get('/login.php');token=re.search(r'name="_token" value="([^"]+)"',page)[1]
with client.open(args.base+'/login.php',urllib.parse.urlencode({'_token':token,'username':'disposable-smoke-admin','password':'Disposable-Fixture-Only-82!'}).encode(),timeout=15) as r:assert r.status==200
class Panes(HTMLParser):
    def __init__(self):super().__init__();self.visible=[]
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if 'data-admin-pane' in attrs and 'hidden' not in attrs:self.visible.append(attrs['data-admin-pane'])
for path in ['/index.php','/dashboard.php','/adjustments.php','/work_models.php','/history.php','/supervisor.php','/settings.php','/terminals.php','/vacation.php','/get_time_records.php']:
    page=get(path);assert '����' not in page and 'Undefined variable' not in page,path
for selected in ['users','departments','rules','access','system']:
    page=get('/admin.php?section='+selected);panes=Panes();panes.feed(page);assert panes.visible==[selected],(selected,panes.visible)
summary=json.loads(get('/attendance.php?format=status&home_balance=1'))
assert summary['home_balance']['presentation'] in ['current','unresolved','incomplete']
assert isinstance(summary['home_balance']['label'],str) and summary['report_finality'].keys() >= {'all','week','month','year'}
page=get('/index.php');assert 'Sichere Ereignis-ID nicht verfügbar' not in page and 'UTF-8-Bytes' not in page
# Check the accessible balance name; Home no longer requires accordion styling.
assert 'Erfasster Saldo' in page
print('PASS all ten page/fragment renders, five isolated admin areas and concise Home labels')
