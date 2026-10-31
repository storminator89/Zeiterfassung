"""Real Streamable HTTP journey on a NEW isolated instance, never user data.
Prepare /tmp/chrono-mcp-http-test on 127.0.0.1:18084, project chrono-mcp-http-test.
Creates two disposable users, API/MCP policies and synthetic bookings. No token output.
"""
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,subprocess,secrets
BASE='http://127.0.0.1:18084';CONTAINER='chrono-mcp-http-test-web-1';PASSWORD='Disposable-Mcp-Only-82!'
client=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
wire=urllib.request.build_opener(urllib.request.ProxyHandler({}))
def send(opener,path,data=None,headers=None,method=None):
 req=urllib.request.Request(BASE+path,data,headers or {},method=method)
 try:
  with opener.open(req,timeout=20) as r:return r.status,r.read().decode(),dict(r.headers)
 except urllib.error.HTTPError as e:return e.code,e.read().decode(),dict(e.headers)
def get(path):
 s,b,_=send(client,path);assert s==200,(path,s);return b
def token(body):return re.search(r'name="_token" value="([^"]+)"',body)[1]
def post(path,data):return send(client,path,urllib.parse.urlencode(data).encode())
def policy(action,revision,csrf,**extra):return post('/admin.php?section=system',{'_token':csrf,'mcp_management':'1','mcp_action':action,'mcp_revision':str(revision),'current_password':PASSWORD,**extra})
page=get('/register.php')
s,b,_=post('/setup.php',{'_token':token(page),'username':'mcp-fixture-admin','email':'mcp-admin@example.invalid','password':PASSWORD,'password_confirmation':PASSWORD});assert s==200,'new fixture required'
s,b,_=post('/login.php',{'_token':token(get('/login.php')),'username':'mcp-fixture-admin','password':PASSWORD});assert s==200
csrf=token(get('/admin.php'));s,b,_=post('/admin.php',{'_token':csrf,'add_department':'1','department_name':'MCP fixture'});assert s==200
page=get('/admin.php');department=re.search(r'<option value="(\d+)">MCP fixture</option>',page)[1]
for name in ['alice','bob']:
 s,b,_=post('/admin.php',{'_token':token(page),'add_user':'1','username':'mcp-fixture-'+name,'password':PASSWORD,'email':name+'@example.invalid','role':'user','department':department,'supervisor':'','regelarbeitszeit':'8','ueberstunden':'0'});assert s==200 and 'mcp-fixture-'+name in b,('create',s,b[:80])
csrf=token(get('/admin.php?section=system'))
s,b,_=post('/admin.php?section=system',{'_token':csrf,'api_management':'1','api_action':'enable','api_revision':'0','api_confirm':'1','api_create_key':'1','current_password':PASSWORD});assert s==200,('API enable',s)
# Issue actual fixture API keys through the existing credential-checked issuer; no custom JWT/signing implementation.
php="""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();if(config('app.url')!=='http://127.0.0.1:18084')exit(2);$a=[];foreach(['alice','bob'] as $name){$a[$name]=$app->make(App\\Api\\LegacyApiLogin::class)->issue('mcp-fixture-'.$name,'Disposable-Mcp-Only-82!','127.0.0.1');}echo json_encode($a);"""
keys=json.loads(subprocess.check_output(['docker','exec',CONTAINER,'php','-r',php],text=True));assert all(isinstance(v,str) and v.count('.')==2 for v in keys.values())
seq=0
def rpc(key,method,params=None,origin=None,path='/mcp',extra=None):
 global seq
 seq+=1;headers={'Accept':'application/json, text/event-stream','Content-Type':'application/json','MCP-Protocol-Version':'2025-11-25'}
 if key:headers['Authorization']='Bearer '+key
 if origin:headers['Origin']=origin
 headers.update(extra or {})
 return send(wire,path,json.dumps({'jsonrpc':'2.0','id':seq,'method':method,'params':params or {}}).encode(),headers)
