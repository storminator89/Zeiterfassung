"""Integration checks against a disposable instance seeded with tests/seed.php.
Usage: python3 tests/security_http.py http://127.0.0.1:8080 /path/to/test-data/timetracking.sqlite
Never run against production: writes and removes test records.
"""
import base64, hashlib, hmac, http.cookiejar, json, re, sqlite3, sys, time, urllib.error, urllib.parse, urllib.request
BASE=sys.argv[1]; DB=sys.argv[2]; passed=0
class Client:
 def __init__(self):
  self.cookies=http.cookiejar.CookieJar(); self.op=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.cookies)); self.csrf=''
 def request(self,path,data=None,headers=None,method=None):
  if isinstance(data,dict):data=urllib.parse.urlencode(data).encode()
  req=urllib.request.Request(BASE+path,data=data,headers=headers or {},method=method)
  try:r=self.op.open(req,timeout=15)
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read(),r.headers
 def login(self,name):
  status,page,headers=self.request('/login.php'); self.csrf=re.search(rb'name="csrf_token" value="([a-f0-9]+)"',page)[1].decode()
  status,page,headers=self.request('/login.php',dict(username=name,password='LocalAudit!Password2026',csrf_token=self.csrf))
  check('login '+name,status==200 and b'csrf-token' in page)
  return status,page,headers
 def api(self,path,data=None,token=None,method=None):
  return self.request('/api.php/'+path,json.dumps(data).encode() if data is not None else None,{'Content-Type':'application/json',**({'Authorization':'Bearer '+token} if token else {})},method)
def check(name,condition):
 global passed
 if not condition:raise AssertionError(name)
 passed+=1; print('PASS',name)
def query(sql,params=()):
 with sqlite3.connect(DB) as c:
  c.create_function('audit_actor',0,lambda:'fixture');c.create_function('audit_reason',0,lambda:'test clock/data simulation');return c.execute(sql,params).fetchall()
def forge(secret,**claims):
 enc=lambda obj:base64.urlsafe_b64encode(json.dumps(obj).encode()).decode().rstrip('=')
 body=enc({'typ':'JWT','alg':'HS256'})+'.'+enc(claims)
 return body+'.'+base64.urlsafe_b64encode(hmac.new(secret,body.encode(),hashlib.sha256).digest()).decode().rstrip('=')
anon=Client()
for path in ['/download.php','/assets/db/timetracking.sqlite','/.git/config','/config.php','/security.php','/tests/seed.php','/assets/db/.htaccess']:
 status,body,_=anon.request(path);check('anonymous denied '+path,status in [403,404] and not body.startswith(b'SQLite format 3'))
old=forge(b'your_secret_key',user_id=1,exp=int(time.time())+3600)
check('public-key forged token rejected',anon.api('users',token=old)[0]==401)
for token in ['x.y.z','e30.e30.x','none','a'*5000]:check('malformed token rejected',anon.api('users',token=token)[0]==401)
check('registration disabled by default',anon.request('/register.php')[0]==403)
user=Client();status,page,headers=user.login('audituser')
check('hardened session cookie',any(c.has_nonstandard_attr('HttpOnly') and c.get_nonstandard_attr('SameSite')=='Lax' for c in user.cookies))
for path in ['/download.php','/get_user.php?id=1']:
 check('ordinary user denied '+path,user.request(path)[0]==403)
check('admin page does not expose admin form',b'name="add_user"' not in user.request('/admin.php')[1])
for path,data in [('/save.php',{'action':'start','startzeit':'2026-10-25 10:00:00'}),('/settings.php',{'lang':'../config'}),('/delete.php',{'ids':[1]}),('/import.php',{})]:
 check('CSRF required '+path,user.request(path,data)[0]==403)
status,body,_=user.request('/index.php?lang=../config');check('language traversal neutralized',status==200 and b'<html lang="de"' in body)
status,body,_=user.request('/settings.php',{'lang':'../config','csrf_token':user.csrf});check('POST language traversal neutralized',status==200 and b'<html lang="de"' in body)
for page in ['index.php','dashboard.php','settings.php','get_time_records.php','get_latest_record.php']:
 status,body,_=user.request('/'+page);check('page '+page,status==200 and b'Fatal error' not in body)
