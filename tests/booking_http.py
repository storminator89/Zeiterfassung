"""Booking regression checks; disposable seed.php fixture only."""
import http.cookiejar,json,re,sqlite3,sys,urllib.request,urllib.parse,urllib.error
BASE=sys.argv[1];DB=sys.argv[2];passed=0
class Client:
 def __init__(self):self.op=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def req(self,path,data=None):
  if isinstance(data,dict):data=urllib.parse.urlencode(data).encode()
  try:r=self.op.open(urllib.request.Request(BASE+path,data=data),timeout=15)
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read()
 def login(self,name):
  _,p=self.req('/login.php');self.csrf=re.search(rb'name="csrf_token" value="([a-f0-9]+)"',p)[1].decode();assert self.req('/login.php',dict(username=name,password='LocalAudit!Password2026',csrf_token=self.csrf))[0]==200
 def post(self,path,data):return self.req(path,dict(data,csrf_token=self.csrf))
def query(sql,args=()):
 with sqlite3.connect(DB) as db:
  db.create_function('audit_actor',0,lambda:'fixture');db.create_function('audit_reason',0,lambda:'booking regression fixture');return db.execute(sql,args).fetchall()
def check(name,ok):
 global passed
 assert ok,name
 passed+=1;print('PASS',name)
assert query('SELECT username FROM users ORDER BY id')==[('auditadmin',),('audituser',),('auditother',)]
admin=Client();admin.login('auditadmin');user=Client();user.login('audituser');other=Client();other.login('auditother')
admin.post('/work_models.php',dict(user_id=3,valid_from='2000-01-01',reason='Null-Soll Regression',**{'day'+str(i):0 for i in range(1,8)}))
status,body=other.req('/dashboard.php')
check('zero-target dashboard does not crash',status==200 and b'DivisionByZero' not in body and b'</html>' in body)
check('zero-target productivity displayed as zero',b'0.0%' in body)
status,body=user.req('/attendance.php?format=status&user_id=3');state=json.loads(body)
owned={r[0] for r in query('SELECT id FROM zeiterfassung WHERE user_id=2')}
check('read snapshot contains only authenticated users records',status==200 and all(r['id'] in owned for r in state['records']))
check('snapshot records have deterministic most-recent order',[r['id'] for r in state['records']]==sorted((r['id'] for r in state['records']),reverse=True))
count=query('SELECT COUNT(*) FROM time_adjustments')[0][0]
_,body=user.post('/adjustments.php',dict(action='request',record_id=0,start='2037-01-06T09:00+01:00',end='2037-01-06T10:00+01:00',pause=0,reason='Unzulässiger Zukunftsnachtrag'))
check('future work correction refused before persistence',b'erst nach' in body and query('SELECT COUNT(*) FROM time_adjustments')[0][0]==count)
# Simulate an old pending future request: validation must also happen on approval.
query("INSERT INTO time_adjustments(user_id,proposed_start,proposed_end,proposed_pause,reason,created_at) VALUES(2,2114838000,2114841600,0,'Older pending future request',1)")
aid=query('SELECT MAX(id) FROM time_adjustments')[0][0]
_,body=admin.post('/adjustments.php',dict(action='review',id=aid,decision='approved',review_reason='Regression future approval'))
check('old future request also cannot be approved',b'erst nach' in body and query('SELECT status FROM time_adjustments WHERE id=?',(aid,))==[('pending',)])
status,body=user.post('/save.php',dict(urlaubStart='2022-02-15',urlaubEnde='2022-02-15',beschreibung='Urlaub'))
check('past absence fixture booked',status==200 and json.loads(body)['success'])
record=query("SELECT id FROM zeiterfassung WHERE user_id=2 AND startzeit LIKE '2022-02-15%'")[0][0]
user.post('/adjustments.php',dict(action='request',record_id=0,start='2022-02-15T15:00+01:00',end='2022-02-15T16:00+01:00',pause=0,reason='Nachtrag am Urlaubstag'))
aid=query('SELECT MAX(id) FROM time_adjustments')[0][0]
_,body=admin.post('/adjustments.php',dict(action='review',id=aid,decision='approved',review_reason='Regression Tagesüberschneidung'))
check('afternoon correction cannot overlap all-day leave',b'ganzt' in body and query('SELECT status FROM time_adjustments WHERE id=?',(aid,))==[('pending',)])
check('failed approval leaves credit and no extra time entry',query("SELECT COUNT(*) FROM zeiterfassung WHERE user_id=2 AND startzeit LIKE '2022-02-15%'")==[(1,)])
query("INSERT INTO zeiterfassung(startzeit,endzeit,pause,beschreibung,user_id) VALUES('2022-02-16T15:00:00+01:00','2022-02-16T16:00:00+01:00',0,'Existing afternoon work',2)")
user.post('/adjustments.php',dict(action='request',record_id=record,start='2022-02-16T00:00+01:00',end='2022-02-16T04:00+01:00',pause=0,reason='Urlaub auf anderen Tag verschieben'))
aid=query('SELECT MAX(id) FROM time_adjustments')[0][0]
_,body=admin.post('/adjustments.php',dict(action='review',id=aid,decision='approved',review_reason='Regression umgekehrte Überschneidung'))
check('moving leave cannot overlap later work that day',b'ganzt' in body and query('SELECT status FROM time_adjustments WHERE id=?',(aid,))==[('pending',)])
# Contiguous past records are valid; only actual intersection is rejected.
user.post('/adjustments.php',dict(action='request',record_id=0,start='2022-02-16T16:00+01:00',end='2022-02-16T17:00+01:00',pause=0,reason='Anschließender Arbeitsblock'))
aid=query('SELECT MAX(id) FROM time_adjustments')[0][0]
admin.post('/adjustments.php',dict(action='review',id=aid,decision='approved',review_reason='Direkt anschließender Block geprüft'))
check('adjacent work intervals can be approved',query('SELECT status FROM time_adjustments WHERE id=?',(aid,))==[('approved',)])
# An older-format future closed entry must not allow a new live start inside its interval.
query("INSERT INTO zeiterfassung(startzeit,endzeit,pause,beschreibung,user_id) VALUES(datetime('now','-1 hour')||'+00:00',datetime('now','+1 hour')||'+00:00',0,'Existing future-spanning work',3)")
check('HTTP start rejects overlap with imported closed entry',other.post('/save.php',dict(action='start'))[0]==409)
status,body=admin.req('/supervisor.php')
check('admin supervisor view includes unassigned users',status==200 and b'auditother' in body)
print(f'{passed} booking HTTP assertions passed')
