"""Full HTTP regression of mandatory web attendance, schedules and append-only history.
Use only the disposable seed.php fixture. The optional terminal must be disabled.
"""
import concurrent.futures, http.cookiejar, json, re, secrets, sqlite3, sys, time, urllib.request, urllib.parse, urllib.error
BASE=sys.argv[1];DB=sys.argv[2];passed=0
class Client:
 def __init__(self):self.op=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def req(self,path,data=None,headers=None):
  if isinstance(data,dict):data=urllib.parse.urlencode(data).encode()
  try:r=self.op.open(urllib.request.Request(BASE+path,data=data,headers=headers or {}),timeout=15)
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read()
 def login(self,name):
  _,p=self.req('/login.php');self.csrf=re.search(rb'name="csrf_token" value="([a-f0-9]+)"',p)[1].decode();assert self.req('/login.php',dict(username=name,password='LocalAudit!Password2026',csrf_token=self.csrf))[0]==200
 def post(self,path,data):return self.req(path,dict(data,csrf_token=self.csrf))
def query(sql,args=()):
 with sqlite3.connect(DB) as db:
  db.create_function('audit_actor',0,lambda:'fixture');db.create_function('audit_reason',0,lambda:'test clock/data simulation');return db.execute(sql,args).fetchall()
def check(name,ok):
 global passed
 assert ok,name
 passed+=1;print('PASS',name)
def fails(sql):
 try:query(sql)
 except sqlite3.IntegrityError:return True
 return False
