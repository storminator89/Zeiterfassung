"""Automatic holiday fetch/preview/import on the existing SYNTHETIC MCP fixture only.
This performs calendar writes exclusively on 127.0.0.1:18084, project chrono-mcp-http-test.
It calls the fixed public feiertage-api.de source for 2028; no credentials go to that service.
"""
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re
BASE='http://127.0.0.1:18084';YEAR='2028';PASSWORD='Disposable-Mcp-Only-82!'
client=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def send(path,data=None):
 try:
  with client.open(BASE+path,None if data is None else urllib.parse.urlencode(data).encode(),timeout=20) as r:return r.status,r.read().decode()
 except urllib.error.HTTPError as e:return e.code,e.read().decode()
def csrf(page):return re.search(r'name="_token" value="([^"]+)"',page)[1]
s,page=send('/login.php');s,page=send('/login.php',{'_token':csrf(page),'username':'mcp-fixture-admin','password':PASSWORD});assert s==200
s,page=send('/admin.php?section=system');assert s==200 and 'name="holiday_json"' not in page
token=csrf(page)
s,page=send('/admin.php?section=system',{'_token':token,'load_holidays':'1','holiday_year':YEAR});assert s==200,('load',s)
assert 'Feiertage '+YEAR in page and '>Neu</span>' in page and 'name="holiday_json"' not in page
proposal=re.search(r'name="holiday_import_token" value="([a-f0-9]{64})"',page)[1]
s,page=send('/admin.php?section=system',{'_token':token,'import_holidays':'1','holiday_import_token':'0'*64,'confirm_bw_source':'BW'});assert s==400,'forged preview token'
s,page=send('/admin.php?section=system',{'_token':token,'import_holidays':'1','holiday_import_token':proposal});assert s==400,'confirmation required'
# A second fetch still marks all candidates new: neither fetch nor rejected import wrote rows.
s,page=send('/admin.php?section=system',{'_token':token,'load_holidays':'1','holiday_year':YEAR});assert s==200 and '>Neu</span>' in page
proposal=re.search(r'name="holiday_import_token" value="([a-f0-9]{64})"',page)[1]
s,page=send('/admin.php?section=system',{'_token':token,'import_holidays':'1','holiday_import_token':proposal,'confirm_bw_source':'BW'});assert s==200 and re.search(YEAR+r': [1-9][0-9]* ergänzt, 0 bereits vorhanden',page),'confirmed import receipt'
s,page=send('/admin.php?section=system',{'_token':token,'import_holidays':'1','holiday_import_token':proposal,'confirm_bw_source':'BW'});assert s==400,'used preview cannot replay'
s,page=send('/admin.php?section=system',{'_token':token,'load_holidays':'1','holiday_year':YEAR});assert s==200 and '>Neu</span>' not in page and '>Vorhanden</span>' in page and 'name="holiday_import_token"' not in page,'existing calendar unchanged'
s,page=send('/admin.php?section=system',{'_token':token,'load_holidays':'1','holiday_year':'2028&nur_land=BY'});assert s==400,'year cannot alter fixed query'
print('PASS live fixed BW fetch, readable preview without JSON, read-only loading, bound confirmation, synthetic import receipt, replay rejection and unchanged existing calendar')
