"""Backup/restore checks on an isolated fixture; browser partial imports must fail closed."""
import sys, urllib.request, urllib.parse, http.cookiejar, re, sqlite3, tempfile, pathlib
BASE=sys.argv[1]; DB=sys.argv[2]
op=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def req(path,data=None,headers={}):
 try:r=op.open(urllib.request.Request(BASE+path,data=data,headers=headers),timeout=15)
 except urllib.error.HTTPError as e:r=e
 return r.status,r.read()
_,page=req('/login.php');token=re.search(rb'name="csrf_token" value="([a-f0-9]+)"',page)[1].decode()
req('/login.php',urllib.parse.urlencode(dict(csrf_token=token,username='auditadmin',password='LocalAudit!Password2026')).encode())
status,backup=req('/download.php');assert status==200 and backup.startswith(b'SQLite format 3');print('PASS consistent database backup')
boundary='ChronoAuditBoundary'
body=(f'--{boundary}\r\nContent-Disposition: form-data; name="csrf_token"\r\n\r\n{token}\r\n--{boundary}\r\nContent-Disposition: form-data; name="dbFile"; filename="backup.sqlite"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+backup+f'\r\n--{boundary}--\r\n'.encode())
assert req('/import.php',body,{'Content-Type':f'multipart/form-data; boundary={boundary}'})[0]==409;print('PASS partial browser restore blocked')
with tempfile.TemporaryDirectory() as directory:
 restored=pathlib.Path(directory)/'timetracking.sqlite';restored.write_bytes(backup)
 with sqlite3.connect(restored) as copy,sqlite3.connect(DB) as live:
  assert copy.execute('PRAGMA integrity_check').fetchone()[0]=='ok'
  for (table,) in live.execute("SELECT name FROM sqlite_master WHERE type='table'"):
   assert copy.execute('SELECT * FROM "'+table+'"').fetchall()==live.execute('SELECT * FROM "'+table+'"').fetchall(),table
  assert copy.execute("SELECT name,sql FROM sqlite_master WHERE type='trigger' ORDER BY name").fetchall()==live.execute("SELECT name,sql FROM sqlite_master WHERE type='trigger' ORDER BY name").fetchall()
  print('PASS restored database retains all tables, rows and triggers')
  try:copy.execute("DELETE FROM audit_log")
  except sqlite3.IntegrityError:pass
  else:raise AssertionError('Restored journal can be deleted')
  print('PASS restored journal remains append-only')
