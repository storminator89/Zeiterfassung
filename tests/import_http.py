import sys, urllib.request, urllib.parse, http.cookiejar, re, sqlite3, tempfile, pathlib
BASE=sys.argv[1]; DB=sys.argv[2]
op=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def req(path,data=None,headers={}):
 try:r=op.open(urllib.request.Request(BASE+path,data=data,headers=headers),timeout=15)
 except urllib.error.HTTPError as e:r=e
 return r.status,r.read()
_,page=req('/login.php');token=re.search(rb'name="csrf_token" value="([a-f0-9]+)"',page)[1].decode()
req('/login.php',urllib.parse.urlencode(dict(csrf_token=token,username='auditadmin',password='LocalAudit!Password2026')).encode())
status,backup=req('/download.php');assert status==200
boundary='ChronoAuditBoundary'
def upload(blob):
 body=(f'--{boundary}\r\nContent-Disposition: form-data; name="csrf_token"\r\n\r\n{token}\r\n--{boundary}\r\nContent-Disposition: form-data; name="dbFile"; filename="backup.sqlite"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+blob+f'\r\n--{boundary}--\r\n'.encode())
 return req('/import.php',body,{'Content-Type':f'multipart/form-data; boundary={boundary}'})
assert upload(b'not a database')[0]==400;print('PASS non-SQLite rejected')
with tempfile.NamedTemporaryFile() as f:
 f.write(backup);f.flush()
 with sqlite3.connect(f.name) as c:c.execute('CREATE TRIGGER malicious AFTER INSERT ON users BEGIN DELETE FROM users; END')
 assert upload(pathlib.Path(f.name).read_bytes())[0]==400;print('PASS trigger database rejected')
status,body=upload(backup);assert status==200 and b'name="password"' in body;print('PASS valid backup restored and session terminated')
with sqlite3.connect(DB) as c:assert c.execute('SELECT COUNT(*) FROM users').fetchone()[0]==3
print('PASS restore preserved all accounts')
