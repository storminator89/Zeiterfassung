"""Integration test for a NEW disposable instance on 127.0.0.1:18081 only.
Prepare with CHRONO_DATA_DIR=/tmp/chrono-test CHRONO_HOST=127.0.0.1
CHRONO_HTTP_PORT=18081 CHRONO_PROJECT_NAME=chrono-test ./start.sh.
Never run against a real installation. The test creates fixed disposable accounts.
"""
import urllib.request, urllib.parse, urllib.error, http.cookiejar, re
base='http://127.0.0.1:18081'
client=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(path):
    with client.open(base+path) as r:return r.read().decode()
def token(body):return re.search(r'name="_token" value="([^"]+)"',body)[1]
def post(path,data):
    request=urllib.request.Request(base+path,urllib.parse.urlencode(data).encode())
    try:
        with client.open(request) as r:return r.status,r.read().decode(),r.geturl()
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url
page=get('/register.php')
assert 'name="setup_token"' not in page
status,body,url=post('/setup.php',{'_token':token(page),'username':'disposable-smoke-admin','email':'disposable@example.invalid','password':'Disposable-Fixture-Only-82!','password_confirmation':'Disposable-Fixture-Only-82!'})
assert status==200 and 'setup=complete' in url,(status,url,body[:100])
page=get('/login.php')
status,body,url=post('/login.php',{'_token':token(page),'username':'disposable-smoke-admin','password':'Disposable-Fixture-Only-82!'})
assert status==200 and 'login.php' not in url,(status,url)
page=get('/terminals.php')
assert re.search(r'name="enabled"[^>]*checked',page), 'Terminal must default to enabled after setup'
assert 'name="pin"' in page
csrf=token(page)
status,_,_=post('/terminals.php',{'_token':csrf,'action':'site','name':'Smoke site'})
assert status==200
page=get('/terminals.php');site=re.search(r'<option value="(\d+)">Smoke site</option>',page)[1]
status,body,_=post('/terminals.php',{'_token':token(page),'action':'credential','user_id':'1','site_id':site,'employee_code':'smoke01','pin':'00000000','card':''})
assert status==200 and 'smoke01' in body,'Fixture PIN provisioning failed'
page=get('/terminals.php')
status,body,_=post('/terminals.php',{'_token':token(page),'action':'device','name':'Disposable fixture device','site_id':site})
assert status==200
code=re.search(r'<code>([a-f0-9]+)</code>',body)[1]
device=re.search(r'Gerät\s+(\d+)\s+koppeln',body)[1]
import json, hashlib, hmac, time, secrets
raw=json.dumps({'op':'pair','device_id':int(device),'code':code}).encode()
with client.open(urllib.request.Request(base+'/terminal_api.php',raw,{'Content-Type':'application/json'})) as r:pair=json.load(r)
assert pair.get('success'),pair
raw=b'{"op":"heartbeat"}';stamp=str(int(time.time()));nonce=secrets.token_hex(16)
signature=hmac.new(pair['key'].encode(),f'terminal-v1\n{device}\n{stamp}\n{nonce}\n'.encode()+raw,hashlib.sha256).hexdigest()
headers={'Content-Type':'application/json','X-Terminal-Id':device,'X-Terminal-Time':stamp,'X-Terminal-Nonce':nonce,'X-Terminal-Signature':signature}
with client.open(urllib.request.Request(base+'/terminal_api.php',raw,headers)) as r:assert json.load(r).get('success')
status,_,_=post('/terminals.php',{'_token':'invalid','action':'toggle'})
assert status==403,'CSRF bypass'
print('PASS fresh HTTP setup/login, terminal default on, site and PIN provisioning, device pairing, signed heartbeat, CSRF rejection')

def signed(payload):
 raw=json.dumps(payload,separators=(',',':')).encode();stamp=str(int(time.time()));nonce=secrets.token_hex(16)
 signature=hmac.new(pair['key'].encode(),f'terminal-v1\n{device}\n{stamp}\n{nonce}\n'.encode()+raw,hashlib.sha256).hexdigest()
 headers={'Content-Type':'application/json','X-Terminal-Id':device,'X-Terminal-Time':stamp,'X-Terminal-Nonce':nonce,'X-Terminal-Signature':signature}
 try:
  with client.open(urllib.request.Request(base+'/terminal_api.php',raw,headers)) as r:return json.load(r)
 except urllib.error.HTTPError as e:
  data=json.load(e);raise RuntimeError(str(e.code)+': '+str(data.get('message','rejected'))) from None
def identify():return signed({'op':'identify','employee_code':'smoke01','pin':'00000000','card':''})
g=identify();assert g['state']=='absent'
event={'op':'event','grant':g['grant'],'event_id':secrets.token_hex(16),'action':'start','confirm_end_pause':False}
result=signed(event);assert result['state']=='working';assert signed(event)==result
record=str(result['record_id']);g=identify();assert g['state']=='working'
result=signed({'op':'event','grant':g['grant'],'event_id':secrets.token_hex(16),'action':'end','confirm_end_pause':False,'expected_record':record});assert result['state']=='absent'
page=get('/terminals.php');status,body,_=post('/terminals.php',{'_token':token(page),'action':'toggle'});assert status==200
assert not re.search(r'name="enabled"[^>]*checked',body)
print('PASS signed identify, start, exact retry, end, and UI module deactivation in disposable fixture')
