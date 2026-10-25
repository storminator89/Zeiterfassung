"""Optional Playwright smoke test; disposable seed.php HTTP fixture only."""
import pathlib,sys
from playwright.sync_api import sync_playwright,expect
base=sys.argv[1];output=pathlib.Path(sys.argv[2]) if len(sys.argv)>2 else pathlib.Path('/tmp/chrono-previews');output.mkdir(exist_ok=True)
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':1280,'height':900},timezone_id='Europe/Berlin');errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.goto(base+'/login.php');page.locator('[name=username]').fill('audituser');page.locator('[name=password]').fill('LocalAudit!Password2026');page.locator('button[type=submit]').click();page.wait_for_url('**/attendance.php')
 expect(page.get_by_role('button',name='Pause beginnen')).to_be_hidden();page.get_by_role('button',name='Kommen',exact=True).click();expect(page.locator('#status')).to_have_text('Arbeitszeit läuft')
 page.get_by_role('button',name='Pause beginnen').click();expect(page.locator('#status')).to_have_text('Pause läuft')
 page.on('dialog',lambda dialog:dialog.accept());page.get_by_role('button',name='Gehen',exact=True).click();expect(page.locator('#status')).to_have_text('Nicht angemeldet');expect(page.get_by_role('button',name='Gehen',exact=True)).to_be_hidden();expect(page.locator('#message')).to_contain_text('bestätigt')
 print('PASS browser work/break/end flow with terminal disabled')
 page.locator('[name=urlaubStart]').fill('2036-01-08');page.locator('[name=urlaubEnde]').fill('2036-01-08');page.get_by_role('button',name='Abwesenheit erfassen').click();expect(page.locator('#absence-message')).to_contain_text('nach Arbeitszeitmodell')
 print('PASS absence form returns inline confirmation')
 page.screenshot(path=str(output/'attendance.png'),full_page=True)
 for path,title in [('work_models.php','Arbeitszeitmodelle'),('history.php','Änderungshistorie'),('adjustments.php','Korrekturanträge')]:
  page.goto(base+'/'+path);expect(page.locator('h1')).to_have_text(title);page.screenshot(path=str(output/(path+'.png')),full_page=True)
  page.set_viewport_size({'width':390,'height':844});assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),path
  page.set_viewport_size({'width':1280,'height':900})
 assert not errors,errors
 print('PASS models/history/corrections and mobile width without JS errors')
 browser.close()
