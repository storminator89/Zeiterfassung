"""Destructive integration suite for the isolated tests/seed.php fixture only."""
import concurrent.futures, hashlib, hmac, http.cookiejar, json, pathlib, re, secrets, sqlite3, sys, time, urllib.error, urllib.parse, urllib.request
BASE=sys.argv[1];DB=sys.argv[2];passed=0
class Client:
 def __init__(self):self.op=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def req(self,path,data=None,headers=None):
  if isinstance(data,dict):data=urllib.parse.urlencode(data).encode()
  try:r=self.op.open(urllib.request.Request(BASE+path,data=data,headers=headers or {}),timeout=15)
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read()
 def login(self,name):
  _,p=self.req('/login.php');self.csrf=re.search(rb'name="csrf_token" value="([a-f0-9]+)"',p)[1].decode()
  status,p=self.req('/login.php',dict(username=name,password='LocalAudit!Password2026',csrf_token=self.csrf));assert status==200
 def post(self,path,data):return self.req(path,dict(data,csrf_token=self.csrf))
def query(sql,args=()):
 with sqlite3.connect(DB) as db:
  db.create_function('audit_actor',0,lambda:'fixture');db.create_function('audit_reason',0,lambda:'test clock/data simulation');return db.execute(sql,args).fetchall()
def check(name,ok):
 global passed
 assert ok,name
 passed+=1;print('PASS',name)
def api(data,device=None,headers_override=None):
 raw=json.dumps(data,separators=(',',':')).encode();headers={'Content-Type':'application/json'}
 if device:
  stamp=str(int(time.time()));nonce=secrets.token_hex(16);id=str(device['device_id'])
  signature=hmac.new(device['key'].encode(),f'terminal-v1\n{id}\n{stamp}\n{nonce}\n'.encode()+raw,hashlib.sha256).hexdigest()
  headers.update({'X-Terminal-Id':id,'X-Terminal-Time':stamp,'X-Terminal-Nonce':nonce,'X-Terminal-Signature':signature})
 headers.update(headers_override or {})
 status,body=Client().req('/terminal_api.php',raw,headers)
 try:return status,json.loads(body)
 except ValueError:return status,{'body':body.decode()}
def identify(device,user='P02',pin='12345678',card=''):
 status,data=api({'op':'identify','employee_code':user,'pin':pin,'card':card},device)
 assert status==200,(status,data)
 return data