assert rpc(keys['alice'],'initialize')[0]==404,'default off'
# Wrong password and CSRF cannot change policy.
assert post('/admin.php?section=system',{'_token':'invalid','mcp_management':'1','mcp_action':'enable','mcp_revision':'0','current_password':PASSWORD})[0] in [403,419]
assert policy('enable',0,csrf)[0]==200
s,b,_=rpc(None,'initialize');assert s==401,('missing auth',s)
assert rpc('invalid','initialize')[0]==401
assert rpc(keys['alice'],'initialize',origin='https://foreign.example')[0]==403
assert rpc(keys['alice'],'initialize',path='/mcp?api_key=ignored')[0]==403
for key in keys.values():
 s,b,h=rpc(key,'initialize',{'protocolVersion':'2025-11-25','capabilities':{},'clientInfo':{'name':'synthetic-http-test','version':'1'}});assert s==200 and 'serverInfo' in json.loads(b).get('result',{}),('initialize',s,b[:160])
 s,b,_=rpc(key,'tools/list');assert s==200 and len(json.loads(b)['result']['tools'])==10
assert send(wire,'/mcp',headers={'Authorization':'Bearer '+keys['alice']},method='GET')[0]==405
assert send(wire,'/mcp',headers={'Authorization':'Bearer '+keys['alice']},method='DELETE')[0]==405

def tool(key,name,args=None):
 s,b,_=rpc(key,'tools/call',{'name':'chrono_'+name,'arguments':args or {}});assert s==200,('tool status',s,b[:80]);result=json.loads(b)['result'];return result.get('structuredContent') or json.loads(result['content'][0]['text'])
a=tool(keys['alice'],'identity');b=tool(keys['bob'],'identity');assert a['success'] and b['success'] and a['profile_binding']!=b['profile_binding'],('identity',a,b)
assert tool(keys['alice'],'attendance')['state']=='absent';assert tool(keys['bob'],'attendance')['state']=='absent'
wrong=secrets.token_hex(16)
assert not tool(keys['bob'],'start',{'event_id':wrong,'confirmed_event_id':wrong,'profile_binding':a['profile_binding']})['success'],'cross-user binding'
event=secrets.token_hex(16);started=tool(keys['alice'],'start',{'event_id':event,'confirmed_event_id':event,'profile_binding':a['profile_binding']});assert started['success'] and started['state']=='working',started
assert tool(keys['bob'],'attendance')['state']=='absent','caller isolation'
assert tool(keys['bob'],'time_entries')['data']==[],'no foreign bookings'
entries=tool(keys['alice'],'time_entries');assert len(entries['data'])==1
record=started['record_id_string'];event=secrets.token_hex(16)
ended=tool(keys['alice'],'end',{'event_id':event,'confirmed_event_id':event,'profile_binding':a['profile_binding'],'expected_record':record,'confirmed_record_id':record});assert ended['success'] and ended['state']=='absent',ended
assert policy('disable',1,csrf)[0]==200
assert rpc(keys['alice'],'tools/list')[0]==404,'disable takes effect per request'
# User settings expose connection info; ordinary users cannot activate the global service.
other=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
s,page,_=send(other,'/login.php');s,b,_=send(other,'/login.php',urllib.parse.urlencode({'_token':token(page),'username':'mcp-fixture-bob','password':PASSWORD}).encode());assert s==200
s,page,_=send(other,'/settings.php');assert s==200 and 'Streamable HTTP' in page and BASE+'/mcp' in page
s,b,_=send(other,'/admin.php?section=system',urllib.parse.urlencode({'_token':token(page),'mcp_management':'1','mcp_action':'enable','mcp_revision':'2','current_password':PASSWORD}).encode());assert s==403,('nonadmin',s)
print('PASS Streamable HTTP initialize/tools, bearer auth, Origin/query rejection, two-user isolation, confirmed synthetic start/end, immediate disable and ordinary-user settings/authorization')