assert query('SELECT username FROM users ORDER BY id')==[('auditadmin',),('audituser',),('auditother',)]
assert query('SELECT enabled FROM terminal_settings')==[(0,)]
admin=Client();admin.login('auditadmin');user=Client();user.login('audituser');other=Client();other.login('auditother')
check('web attendance works with terminal disabled',b'Pause beginnen' in user.req('/attendance.php')[1])
status,body=user.post('/save.php',dict(action='start',startzeit='2000-01-01'));record=json.loads(body)['record_id']
check('start uses server clock',status==200 and query('SELECT started_at FROM work_sessions WHERE record_id=?',(record,))[0][0]>time.time()-60)
query('UPDATE work_sessions SET started_at=? WHERE record_id=?',(int(time.time())-10*3600,record))
status,body=user.post('/save.php',dict(action='end',endzeit='2000-01-01',expected_record=str(record)))
check('ten-hour shift without recorded break has zero deduction',status==200 and query('SELECT pause FROM zeiterfassung WHERE id=?',(record,))[0][0]==0)
check('missing break generates warning only','pause_warning' in json.loads(body))
check('web actor in journal',query("SELECT actor FROM audit_log WHERE entity='zeiterfassung' AND record_id=? ORDER BY id DESC LIMIT 1",(record,))[0][0]=='web:2:user:2')
# Work models are versioned and restricted to administrators.
model=dict(user_id=2,valid_from='2030-01-01',reason='Teilzeit mit freiem Mittwoch',**{'day'+str(i+1):v for i,v in enumerate([240,360,0,300,180,120,0])})
check('model requires CSRF',admin.req('/work_models.php',model)[0]==403)
check('employee cannot change own schedule',user.post('/work_models.php',model)[0]==403)
admin.post('/work_models.php',dict(model,day1=-1));check('negative target rejected',not query("SELECT id FROM work_models WHERE valid_from='2030-01-01'"))
admin.post('/work_models.php',dict(model,valid_from='2030-02-30'));check('invalid calendar date rejected',not query("SELECT id FROM work_models WHERE valid_from='2030-02-30'"))
admin.post('/work_models.php',model);check('admin creates effective-dated model',bool(query("SELECT id FROM work_models WHERE valid_from='2030-01-01'")))
query("INSERT INTO Feiertage(datum,name) VALUES('2030-01-08','Test holiday')")
absence=dict(urlaubStart='2030-01-07',urlaubEnde='2030-01-13',beschreibung='Urlaub')
status,body=user.post('/save.php',absence)
check('absence uses daily schedule and skips holidays/free days',status==200 and query("SELECT substr(startzeit,1,10),planned_minutes,pause,absence_kind FROM zeiterfassung WHERE startzeit LIKE '2030-%' ORDER BY startzeit")==[('2030-01-07',240,0,'Urlaub'),('2030-01-10',300,0,'Urlaub'),('2030-01-11',180,0,'Urlaub'),('2030-01-12',120,0,'Urlaub')])
check('duplicate absence rejected atomically',user.post('/save.php',absence)[0]==409 and query("SELECT COUNT(*) FROM zeiterfassung WHERE startzeit LIKE '2030-%'")==[(4,)])
admin.post('/work_models.php',dict(model,valid_from='2030-01-10',day4=60,reason='Donnerstag ab Januar gekürzt'))
check('schedule revision retains booked credit',query("SELECT planned_minutes FROM zeiterfassung WHERE startzeit LIKE '2030-01-10%'")==[(300,)])
check('schedule history cannot update',fails("UPDATE work_models SET reason='rewrite'"))
check('schedule history cannot delete',fails('DELETE FROM work_models'))
check('journal cannot update',fails("UPDATE audit_log SET reason='rewrite'"))
check('journal cannot delete',fails('DELETE FROM audit_log'))
check('original punch events cannot update',fails("UPDATE attendance_events SET kind='end'"))
check('original punch events cannot delete',fails('DELETE FROM attendance_events'))
check('admin can inspect other employees models',b'Teilzeit mit freiem Mittwoch' in admin.req('/work_models.php')[1])
check('own history is readable',b'Teilzeit mit freiem Mittwoch' in user.req('/history.php')[1])
check('other employee cannot see history',b'Teilzeit mit freiem Mittwoch' not in other.req('/history.php')[1])
check('history HTML is escaped',b'<script>alert(1)</script>' not in user.req('/history.php')[1])
# Historical legacy records must use a second-person correction or cancellation too.
check('legacy direct edit denied',user.post('/save.php',dict(id=1,update='true',column='pause',data=0))[0]==409)
check('legacy direct delete denied',user.post('/save.php',dict(id=1,delete='true'))[0]==409)
user.post('/adjustments.php',dict(action='request',record_id=1,cancel=1,reason='Doppelte Altbuchung stornieren'))
aid=query('SELECT MAX(id) FROM time_adjustments')[0][0]
check('cancellation requested with original snapshot',query('SELECT cancel,original_json FROM time_adjustments WHERE id=?',(aid,))[0][0]==1)
user.post('/adjustments.php',dict(action='review',id=aid,decision='approved',review_reason='Selbstfreigabe'))
check('self approval denied',query('SELECT status FROM time_adjustments WHERE id=?',(aid,))==[('pending',)])
admin.post('/adjustments.php',dict(action='review',id=aid,decision='approved',review_reason='Doppelung geprüft'))
check('approved cancellation removes reporting projection',not query('SELECT id FROM zeiterfassung WHERE id=1'))
row=query("SELECT before_json,after_json,actor,reason FROM audit_log WHERE entity='zeiterfassung' AND record_id=1 AND operation='DELETE'")[0]
check('cancellation retains original and reviewer',json.loads(row[0])['id']==1 and row[1] is None and row[2]=='web:1' and 'Doppelung' in row[3])
check('final approval cannot be changed',fails(f"UPDATE time_adjustments SET status='pending' WHERE id={aid}"))
check('correction requests cannot be deleted',fails('DELETE FROM time_adjustments'))
# Forgotten open records from older versions can be ended without a fabricated break.
query("INSERT INTO zeiterfassung(startzeit,pause,user_id) VALUES(?,30,3)",(time.strftime('%Y-%m-%dT%H:%M:%S+00:00',time.gmtime(time.time()-10*3600)),))
legacy=query('SELECT MAX(id) FROM zeiterfassung')[0][0]
check('legacy open record visible',b'data-state="working"' in other.req('/attendance.php')[1])
check('legacy open record adopted and ended',other.post('/save.php',dict(action='end',expected_record=str(legacy)))[0]==200 and query('SELECT pause FROM zeiterfassung WHERE id=?',(legacy,))==[(0,)])
check('adoption preserves pre-upgrade value in journal',any(json.loads(r[0])['pause']==30 for r in query("SELECT before_json FROM audit_log WHERE entity='zeiterfassung' AND record_id=? AND before_json IS NOT NULL",(legacy,))))
# Whole absence ranges roll back if a later day conflicts.
status,_=user.post('/save.php',dict(urlaubStart='2030-01-06',urlaubEnde='2030-01-10',beschreibung='Krankheit'))
check('conflicting absence range rolls back',status==409 and not query("SELECT id FROM zeiterfassung WHERE absence_kind='Krankheit'"))
# Actual start blocking on a credited absence day (administrator has no previous work).
from datetime import datetime
from zoneinfo import ZoneInfo
today=datetime.now(ZoneInfo('Europe/Berlin')).strftime('%Y-%m-%d')
admin.post('/work_models.php',dict(user_id=1,valid_from=today,reason='Einzelner Testplan',**{'day'+str(i):60 for i in range(1,8)}))
check('today absence credit created',admin.post('/save.php',dict(urlaubStart=today,urlaubEnde=today,beschreibung='Urlaub'))[0]==200)
check('cannot punch into credited absence day',admin.post('/save.php',dict(action='start'))[0]==409)
# The API aliases also use mandatory attendance without hardware enabled.
status,body=other.req('/api.php/login',json.dumps(dict(username='auditother',password='LocalAudit!Password2026')).encode(),{'Content-Type':'application/json'})
headers={'Authorization':'Bearer '+json.loads(body)['token'],'Content-Type':'application/json'}
id=secrets.token_hex(16);payload=json.dumps(dict(event_id=id,startzeit='2000-01-01')).encode()
status,body=other.req('/api.php/workentry',payload,headers);result=json.loads(body)
check('API alias starts with terminal disabled',status==200 and result['state']=='working')
check('API alias retry is idempotent',other.req('/api.php/workentry',payload,headers)[1]==body)
status,body=other.req('/api.php/setendzeit',json.dumps(dict(id=result['record_id'])).encode(),headers)
check('API alias ends shared session',status==200)
check('short shift has no minimum break warning','pause_warning' not in json.loads(body))
# Two independent browser sessions submit the same absence simultaneously.
second=Client();second.login('audituser')
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
 responses=list(pool.map(lambda c:c.post('/save.php',dict(urlaubStart='2031-01-06',urlaubEnde='2031-01-06',beschreibung='Krankheit')),[user,second]))