def event(device,grant,action,id=None,confirm=False):return api({'op':'event','grant':grant,'action':action,'event_id':id or secrets.token_hex(16),'confirm_end_pause':confirm},device)
# Refuse arbitrary data; the fixture intentionally has a known test-only credential.
assert query('SELECT username FROM users ORDER BY id')==[('auditadmin',),('audituser',),('auditother',)]
assert not query('SELECT id FROM terminals'), 'Use a fresh test fixture'
admin=Client();admin.login('auditadmin');user=Client();user.login('audituser');other=Client();other.login('auditother')
check('disabled API denies pairing',api({'op':'pair','device_id':1,'code':'x'})[0]==404)
check('ordinary user cannot manage terminals',user.req('/terminals.php')[0]==403)
check('management requires CSRF',admin.req('/terminals.php',{'action':'toggle','enabled':1})[0]==403)
check('admin enables optional module',admin.post('/terminals.php',{'action':'toggle','enabled':1})[0]==200 and query('SELECT enabled FROM terminal_settings')[0][0]==1)
status,page=user.req('/index.php')
check('authenticated web entry page loads',status==200 and bool(page.strip()) and b'Fatal error' not in page and b'name="password"' not in page)
status,p=admin.post('/terminals.php',{'action':'device','name':'Audit terminal','site_id':1});assert status==200
code=re.search(rb'<code>([a-f0-9]+)</code>',p)[1].decode();id=query('SELECT MAX(id) FROM terminals')[0][0]
check('wrong pairing code denied',api({'op':'pair','device_id':id,'code':'bad'})[0]==401)
status,device=api({'op':'pair','device_id':id,'code':code});check('pairing succeeds',status==200 and 'key' in device)
check('pairing code is single-use',api({'op':'pair','device_id':id,'code':code})[0]==401)
status,body=admin.post('/terminals.php',{'action':'credential','user_id':2,'site_id':1,'employee_code':'P02','pin':'12345678','card':'AABBCCDD'});check('credential assigned',status==200)
check('raw card and PIN not stored',query('SELECT pin_hash,card_hash FROM employee_credentials')[0][0]!='12345678' and query('SELECT card_hash FROM employee_credentials')[0][0]!='AABBCCDD')
check('unsigned identify denied',api({'op':'identify','employee_code':'P02','pin':'12345678'})[0]==401)
check('bad device signature denied',api({'op':'heartbeat'},device,{'X-Terminal-Signature':'0'*64})[0]==401)
check('stale request denied',api({'op':'heartbeat'},device,{'X-Terminal-Time':'1'})[0]==401)
raw=b'{"op":"heartbeat"}';stamp=str(int(time.time()));nonce=secrets.token_hex(16);signature=hmac.new(device['key'].encode(),f'terminal-v1\n{id}\n{stamp}\n{nonce}\n'.encode()+raw,hashlib.sha256).hexdigest();heads={'X-Terminal-Id':str(id),'X-Terminal-Time':stamp,'X-Terminal-Nonce':nonce,'X-Terminal-Signature':signature,'Content-Type':'application/json'}
check('signed heartbeat succeeds',Client().req('/terminal_api.php',raw,heads)[0]==200)
check('nonce replay denied',Client().req('/terminal_api.php',raw,heads)[0]==409)
check('wrong PIN denied',api({'op':'identify','employee_code':'P02','pin':'00000000'},device)[0]==401)
g=identify(device);check('PIN identifies only current employee',g['name']=='audituser' and g['state']=='absent' and 'users' not in g)
query('UPDATE terminal_grants SET expires=?',(int(time.time())-1,));check('expired identity cannot book',event(device,g['grant'],'start')[0]==401)
g=identify(device,card='aabbccdd');event_id=secrets.token_hex(16);status,result=event(device,g['grant'],'start',event_id);record=result.get('record_id');check('NFC plus PIN starts session',status==200 and result['state']=='working')
status,retry=event(device,g['grant'],'start',event_id);check('event retry returns same result',status==200 and retry==result)
check('same ID with changed content denied',event(device,g['grant'],'end',event_id)[0]==409)
check('consumed identity cannot perform another action',event(device,g['grant'],'end')[0]==401)
check('only one open session',query('SELECT COUNT(*) FROM work_sessions WHERE ended_at IS NULL')[0][0]==1)
# Same open state is visible and enforced through web and legacy APIs.
check('web duplicate start denied',user.post('/save.php',{'action':'start','startzeit':'2020-01-01'})[0]==409)
check('legacy record edit blocked',user.post('/save.php',{'id':record,'update':'true','column':'pause','data':999})[0]==409)
check('bulk delete blocked',user.req('/delete.php',json.dumps({'ids':[record]}).encode(),{'X-CSRF-Token':user.csrf})[0]==409)
check('active session prevents deactivation',admin.post('/terminals.php',{'action':'toggle'})[0]==200 and query('SELECT enabled FROM terminal_settings')[0][0]==1)
check('pause starts from web',user.post('/attendance.php',{'event_id':secrets.token_hex(16),'action':'pause_start','expected_record':str(record)})[0]==200)
g=identify(device);check('terminal sees web pause',g['state']=='paused')
check('end during pause requires confirmation',event(device,g['grant'],'end')[0]==409)
check('pause can end via same identity after confirmation prompt',event(device,g['grant'],'end',confirm=True)[0]==200)
check('pause and work close atomically',not query('SELECT id FROM break_intervals WHERE ended_at IS NULL') and not query('SELECT id FROM work_sessions WHERE ended_at IS NULL'))
check('no automatic minimum break deduction',query('SELECT pause FROM zeiterfassung WHERE id=?',(record,))[0][0]==0)
# Two independent signed clients race on the same employee.
query('DELETE FROM terminal_limits')
g1=identify(device);g2=identify(device)
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
 responses=list(pool.map(lambda g:event(device,g['grant'],'start'),[g1,g2]))