headers={'X-CSRF-Token':user.csrf}
status,body,_=user.request('/save.php',{'action':'start','startzeit':'2026-10-25 10:00:00','standort':'office','beschreibung':'integration'},headers);check('start time record',status==200 and json.loads(body)['success'])
new_id=json.loads(body)['record_id']
check('direct edit requires correction',user.request('/save.php',{'update':'true','id':new_id,'column':'beschreibung','data':'updated'},headers)[0]==409)
other=Client();other.login('auditother')
check('other user cannot edit record',other.request('/save.php',{'update':'true','id':new_id,'column':'beschreibung','data':'stolen'},{'X-CSRF-Token':other.csrf})[0]==409)
check('record ownership preserved',query('SELECT beschreibung FROM zeiterfassung WHERE id=?',(new_id,))[0][0]=='')
status,body,_=user.api('login',{'username':'audituser','password':'LocalAudit!Password2026'});check('API login',status==200); token=json.loads(body)['token']
status,body,_=user.api('users',token=token);data=json.loads(body)['data'];check('API only returns current user',status==200 and len(data)==1 and data[0]['username']=='audituser')
check('API historical entry requires correction',user.api('workentry',{'startzeit':'2026-10-25 08:00:00','endzeit':'2026-10-25 17:00:00','pause':30,'beschreibung':'API integration','standort':'office'},token)[0]==409)
check('API rejects HTML pause',user.api('workentry',{'startzeit':'2026-10-25','pause':'<img src=x onerror=alert(1)>','beschreibung':'x','standort':'office'},token)[0]==409)
admin=Client();admin.login('auditadmin')
check('admin CSRF blocked',admin.request('/admin.php',{'add_department':1,'department_name':'blocked'})[0]==403)
status,body,_=admin.request('/admin.php',{'add_department':1,'department_name':'Audit department','csrf_token':admin.csrf});check('admin valid mutation',status==200 and query("SELECT name FROM departments WHERE name='Audit department'"))
status,body,_=admin.request('/download.php');check('authorized consistent backup',status==200 and body.startswith(b'SQLite format 3'))
check('invalid import blocked',admin.request('/import.php',{'csrf_token':admin.csrf})[0]==409)
query('UPDATE zeiterfassung SET beschreibung=?,standort=?,pause=? WHERE id=?',('=1+1','<img src=x onerror=alert(1)>','" onfocus="alert(1)',new_id))
status,body,_=user.request('/export_csv.php');check('CSV formula neutralized',status==200 and b"'=1+1" in body)
status,body,_=user.request('/get_time_records.php');check('pause HTML escaped',status==200 and b'&quot; onfocus=&quot;' in body and b'value="" onfocus=' not in body)
# The single PHP table render path must escape stored location HTML, never emit the raw payload.
status,body,_=admin.request('/supervisor.php');check('supervisor data encoded',status==200 and b'<td>&lt;img src=x onerror=alert(1)&gt;</td>' in body and b'<img src=x onerror=alert(1)>' not in body)
# Password changes revoke tokens and sessions without waiting for expiry.
old_hash=query('SELECT password FROM users WHERE id=2')[0][0]
query("UPDATE users SET password='changed-test-hash' WHERE id=2")
check('token revoked on password change',anon.api('users',token=token)[0]==401)
check('session revoked on password change',user.request('/settings.php')[0]==401)
query('UPDATE users SET password=? WHERE id=2',(old_hash,))
# User removal is enforced against the current database.
query("UPDATE users SET role='user' WHERE id=1")
check('admin demotion takes effect immediately',admin.request('/download.php')[0]==403)
query("UPDATE users SET role='admin' WHERE id=1")
for i in range(17):status,_,_=anon.api('login',{'username':'rate-test','password':'bad'})
check('login rate limited',status==429)
query("UPDATE users SET auth_source='ldap' WHERE id=3")
check('LDAP account has no local API fallback',anon.api('login',{'username':'auditother','password':'LocalAudit!Password2026'})[0]==401)
query("UPDATE users SET auth_source='local' WHERE id=3")
check('logout GET blocked',other.request('/logout.php')[0]==405)
check('logout CSRF required',other.request('/logout.php',{})[0]==403)
check('logout POST succeeds',other.request('/logout.php',{'csrf_token':other.csrf})[0]==200)
query('UPDATE work_sessions SET ended_at=? WHERE record_id=?',(int(time.time()),new_id));query('DELETE FROM zeiterfassung WHERE id=? OR beschreibung=?',(new_id,'API integration'));query("DELETE FROM departments WHERE name='Audit department'");query('DELETE FROM login_attempts')
print(f'{passed} checks passed')