check('concurrent absences create exactly one credit',sorted(r[0] for r in responses)==[200,409] and query("SELECT COUNT(*) FROM zeiterfassung WHERE startzeit LIKE '2031-01-06%'")==[(1,)])
# A stale correction cannot overwrite a later change, even for a legacy row.
old=query("SELECT id FROM zeiterfassung WHERE startzeit LIKE '2030-01-10%'")[0][0]
user.post('/adjustments.php',dict(action='request',record_id=old,start='2030-01-10T00:00+01:00',end='2030-01-10T01:00+01:00',pause=0,reason='Gutschrift an neues Modell anpassen'))
aid=query('SELECT MAX(id) FROM time_adjustments')[0][0]
query("UPDATE zeiterfassung SET beschreibung='Concurrent change' WHERE id=?",(old,))
_,body=admin.post('/adjustments.php',dict(action='review',id=aid,decision='approved',review_reason='Geprüfter Antrag'))
check('stale correction cannot overwrite newer record',b'inzwischen' in body and query('SELECT status FROM time_adjustments WHERE id=?',(aid,))==[('pending',)])
check('closed work intervals cannot be rewritten',fails('UPDATE work_sessions SET started_at=0 WHERE ended_at IS NOT NULL'))
check('closed break intervals cannot be rewritten',fails('UPDATE break_intervals SET started_at=0 WHERE ended_at IS NOT NULL'))
# Store hostile text through a real authorized endpoint and inspect escaped history output.
admin.post('/work_models.php',dict(model,valid_from='2035-01-01',reason='<script>alert(1)</script>'))
_,body=user.req('/history.php')
check('journal escapes stored hostile text',b'&lt;script&gt;' in body and b'<script>alert(1)</script>' not in body)
print(f'{passed} practice assertions passed')