check('concurrent starts serialize',sorted(r[0] for r in responses)==[200,409])
record2=query('SELECT record_id FROM work_sessions WHERE ended_at IS NULL')[0][0]
# Model >24h across the fall DST transition without altering production clocks.
start=int(time.time())-26*3600
query('UPDATE work_sessions SET started_at=? WHERE record_id=?',(start,record2));query('UPDATE zeiterfassung SET startzeit=? WHERE id=?',('2026-10-24T02:00:00+02:00',record2))
query('DELETE FROM terminal_limits');g=identify(device);check('pause start signed',event(device,g['grant'],'pause_start')[0]==200)
query('UPDATE break_intervals SET started_at=? WHERE ended_at IS NULL',(int(time.time())-600,));g=identify(device);check('pause end signed',event(device,g['grant'],'pause_end')[0]==200)
g=identify(device);check('second pause supported',event(device,g['grant'],'pause_start')[0]==200)
query('UPDATE break_intervals SET started_at=? WHERE ended_at IS NULL',(int(time.time())-300,));g=identify(device);check('second pause closes',event(device,g['grant'],'pause_end')[0]==200)
g=identify(device);check('overnight work closes',event(device,g['grant'],'end')[0]==200)
check('actual breaks accumulate',query('SELECT pause FROM zeiterfassung WHERE id=?',(record2,))[0][0]==15)
check('UTC elapsed time exceeds 24h',query('SELECT ended_at-started_at FROM work_sessions WHERE record_id=?',(record2,))[0][0]>=26*3600)
# Revocations apply to grants already issued.
query('DELETE FROM terminal_limits');g=identify(device)
admin.post('/terminals.php',{'action':'revoke_credential','user_id':2})
check('credential revocation stops existing grant',event(device,g['grant'],'start')[0]==403)
check('revoked card cannot identify',api({'op':'identify','card':'AABBCCDD','pin':'12345678'},device)[0]==401)
admin.post('/terminals.php',{'action':'credential','user_id':2,'site_id':1,'employee_code':'P02','pin':'12345678','card':'AABBCCDD'})
query('UPDATE employee_credentials SET site_id=999 WHERE user_id=2');check('wrong site denied',api({'op':'identify','employee_code':'P02','pin':'12345678'},device)[0]==401);query('UPDATE employee_credentials SET site_id=1 WHERE user_id=2')
query('DELETE FROM terminal_limits');g=identify(device);admin.post('/terminals.php',{'action':'revoke_device','id':id})
check('device revocation immediate',event(device,g['grant'],'start')[0]==401)
# Correction workflow: own request, immutable events, authorized second person review.
status,page=user.post('/adjustments.php',{'action':'request','record_id':record,'start':'2026-10-10T09:00+02:00','end':'2026-10-10T17:00+02:00','pause':35,'reason':'Test: vergessenes Gehen korrigieren'})
check('employee correction request created',status==200 and query('SELECT id FROM time_adjustments'))
aid=query('SELECT MAX(id) FROM time_adjustments')[0][0]
check('employee cannot approve own correction',b'Keine Freigabeberechtigung' in user.post('/adjustments.php',{'action':'review','id':aid,'decision':'approved','review_reason':'Selbstfreigabe'})[1])
check('other ordinary employee cannot review',b'Keine Freigabeberechtigung' in other.post('/adjustments.php',{'action':'review','id':aid,'decision':'approved','review_reason':'Fremdfreigabe'})[1])
before=query('SELECT response FROM attendance_events ORDER BY id')
status,p=admin.post('/adjustments.php',{'action':'review','id':aid,'decision':'approved','review_reason':'Geprüft und freigegeben'})
check('admin approves correction',status==200 and query('SELECT status FROM time_adjustments WHERE id=?',(aid,))[0][0]=='approved')
check('original events unchanged',before==query('SELECT response FROM attendance_events ORDER BY id'))
check('projection reflects approved correction',query('SELECT pause FROM zeiterfassung WHERE id=?',(record,))[0][0]==35)
check('correction is only reviewed once',b'Keine Freigabeberechtigung' in admin.post('/adjustments.php',{'action':'review','id':aid,'decision':'rejected','review_reason':'Noch einmal'})[1])
check('restore requires full terminal backup',admin.post('/import.php',{})[0]==409)
admin.post('/terminals.php',{'action':'toggle'})
check('module can be disabled when sessions closed',query('SELECT enabled FROM terminal_settings')[0][0]==0)
check('disabled signed API denied',api({'op':'heartbeat'},device)[0]==404)
check('managed history remains protected when disabled',user.post('/save.php',{'id':record,'delete':'true'})[0]==409)
check('data kept after deactivation',query('SELECT COUNT(*) FROM attendance_events')[0][0]>0)
print(f'{passed} terminal assertions passed')
